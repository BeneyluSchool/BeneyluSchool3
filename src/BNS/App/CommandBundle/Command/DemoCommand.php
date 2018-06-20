<?php
namespace BNS\App\CommandBundle\Command;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Exception\InvalidInstallApplication;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleBlog;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\BlogArticleComment;
use BNS\App\CoreBundle\Model\BlogArticleCommentPeer;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\LiaisonBookSignature;
use BNS\App\CoreBundle\Model\ProfileComment;
use BNS\App\CoreBundle\Model\ProfileFeed;
use BNS\App\CoreBundle\Model\ProfileFeedStatus;
use BNS\App\CoreBundle\Model\ProfilePreference;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\GPSBundle\Model\GpsCategory;
use BNS\App\GPSBundle\Model\GpsPlace;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkGroup;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\LunchBundle\Model\LunchDay;
use BNS\App\LunchBundle\Model\LunchWeek;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageText;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\PaasBundle\Manager\PaasManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Yaml\Yaml;

class DemoCommand extends ContainerAwareCommand
{

    protected $usedKeys = array();
    /** @var  OutputInterface $output */
    protected $output;

    protected $lang;
    protected $fullLang;
    protected $country;

    protected function configure()
    {
        $this->setName('bns:demo')
            ->setDescription('Insert demo content from group')
            ->addOption('groupId', null, InputOption::VALUE_REQUIRED, 'Group ID')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language', 'fr')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Country', null)
            ->addOption('onlyCity', null, InputOption::VALUE_NONE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // fix container
        BNSAccess::setContainer($this->getContainer());

        $locales = $this->getContainer()->get('bns.locale_manager')->availableLanguages;

        $lang = $input->getOption('lang');
        if (!in_array($lang, $locales)) {
            throw new \InvalidArgumentException(sprintf('Lang "%s" is invalid should be one of %s', $lang, implode(", ", $locales)));
        }
        $this->fullLang = $lang;
        $this->lang = explode('_', $lang)[0];
        $this->country = $input->getOption('country');
        if (!$this->country) {
            switch ($lang) {
                default:
                case 'fr':
                    $this->country = 'FR';
                    break;
                case 'en_GB':
                case 'en':
                    $this->country = 'GB';
                    break;
                case 'en_US':
                    $this->country = 'US';
                    break;
                case 'es':
                    $this->country = 'ES';
                    break;
                case 'es_AR':
                    $this->country = 'AR';
                    break;
            }
        }
        $this->getContainer()->get('session')->set('lang', $this->lang);
        ini_set('memory_limit','2048M');
        $this->output = $output;
        $group = GroupQuery::create()->findOneById($input->getOption('groupId'));

        if(!$group)
        {
           throw new \Exception('Please set a valid group id');
        }

        if(!in_array($group->getGroupType()->getType(), ['SCHOOL', 'CITY', 'ACADEMY']))
        {
            throw new \Exception('Please set a school, a city or an academy');
        }

        $this->output->writeln('This group is a ' . $group->getGroupType()->getType());
        if ($group->getGroupType()->getType() === 'SCHOOL') {
            $this->updateSchoolInformations($group);
            //Création de la classe
            $this->createClassroomDatas($group);
            $this->addSchoolVersion($group);
            $this->addSchoolApps($group);
            $this->createMiniSite($group);
            $this->createBlog($group);
            $this->createAgendaEvents($group);
            $this->createLunches($group);
            $this->output->writeln('<info>All done !</info>');
        }
        elseif ($group->getGroupType()->getType() === 'CITY') {
           $this->updateCityInformations($group);
           if (!$input->getOption('onlyCity')) {
               $output->writeln('creating school');
               $this->createLeasureCenterDatas($group);
           } else {
               $output->writeln('Skip creating school');
           }
           $this->addGroupApps($group);
           $this->createMiniSite($group, 'minisite-city');
           $this->createBlog($group, 'blog-city');
           $this->createAgendaEvents($group, 'agenda-city');
           $this->output->writeln('<info>All done !</info>');
        } elseif ($group->getGroupType()->getType() === 'ACADEMY') {
            $this->updateAcademyInformations($group);
            $this->createAcademyDatas($group);
            $this->addGroupApps($group);
            $this->createMiniSite($group, 'minisite-academy');
            $this->createBlog($group, 'blog-academy');
            $this->createAgendaEvents($group, 'agenda-academy');
            $this->output->writeln('<info>All done !</info>');
        }
    }

    protected function addSchoolApps($school)
    {
        $applicationManager = $this->getContainer()->get('bns_core.application_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $groupManager = $this->getContainer()->get('bns.group_manager');
        $apps = ['MINISITE', 'BLOG', 'LUNCH', 'CALENDAR', 'LIAISONBOOK'];
        foreach($apps as $app) {
            $paasManager->generateSubscription($school, $app, 'unlimited', null, null, $this->lang);

            try {
                // optionnal should be done by the subscription
                $applicationManager->installApplication($app, $school);
                $paasManager->generateSubscription($school, $app, 'unlimited', null, null, $this->lang);
                $roles = GroupTypeQuery::create()->filterBySimulateRole(true)->filterByType(['PARENT', 'PUPIL', 'TEACHER'])->find();
                foreach ($roles as $role) {
                    $groupManager->activationModuleRequest($applicationManager->getApplication($app), $role, true, null, $school->getId(), true);
                }
            } catch (InvalidInstallApplication $e) {
                $this->output->writeln(sprintf('<info>Try to install application "%s" failed : %s' , $app, $e->getMessage()));
            }
        }

        $favoriteApps = ['BLOG', 'CALENDAR', 'MEDIA_LIBRARY', 'CAMPAIGN', 'PORTAL', 'MINISITE'];
        /** @var Group $school */
        $school->setFavoriteModules([]);
        foreach ($favoriteApps as $favoriteApp) {
            $school->addFavoriteModule($favoriteApp);
        }
        $school->save();
        $this->output->writeln('<info>School apps installed</info>');
    }

    protected function addGroupApps($group)
    {
        $applicationManager = $this->getContainer()->get('bns_core.application_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $groupManager = $this->getContainer()->get('bns.group_manager');
        $apps = ['MINISITE', 'BLOG', 'CALENDAR', 'CAMPAIGN', 'PORTAL'];
        foreach($apps as $app) {
            try {
                // optionnal should be done by the subscription
                $applicationManager->installApplication($app, $group);
                $paasManager->generateSubscription($group, $app, 'unlimited', null, null, $this->lang);
                $roles = GroupTypeQuery::create()->filterBySimulateRole(true)->filterByType(['PARENT', 'PUPIL', 'TEACHER', 'DIRECTOR'])->find();
                foreach ($roles as $role) {
                    $groupManager->activationModuleRequest($applicationManager->getApplication($app), $role, true, null, $group->getId(), true);
                }
            } catch (InvalidInstallApplication $e) {
                $this->output->writeln(sprintf('<info>Try to install application "%s" failed : %s' , $app, $e->getMessage()));
            }
        }
        $favoriteApps = ['BLOG', 'CALENDAR', 'MEDIA_LIBRARY', 'CAMPAIGN', 'PORTAL', 'MINISITE', 'STATISTICS'];
        /** @var Group $group */
        $group->setFavoriteModules([]);
        foreach ($favoriteApps as $favoriteApp) {
            $group->addFavoriteModule($favoriteApp);
        }
        $group->save();
        $this->output->writeln('<info>City apps installed</info>');
    }

    protected function addSchoolVersion($school)
    {
        $userManager = $this->getContainer()->get('bns.user_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $groupManager = $this->getContainer()->get('bns.group_manager');
        $groupManager->setGroup($school);

        $ref = $groupManager->getUsersByRoleUniqueName('DIRECTOR');

        $user = UserQuery::create()
            ->findPk($ref[0]['id']);

        $paasManager->generateSubscription($school, PaasManager::PREMIUM_SUBSCRIPTION, 'unlimited', $user->getUsername(), null, $this->lang);
        $this->output->writeln('<info>School subscription activated with :</info>');

        $this->output->writeln($user->getUsername(). ' / ' . $user->getPassword());

    }

    protected function addClassroomApps($classroom)
    {
        $applicationManager = $this->getContainer()->get('bns_core.application_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $groupManager = $this->getContainer()->get('bns.group_manager');
        $apps = ['HOMEWORK', 'BLOG', 'CALENDAR', 'LIAISONBOOK', 'GPS', 'COMPETITION', 'WORKSHOP', 'FORUM', 'SEARCH', 'COURSE', 'NOTEBOOK', 'PUPILMONITORING', 'CHAT', 'LSU'];
        foreach($apps as $app) {
            $paasManager->generateSubscription($classroom, $app, 'unlimited', null, null, $this->lang);

            try {
                // optionnal should be done by the subscription
                $applicationManager->installApplication($app, $classroom);
                $roles = GroupTypeQuery::create()->filterBySimulateRole(true)->filterByType(['PARENT', 'PUPIL'])->find();
                foreach ($roles as $role) {
                    /** @var GroupType $role */
                    $groupManager->activationModuleRequest($applicationManager->getApplication($app), $role, true, null, $classroom->getId(), true);
                }
            } catch (InvalidInstallApplication $e) {
                $this->output->writeln(sprintf('<info>Try to install application "%s" failed : %s' , $app, $e->getMessage()));
            }
        }
        $favoriteApps = ['PROFILE', 'BLOG', 'HOMEWORK', 'LIAISONBOOK', 'CALENDAR', 'MESSAGING', 'MEDIA_LIBRARY', 'NOTIFICATION'];
        /** @var Group $classroom */
            $classroom->setFavoriteModules([]);
        foreach ($favoriteApps as $favoriteApp) {
            $classroom->addFavoriteModule($favoriteApp);
        }
        $classroom->save();
        $this->output->writeln('<info>Classroom apps installed</info>');
    }

    protected function createClassroomDatas($school)
    {
        $classroom = $this->createClassroom($school);
        $this->createAgendaEvents($classroom);
        $this->createHomeworks($classroom);
        $this->createLiaisonBooks($classroom);
        $this->createBlog($classroom);
        $this->createGPS($classroom);
        $this->createMessage($classroom);
        $this->addClassroomApps($classroom);
        $user = $this->createUserAndProfileDatasInGroup('director', $school);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('Director created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));
        $this->output->writeln('<info>All classroom tasks done !</info>');
    }

    protected function getRandomizedData($file, $key)
    {
        $datas = Yaml::parse(__DIR__ . '/../Resources/config/datas/'. $this->lang. '/' . $file . '.yml');
        $datas = $datas[$key];

        $chosenKey = array_rand($datas);

        if(isset($this->usedKeys[$file][$key][$chosenKey]))
        {
            $i = 0;
            while(isset($this->usedKeys[$file][$key][$chosenKey]) && $i < 30)
            {
                $chosenKey = array_rand($datas);
                $i++;
            }
        }

        $this->usedKeys[$file][$key][$chosenKey] = true;
        return $datas[$chosenKey];
    }

    protected function createMedia($user, $path, $folder)
    {
        $path = __DIR__ . '/../Resources/config/datas/'. $this->lang . $path;
        $fs = $this->getContainer()->get('bns.file_system_manager')->getFileSystem();
        $mc = $this->getContainer()->get('bns.media.creator');

        $mimeType = $mc->extensionToContentType(substr(strrchr($path, '.'),1));

        $params = array(
            'label' => substr(strrchr($path, '/'),1),
            'type' => $mc->getModelTypeFromMimeType($mimeType),
            'mime_type' => $mimeType,
            'media_folder' => $folder,
            'user_id' => $user->getId(),
            'filename' => substr(strrchr($path, '/'),1)
        );

        $media = $mc->createModelDatas($params);
        $fs->write($media->getFilePath(),file_get_contents($path));
        return $media;
    }

    protected function updateSchoolInformations(Group $school)
    {
        $school->setAttribute('NAME',$this->getRandomizedData('school','name'));
        $school->setAttribute('UAI',$this->getRandomizedData('school','uai'));
        $school->setAttribute('ADDRESS',$this->getRandomizedData('school','address'));
        $school->setAttribute('ZIPCODE',$this->getRandomizedData('school','zipcode'));
        $school->setAttribute('CITY',$this->getCity($school->getAttribute("ZIPCODE")));
        $school->setAttribute('HOME_MESSAGE',$this->getRandomizedData('school','home_message'));
        $school->setLang($this->fullLang);
        $school->setCountry($this->country);
        $school->save();
    }

    protected function createClassroom(Group $school)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $userManager = $this->getContainer()->get('bns.user_manager');
        $gm->setGroup($school);

        $classroomParams = array(
            'label' => $this->getRandomizedData('classroom','name'),
            'type'  => 'CLASSROOM'
        );

        $classroom = $gm->createSubgroupForGroup($classroomParams, $school->getId());
        $classroom->setAttribute('LEVEL', [$this->guessLevel($classroom->getLabel())]);
        $classroom->setAttribute('HOME_MESSAGE',$this->getRandomizedData('classroom','home_message'));
        $classroom->validateStatus();
        $classroom->setCountry($this->country);
        $classroom->setLang($this->fullLang);
        $classroom->save();

        //création des élèves
        $pupilCount = 0;

        while($pupilCount < 25)
        {
            $user = $this->createUserAndProfileDatas('pupil', $classroom);
            $pupilCount++;
            $this->output->writeln(sprintf('<info>%s</info> pupils created with login <info>%s</info>', $pupilCount, $user->getLogin()));
        }

        $parents = $user->getParents();
        foreach ($parents as $parent) {
            $parent = $userManager->resetUserPassword($parent, false, null, false);
            $this->output->writeln(sprintf('Parent created with login <info>%s</info> and Password : <info>%s</info>', $parent->getLogin(), $parent->getPassword()));
        }
        $teacherCount = 0;

        while($teacherCount < 2)
        {
            $user = $this->createUserAndProfileDatas('teacher', $classroom);
            $teacherCount++;
            $user = $userManager->resetUserPassword($user, false, null, false);
            $this->output->writeln(sprintf('<info>%s</info> teachers created with login <info>%s</info> and Password : <info>%s</info>', $teacherCount, $user->getLogin(), $user->getPassword()));
        }

        $user = $this->createUserAndProfileDatas('assistant', $classroom);
        $user = $userManager->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('assistant created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));

        $this->output->writeln(sprintf('Classroom <info>%s</info> created', $classroom->getId()));

        return $classroom;

    }


    protected function createUserAndProfileDatas($type = 'pupil',Group $classroom)
    {

        $um = $this->getContainer()->get('bns.user_manager');
        $rm = $this->getContainer()->get('bns.role_manager');
        $cm = $this->getContainer()->get('bns.classroom_manager');

        $array = $this->getRandomizedData($type,'datas');
        $values = array(
            'first_name' => $array['first_name'],
            'last_name' => $array['last_name'],
            'gender' => $array['gender'],
            'lang' => $this->fullLang
        );

        if(in_array($type,['teacher', 'assistant']))
        {
            $values['email'] = strtolower($array['first_name'] . '.' . $array['last_name'] . rand(1,9999) . '@demo.beneylu.com');
        }

        $user = $um->createUser($values, false);
        $user->setCguValidation(true)->setCguVersion(2)->save();
        $cm->setClassroom($classroom);
        /** @var User $pupil */
        if($type == 'pupil')
        {
            $cm->assignPupil($user, true);
        }elseif($type == 'teacher')
        {
            $cm->assignTeacher($user);
        } elseif ($type == 'assistant') {
            $cm->assignAssistant($user);
        }

        if ( $type == 'pupil') {
            $avatar = $this->createMedia($user, '/avatar/' . strtolower($user->getFirstName()) . '-' . strtolower($user->getLastName()) . '.jpg', $user->getMediaFolderRoot());
            $profile = $user->getProfile();
            $profile->setAvatarId($avatar->getId());
            $profile->setJob($array['job']);
            $profile->setDescription($array['presentation']);
            $profile->save();
            foreach ($array['likes'] as $like) {
                $preference = new ProfilePreference();
                $preference->setIsLike(1);
                $preference->setItem($like);
                $preference->setProfile($profile);
                $preference->save();
            }
            foreach ($array['dislikes'] as $dislike) {
                $preference = new ProfilePreference();
                $preference->setIsLike(0);
                $preference->setItem($dislike);
                $preference->setProfile($profile);
                $preference->save();
            }
        } else {
            //Gestion des avatars

            $avatar = $this->createMedia($user, '/avatar/' . strtolower($user->getFirstName()) . '.jpg', $user->getMediaFolderRoot());

            $profile = $user->getProfile();
            $profile->setAvatarId($avatar->getId());
            $profile->setJob($this->getRandomizedData($type,'job'));
            $profile->setDescription($this->getRandomizedData($type,'presentation'));
            $profile->save();

            $like = 0;
            while($like < 2)
            {
                $preference = new ProfilePreference();
                $preference->setIsLike(1);
                $preference->setItem($this->getRandomizedData($type,'like'));
                $preference->setProfile($profile);
                $preference->save();
                $like++;
            }

            $dislike = 0;
            while($dislike < 2)
            {
                $preference = new ProfilePreference();
                $preference->setIsLike(0);
                $preference->setItem($this->getRandomizedData($type,'like'));
                $preference->setProfile($profile);
                $preference->save();
                $dislike++;
            }
        }

        $statuts = 0;

        while($statuts < 5)
        {
            $profileFeed = new ProfileFeed();
            $profileFeed->setProfileId($profile->getId());
            $profileFeed->setDate(time());
            $values = array('PENDING_VALIDATION', 'VALIDATED', 'REFUSED');
            $profileFeed->setStatus($values[array_rand($values)]);

            $profileFeed->save();

            $statusDatas = $this->getRandomizedData($type,'status');

            $profileFeedStatus = new ProfileFeedStatus();
            $profileFeedStatus->setContent($statusDatas['text']);
            if(isset($statusDatas['image']))
            {
                $image = $this->createMedia($user, '/status/' . $statusDatas['image'], $user->getMediaFolderRoot());
                $profileFeedStatus->setResourceId($image->getId());
            }
            $profileFeedStatus->setProfileFeed($profileFeed);
            $profileFeedStatus->save();

            if(isset($statusDatas['comments']))
            {
                foreach($statusDatas['comments'] as $comment)
                {
                    $editor = UserQuery::create()->filterById($cm->getUsersIds())->addAscendingOrderByColumn("rand()")->findOne();
                    $feedComment = new ProfileComment();
                    $feedComment->setObjectId($profileFeed->getId());
                    $feedComment->setAuthorId($editor->getId());
                    $feedComment->setDate(time());
                    $feedComment->setContent($comment);
                    $values = array('PENDING_VALIDATION', 'VALIDATED', 'REFUSED');
                    $feedComment->setStatus($values[array_rand($values)]);
                    $feedComment->setProfileFeed($profileFeed);
                    $feedComment->save();
                }
            }
            $statuts++;
        }
        return $user;
    }


    protected function createUserAndProfileDatasInGroup($type = 'director',Group $group)
    {

        $um = $this->getContainer()->get('bns.user_manager');
        $rm = $this->getContainer()->get('bns.role_manager');

        $array = $this->getRandomizedData('teacher','datas');
        $values = array(
            'first_name' => $array['first_name'],
            'last_name' => $array['last_name'],
            'gender' => $array['gender'],
            'lang' => $this->fullLang
        );

        if(in_array($type,['teacher', 'assistant']))
        {
            $values['email'] = strtolower($array['first_name'] . '.' . $array['last_name'] . rand(1,9999) . '@demo.beneylu.com');
        }

        $user = $um->createUser($values, false);
        $user->setCguValidation(true)->save();
        /** @var User $user */
        switch ($type) {
            case 'director':
                $rm->setGroupTypeRoleFromType('DIRECTOR');
                break;
            case 'city-referent':
                $rm->setGroupTypeRoleFromType('CITY_REFERENT');
                break;
            case 'atice':
                $rm->setGroupTypeRoleFromType('ATICE');
                break;
            case 'admin':
                $rm->setGroupTypeRoleFromType('ADMIN_FUNCTIONNAL');
                break;
            case 'leasure-center':
                $rm->setGroupTypeRoleFromType('LEASURE_CENTER_REFERENT');
                break;
            case 'cptice':
                $rm->setGroupTypeRoleFromType('CPTICE');
                break;
        }


        $rm->assignRole($user, $group->getId());
            //Gestion des avatars

            $avatar = $this->createMedia($user, '/avatar/' . strtolower($user->getFirstName()) . '.jpg', $user->getMediaFolderRoot());

            $profile = $user->getProfile();
            $profile->setAvatarId($avatar->getId());
            $profile->setJob($this->getRandomizedData('teacher','job'));
            $profile->setDescription($this->getRandomizedData('teacher','presentation'));
            $profile->save();

            $like = 0;
            while($like < 2)
            {
                $preference = new ProfilePreference();
                $preference->setIsLike(1);
                $preference->setItem($this->getRandomizedData('teacher','like'));
                $preference->setProfile($profile);
                $preference->save();
                $like++;
            }

            $dislike = 0;
            while($dislike < 2)
            {
                $preference = new ProfilePreference();
                $preference->setIsLike(0);
                $preference->setItem($this->getRandomizedData('teacher','like'));
                $preference->setProfile($profile);
                $preference->save();
                $dislike++;
            }

        $statuts = 0;

        while($statuts < 5)
        {
            $profileFeed = new ProfileFeed();
            $profileFeed->setProfileId($profile->getId());
            $profileFeed->setDate(time());
            $values = array('PENDING_VALIDATION', 'VALIDATED', 'REFUSED');
            $profileFeed->setStatus($values[array_rand($values)]);

            $profileFeed->save();

            $statusDatas = $this->getRandomizedData('teacher','status');

            $profileFeedStatus = new ProfileFeedStatus();
            $profileFeedStatus->setContent($statusDatas['text']);
            if(isset($statusDatas['image']))
            {
                $image = $this->createMedia($user, '/status/' . $statusDatas['image'], $user->getMediaFolderRoot());
                $profileFeedStatus->setResourceId($image->getId());
            }
            $profileFeedStatus->setProfileFeed($profileFeed);
            $profileFeedStatus->save();
            $statuts++;
        }
        return $user;
    }

    protected function createAgendaEvents(Group $group, $file = 'agenda')
    {
        $calendar = $group->getAgenda();
/*
        if($group->getGroupType()->getType() == 'CLASSROOM')
        {
            $userIds = $gm->getUsersByRoleUniqueNameIds('TEACHER');
        }
*/
        $eventCount = 0;
        while($eventCount < 60)
        {
            $datas = $this->getRandomizedData($file, 'event');

            if(!in_array(strtolower(date('l',time() + 3600 * 24 * ($eventCount + 1))),array('sunday', 'saturday')))
            {
                $begin = mktime($datas['start'],0,0,date('m'),date('d') + ($eventCount + 1),date('Y'));
                $end = mktime($datas['end'],0,0,date('m'),date('d') + ($eventCount + 1),date('Y'));

                $eventInfos = array(
                    'dtstart' => $begin,
                    'dtend' => $end,
                    'summary' => $datas['title'],
                    'type' => 'PUNCTUAL'
                );

                $em = $this->getContainer()->get('bns.calendar_manager');
                $event = $em->createEvent($calendar->getId(), $eventInfos, true);
            }
            $eventCount++;
        }
        $this->output->writeln('<info>Calendar events created</info>');
    }

    protected function createHomeworks(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $teacher = $gm->getUsersByRoleUniqueName('TEACHER', true)->getFirst();

        $homeworkManager = $this->getContainer()->get('bns.homework_manager');

        $date = time();
        $endDate = time() + (60 * 3600 * 24);

        while ($date < $endDate) {
            if (in_array(strtolower(date('l', $date)), array('sunday', 'saturday'))) {
                $date = $date + (3600 * 24);
                continue;
            } else {
                $homeworkCount = 0;
                $homeworkMax = rand(1, 4);
                while ($homeworkCount < $homeworkMax) {
                    $datas = $this->getRandomizedData('homework', 'homework');
                    $subject = new HomeworkSubject();
                    $rootSubject = HomeworkSubject::fetchRoot($group->getId());
                    $subject->insertAsFirstChildOf($rootSubject);
                    $subject->setGroupId($group->getId());
                    $subject->setName($datas['subject']);
                    $subject->save();

                    foreach ($datas['homeworks'] as $homework) {
                        $newHomework = new Homework();
                        $newHomework->setName($homework["title"]);
                        $newHomework->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
                        $newHomework->setSubjectId($subject->getId());
                        $newHomework->setDescription($homework["content"]);
                        $newHomework->addGroup($group);
                        if (isset($homework['help'])) {
                            $newHomework->setHelptext($homework['help']);
                        }

                        $newHomework->setDate($date);

                        $homeworkManager->processHomework($newHomework);
                        $newHomework->save();

                        if (isset($homework['media'])) {
                            $media = $this->createMedia($teacher, '/homework/' . $homework['media'], $group->getMediaFolderRoot());
                            $newHomework->addResourceAttachment($media->getId());
                        }
                    }
                    $homeworkCount++;
                }
                $date = $date + (3600 * 24);
            }
        }
        $this->output->writeln('<info>Homeworks created</info>');
    }

    protected function createLiaisonBooks(Group $group)
    {

        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $teacher = $gm->getUsersByRoleUniqueName('TEACHER', true)->getFirst();
        $parentsIds = $gm->getUsersByRoleUniqueNameIds('PARENT');

        $liaisonBookCount = 0;
        while($liaisonBookCount < 3)
        {
            $datas = $this->getRandomizedData('liaisonBook', 'liaisonBook');
            $lb = new LiaisonBook();
            $lb->setGroupId($group->getId());
            $lb->setTitle($datas['title']);
            $lb->setContent($datas['content']);
            $lb->setDate(mktime(0,0,0, date('m'), date('d') + rand(1,60), date('Y')));
            $lb->setAuthorId($teacher->getId());
            $lb->save(null, true);
            $signatures = 0;
            $dones = array();
            while($signatures < rand(1, count($parentsIds)))
            {
                $lbsi = new LiaisonBookSignature();
                $lbsi->setLiaisonBookId($lb->getId());

                $parentId = UserQuery::create()->filterById($parentsIds)->addAscendingOrderByColumn("rand()")->findOne()->getId();

                if(!in_array($parentId, $dones))
                {
                    $lbsi->setUserId($parentId);
                    $lbsi->save();
                    $signatures++;
                    $dones[] = $parentId;
                }
            }
            $liaisonBookCount++;
        }
        $this->output->writeln('<info>Liaison created</info>');
    }

    protected function createBlog(Group $group, $file = 'blog')
    {
        $blog = $group->getBlog();

        $articleCount = 0;
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $pupilsIds = $gm->getUsersByRoleUniqueNameIds('PUPIL');
        $teacher = $gm->getUsersByRoleUniqueName('TEACHER', true)->getFirst();
        if ($file !== 'blog') {
            $this->createMedia($teacher, '/blog/isserteaux-logo.png', $group->getMediaFolderRoot());
        }
        $root = BlogCategoryQuery::create()->findRoot($blog->getId());
        if(!$root)
        {
            $root = new BlogCategory();
            $root->setBlogId($blog->getId());
            $root->makeRoot();
            $root->setTitle('root');
            $root->save();
        }

        while($articleCount < 5)
        {
            $pupil = UserQuery::create()->filterById($pupilsIds)->addAscendingOrderByColumn("rand()")->findOne();
            if (!$pupil) {
                $this->createUserAndProfileDatas('pupil', $group);
                $pupilsIds = $gm->getUsersByRoleUniqueNameIds('PUPIL');
                $pupil = UserQuery::create()->filterById($pupilsIds)->addAscendingOrderByColumn("rand()")->findOne();
            }
            $pupilId = $pupil->getId();
            $article = new BlogArticle();
            $datas = $this->getRandomizedData($file, 'blog');
            $article->setTitle($datas['title']);
            $article->setContent($datas['content']);
            $article->setStatus(BlogArticlePeer::STATUS_PUBLISHED);
            $article->setBlogReferenceId($blog->getId());
            $article->setAuthorId($pupilId);
            $article->setIsCommentAllowed(true);
            $article->setPublishedAt(time());
            $article->setCreatedAt(time());
            $article->save(null, true, true);

            if(isset($datas['images']))
            {
                if ($file === 'blog') {
                    $path = '/blog/';
                } else  {
                    $path = '/medialibrary/';
                }
                foreach($datas['images'] as $image)
                {
                    $media = $this->createMedia($teacher, $path . $image, $group->getMediaFolderRoot());
                    $articleContent = $article->getContent();
                    $articleContent .= "<img class='bns-insert-resources image-limit image' src='' alt='' data-slug='" . $media->getSlug() . "' data-uid='" . $teacher->getId() . "' data-id='" . $media->getId() . "'>";
                    $article->setContent($articleContent);
                }
                $article->save(null, true);
            }

            $link = new BlogArticleBlog();
            $link->setBlogId($blog->getId());
            $link->setArticleId($article->getId());
            $link->save();

            $category = new BlogCategory();
            $category->setBlogId($blog->getId());
            $category->setTitle($datas['category']);

            $category->insertAsFirstChildOf($root);
            $category->save();

            $link = new BlogArticleCategory();
            $link->setArticleId($article->getId());
            $link->setCategoryId($category->getId());
            $link->save();
            if(isset($datas['comments']))
            {
                foreach($datas['comments'] as $commentData)
                {
                    $pupilId = UserQuery::create()->filterById($pupilsIds)->addAscendingOrderByColumn("rand()")->findOne()->getId();
                    $comment = new BlogArticleComment();
                    $comment->setObjectId($article->getId());
                    $comment->setAuthorId($pupilId);
                    $comment->setStatus(BlogArticleCommentPeer::STATUS_VALIDATED);
                    $comment->setBlogId($blog->getId());
                    $comment->setContent($commentData);
                    $comment->setDate(time());
                    $comment->save(null, true);
                }
            }
            $articleCount++;
        }
        $this->output->writeln('<info>Blog created</info>');
    }

    protected function createMessage($group)
    {
        $messageCount = 0;
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        while($messageCount < 25)
        {
            $data = $this->getRandomizedData('messaging','message');
            $message = new MessagingMessage();
            $fromId = UserQuery::create()->filterById($gm->getUsersByRoleUniqueNameIds(strtoupper($data['from'])))->addAscendingOrderByColumn("rand()")->findOne()->getId();
            $to = UserQuery::create()->filterById($gm->getUsersByRoleUniqueNameIds(strtoupper($data['to'])))->addAscendingOrderByColumn("rand()")->findOne();
            $message->setAuthorId($fromId);
            $message->setSubject($data['title']);
            $message->setContent($data['content']);
            $message->setStatus(2);
            $message->save();
            $this->getContainer()->get('bns.message_manager')->setUser(UserQuery::create()->findOneById($fromId));
            $this->getContainer()->get('bns.message_manager')->sendMessage($message, 1, null, array($to));
            $messageCount++;
        }
        $this->output->writeln('<info>Messages created</info>');
    }

    protected function createGPS($group)
    {
        $gpsCount = 0;
        while ($gpsCount < 2) {
            $data = $this->getRandomizedData('gps', 'gps');
            $category = new GpsCategory();
            $category->setGroupId($group->getId());
            $category->setLabel($data['title']);
            $category->setIsActive(true);
            $category->save();
            foreach ($data['places'] as $place) {
                $gps = new GpsPlace();
                $gps->setLabel($place['title']);
                $gps->setAddress($place['address']);
                $gps->setGpsCategoryId($category->getId());
                $gps->setIsActive(true);
                $this->getContainer()->get('bns.geocoords_manager')->setGeoCoords($gps);
                $gps->save();
            }
            $gpsCount++;
        }
        $this->output->writeln('<info>GPS created</info>');
    }

    protected function createMiniSite(Group $group, $file = 'minisite')
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $author = UserQuery::create()->filterById($gm->getUsersByRoleUniqueNameIds('TEACHER'))->addAscendingOrderByColumn("rand()")->findOne();
        if (!$author) {
            $this->createUserAndProfileDatas('teacher', $group);
            $author = UserQuery::create()->filterById($gm->getUsersByRoleUniqueNameIds('TEACHER'))->addAscendingOrderByColumn("rand()")->findOne();
        }
        $data = $this->getRandomizedData($file,'minisite');
        /** @var MiniSite $miniSite */
        $miniSite = MiniSiteQuery::create()->filterByGroup($group)->findOneOrCreate();
        if ( $file === 'minisite') {
            $path = '/minisite/';
        } else {
            $path = '/medialibrary/';
        }
        $banner = $this->createMedia($author, $path . $data['banner'] ,$group->getMediaFolderRoot());
        $miniSite->setBannerResourceId($banner->getId());
        $miniSite->setDescription($data['presentation']);
        $miniSite->save();
        $this->createMiniSitePage($miniSite, $data['home'], $author, true);
        if ($file !== 'minisite') {
            foreach ($data['actu'] as $actu) {
                $this->createMiniSitePage($miniSite, $actu, $author, false, '/medialibrary/');
            }
        } else {
            $this->createMiniSitePage($miniSite, $data['actu'], $author);
        }
        foreach($data['pages'] as $page)
        {
            $this->createMiniSitePage($miniSite, $page, $author);
        }
        $this->output->writeln('<info>Minisite created</info>');
    }

    public function createMiniSitePage(MiniSite $miniSite, $data, $author, $isHome = false, $path = '/minisite/')
    {
        $page = new MiniSitePage();
        $page->setIsActivated(true);
        $page->setIsPublic(true);
        $page->setMiniSiteId($miniSite->getId());
        $page->setIsHome($isHome);
        $page->setTitle($data['title']);
        $page->setType(is_array($data['content']) ? MiniSitePagePeer::TYPE_NEWS : MiniSitePagePeer::TYPE_TEXT);
        $page->setRank(count($miniSite->getMiniSitePages()) + 1);
        $page->save();

        if(!is_array($data['content']))
        {
            $pageText = new MiniSitePageText();
            $pageText->setPageId($page->getId());
            $pageText->setAuthorId($author->getId());
            $pageText->setLastModificationAuthorId($author->getId());
            $pageText->setStatus(MiniSitePageTextPeer::STATUS_PUBLISHED);
            $pageText->setPublishedAt(time());

            $content = $data['content'];

            if(isset($data['images']))
            {
                foreach($data['images'] as $image)
                {
                    $media = $this->createMedia($author, $path . $image, $miniSite->getGroup()->getMediaFolderRoot());
                    $content .= "<img class='bns-insert-resources image-limit image' src='' alt='' data-slug='" . $media->getSlug() . "' data-uid='" . $author->getId() . "' data-id='" . $media->getId() . "'>";
                }
            }

            $pageText->setDraftContent($content);
            $pageText->setPublishedContent($content);
            $pageText->setPublishedTitle($data['title']);
            $pageText->setDraftTitle($data['title']);
            $pageText->save();
        }else{
            foreach($data['content'] as $actu)
            {
                $pageNew = new MiniSitePageNews();
                $pageNew->setPageId($page->getId());
                $pageNew->setAuthor($author);
                $cont = $actu['content'];
                if(isset($data['images']))
                {
                    foreach($data['images'] as $image)
                    {
                        $media = $this->createMedia($author, '/minisite/' . $image, $miniSite->getGroup()->getMediaFolderRoot());
                        $cont .= "<img class='bns-insert-resources image-limit image' src='' alt='' data-slug='" . $media->getSlug() . "' data-uid='" . $author->getId() . "' data-id='" . $media->getId() . "'>";
                    }
                }
                $pageNew->setContent($cont);
                $pageNew->setStatus(MiniSitePageNewsPeer::STATUS_PUBLISHED);
                $pageNew->setPublishedAt(time());
                $pageNew->setTitle($actu['title']);
                $pageNew->save();
            }
        }


    }

    protected function createLunches(Group $group)
    {

        $lunchCount = 0;
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $dateStart = strtotime('last Monday');
        while($lunchCount < 10) {
            $data = $this->getRandomizedData('lunch', 'lunch');
            $week = new LunchWeek();
            $week->setGroupId($group->getId());
            $week->setSections(array('full_menu', 'starter', 'main_course', 'dessert', 'dairy', 'accompaniment', 'afternoon_snack'));
            $week->setLabel($data['label']);
            $week->setDescription($data['description']);
            $week->setDateStart($dateStart);
            $week->save();
            foreach($data['menus'] as $key=>$menu){
                $lunch = new LunchDay();
                $lunch->setWeekId($week->getId());
                $lunch->setStatus($menu['status']);
                $lunch->setDayOfWeek($key+1);
                if(isset($menu['full_menu'])){
                    $lunch->setFullMenu($menu['full_menu']);
                }
                if(isset($menu['starter'])){
                    $lunch->setStarter($menu['starter']);
                }
                if(isset($menu['main_course'])){
                    $lunch->setMainCourse($menu['main_course']);
                }
                if(isset($menu['dessert'])){
                    $lunch->setDessert($menu['dessert']);
                }
                if(isset($menu['dairy'])){
                    $lunch->setDairy($menu['dairy']);
                }
                if(isset($menu['accompaniment'])){
                    $lunch->setAccompaniment($menu['accompaniment']);
                }
                if(isset($menu['afternoon_snack'])){
                    $lunch->setAfternoonSnack($menu['afternoon_snack']);
                }
                $lunch->save();
            }
            $dateStart = strtotime("+7 days", $dateStart);
            $lunchCount++;
        }

        $this->output->writeln('<info>Lunches created</info>');
    }

    protected function updateCityInformations(Group $group)
    {
        $group->setAttribute('NAME',$this->getRandomizedData('city','name'));
        $group->setAttribute('HOME_MESSAGE',$this->getRandomizedData('city','home_message'));
        $group->setLang($this->fullLang);
        $group->setCountry($this->country);
        $group->save();
    }

    protected function updateAcademyInformations(Group $group)
    {
        $group->setAttribute('NAME',$this->getRandomizedData('academy','name'));
        $group->setAttribute('HOME_MESSAGE',$this->getRandomizedData('academy','home_message'));
        $group->setLang($this->fullLang);
        $group->setCountry($this->country);
        $group->save();
    }

    protected function createSchoolDatas(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);

        $schoolParams = array(
            'label' => $this->getRandomizedData('school','name'),
            'type'  => 'SCHOOL'
        );

        $school = $gm->createSubgroupForGroup($schoolParams, $group->getId());
        $school->setAttribute('HOME_MESSAGE',$this->getRandomizedData('school','home_message'));
        $school->validateStatus();
        $school->setAttribute('UAI',$this->getRandomizedData('school','uai'));
        $school->setAttribute('ZIPCODE', $this->getRandomizedData('city','zipcode'));
        $school->setAttribute('CITY',$this->getCity($school->getAttribute("ZIPCODE")));
        $school->setCountry($this->country);
        $school->setLang($this->fullLang);
        $school->save();

        $user = $this->createUserAndProfileDatasInGroup('leasure-center', $group);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, true);
        $this->output->writeln(sprintf('Leasure Center referent created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));
        $this->createClassroomDatas($school);
        $this->addSchoolVersion($school);
        $this->addSchoolApps($school);
        $this->createMiniSite($school, 'minisite');
        $this->createBlog($school, 'blog');
        $this->createAgendaEvents($school, 'agenda');
        $this->createLunches($school);
        $this->output->writeln('<info> School Done </info>');
    }

    protected function createAcademyDatas(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);

        $departmentParams = array(
            'label' => $this->getRandomizedData('department','name'),
            'type'  => 'DEPARTMENT'
        );

        $department = $gm->createSubgroupForGroup($departmentParams, $group->getId());
        $department->setAttribute('HOME_MESSAGE',$this->getRandomizedData('department','home_message'));
        $department->setCountry($this->country);
        $department->setLang($this->fullLang);
        $department->save();


        $user = $this->createUserAndProfileDatasInGroup('admin', $group);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('functionnal admin created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));
        $this->createCityDatas($department);
        $this->createCirconscriptionDatas($department);
        $this->createMiniSite($department, 'minisite-department');
        $this->createBlog($department, 'blog-department');
        $this->createAgendaEvents($department, 'agenda-department');
        $this->addGroupApps($department);
        $this->output->writeln('<info> Department Done </info>');
    }

    protected function createCirconscriptionDatas(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);

        $circonscriptionParams = array(
            'label' => $this->getRandomizedData('circonscription','name'),
            'type'  => 'CIRCONSCRIPTION'
        );

        $circonscription = $gm->createSubgroupForGroup($circonscriptionParams, $group->getId());
        $circonscription->setAttribute('HOME_MESSAGE',$this->getRandomizedData('circonscription','home_message'));
        $circonscription->setCountry($this->country);
        $circonscription->setLang($this->fullLang);
        $circonscription->save();


        $user = $this->createUserAndProfileDatasInGroup('atice', $circonscription);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('Atice created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));
        $schools = $gm->getAllSubgroups($group->getId(), ['SCHOOL'], false);
        $gm->addParent($schools[0], $circonscription->getId());
        $this->createMiniSite($circonscription, 'minisite-circonscription');
        $this->createBlog($circonscription, 'blog-circonscription');
        $this->createAgendaEvents($circonscription, 'agenda-circonscription');
        $this->addGroupApps($circonscription);
        $this->output->writeln('<info> Circonscription Done </info>');
    }

    protected function createCityDatas(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);

        $cityParams = array(
            'label' => $this->getRandomizedData('city','name'),
            'type'  => 'CITY'
        );

        $city = $gm->createSubgroupForGroup($cityParams, $group->getId());
        $city->setAttribute('HOME_MESSAGE',$this->getRandomizedData('city','home_message'));
        $city->setCountry($this->country);
        $city->setLang($this->fullLang);
        $city->save();

        $user = $this->createUserAndProfileDatasInGroup('cptice', $group);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('Cptice created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));
        $this->createLeasureCenterDatas($city);
        $this->createMiniSite($city, 'minisite-city');
        $this->createBlog($city, 'blog-city');
        $this->createAgendaEvents($city, 'agenda-city');
        $this->addGroupApps($city);
        $this->output->writeln('<info> City Done </info>');
    }


    protected function createLeasureCenterDatas(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);

        $centerParams = array(
            'label' => $this->getRandomizedData('centre','name'),
            'type'  => 'LEASURE_CENTER'
        );

        $center = $gm->createSubgroupForGroup($centerParams, $group->getId());
        $center->setAttribute('HOME_MESSAGE',$this->getRandomizedData('centre','home_message'));
        $center->setCountry($this->country);
        $center->setLang($this->fullLang);
        $center->save();

        $user = $this->createUserAndProfileDatasInGroup('city-referent', $group);
        $user = $this->getContainer()->get('bns.user_manager')->resetUserPassword($user, false, null, false);
        $this->output->writeln(sprintf('City referent created with login <info>%s</info> and Password : <info>%s</info>', $user->getLogin(), $user->getPassword()));

        $this->createSchoolDatas($center);
        $this->createMiniSite($center, 'minisite-centre');
        $this->createBlog($center, 'blog-centre');
        $this->createAgendaEvents($center, 'agenda-centre');
        $this->addGroupApps($center);
        $this->output->writeln('<info> Leasure Center Done </info>');
    }

    protected function getCity($zipCode){
        switch ($zipCode){
            case "13000":
                return "Marseille";
            case "69000":
                return "Lyon";
            case "75000":
                return "Paris";
            case "59000":
                return "Lille";
            case "35000":
                return "Rennes";
            case "44000":
                return "Nantes";
            case "33000":
                return "Bordeaux";
        }
       return $this->getRandomizedData('school', 'city');
    }

    protected function guessLevel($label)
    {
        $level = substr($label, 0, 3);
        $classroomLevel = GroupTypeDataChoiceQuery::create()->filterByGroupTypeDataTemplateUniqueName('LEVEL')->filterByValue($level)->findOne();
        return $classroomLevel->getValue();
    }
}

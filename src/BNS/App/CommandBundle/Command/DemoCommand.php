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
            ->addOption('groupId', null, InputOption::VALUE_REQUIRED, 'School ID')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language', 'fr')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Country', null)
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
        $school = GroupQuery::create()->findOneById($input->getOption('groupId'));

        if(!$school)
        {
           throw new \Exception('Please set a valid group id');
        }

        if($school->getGroupType()->getType() != 'SCHOOL')
        {
            throw new \Exception('Please set a school');
        }

        $this->updateSchoolInformations($school);
        //Création de la classe
        $this->createClassroomDatas($school);
        $this->addSchoolVersion($school);
        $this->addSchoolApps($school);
        $this->createMiniSite($school);
        $this->createBlog($school);
        $this->createAgendaEvents($school);
        $this->createLunches($school);
        $this->output->writeln('<info>All done !</info>');
    }

    protected function addSchoolApps($school)
    {
        $applicationManager = $this->getContainer()->get('bns_core.application_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $apps = ['MINISITE', 'BLOG', 'LUNCH', 'CALENDAR'];
        foreach($apps as $app) {
            $paasManager->generateSubscription($school, $app, 'unlimited', null, null, $this->lang);

            try {
                // optionnal should be done by the subscription
                $applicationManager->installApplication($app, $school);
            } catch (InvalidInstallApplication $e) {
                $this->output->writeln(sprintf('<info>Try to install application "%s" failed : %s' , $app, $e->getMessage()));
            }
        }
        $this->output->writeln('<info>School apps installed</info>');
    }

    protected function addSchoolVersion($school)
    {
        $userManager = $this->getContainer()->get('bns.user_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $groupManager = $this->getContainer()->get('bns.group_manager');
        $groupManager->setGroup($school);

        $ref = $groupManager->getUsersByRoleUniqueName('TEACHER');

        $user = UserQuery::create()
            ->findPk($ref[0]['id']);

        $paasManager->generateSubscription($school, PaasManager::PREMIUM_SUBSCRIPTION, 'unlimited', $user->getUsername(), null, $this->lang);
        $this->output->writeln('<info>School subscription activated with :</info>');

        $userManager->resetUserPassword($user, false);
        $this->output->writeln($user->getUsername(). ' / ' . $user->getPassword());

    }

    protected function addClassroomApps($classroom)
    {
        $applicationManager = $this->getContainer()->get('bns_core.application_manager');
        $paasManager = $this->getContainer()->get('bns.paas_manager');
        $apps = ['HOMEWORK', 'BLOG', 'CALENDAR', 'LIAISONBOOK', 'GPS'];
        foreach($apps as $app) {
            $paasManager->generateSubscription($classroom, $app, 'unlimited', null, null, $this->lang);

            try {
                // optionnal should be done by the subscription
                $applicationManager->installApplication($app, $classroom);
            } catch (InvalidInstallApplication $e) {
                $this->output->writeln(sprintf('<info>Try to install application "%s" failed : %s' , $app, $e->getMessage()));
            }
        }
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
        $gm->setGroup($school);

        $classroomParams = array(
            'label' => $this->getRandomizedData('classroom','name'),
            'type'  => 'CLASSROOM'
        );

        $classroom = $gm->createSubgroupForGroup($classroomParams, $school->getId());
        $classroom->setAttribute('HOME_MESSAGE',$this->getRandomizedData('classroom','home_message'));
        $classroom->validateStatus();
        $classroom->setCountry($this->country);
        $classroom->setLang($this->fullLang);
        $classroom->save();

        //création des élèves
        $pupilCount = 0;

        while($pupilCount < 25)
        {
            $this->createUserAndProfileDatas('pupil', $classroom);
            $pupilCount++;
            $this->output->writeln(sprintf('<info>%s</info> pupils created', $pupilCount));
        }

        $teacherCount = 0;

        while($teacherCount < 2)
        {
            $this->createUserAndProfileDatas('teacher', $classroom);
            $teacherCount++;
            $this->output->writeln(sprintf('<info>%s</info> teachers created', $teacherCount));
        }

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

        if($type == 'teacher')
        {
            $values['email'] = strtolower($array['first_name'] . '.' . $array['last_name'] . rand(1,9999) . '@demo.beneylu.com');
        }

        $user = $um->createUser($values, false);
        $cm->setClassroom($classroom);
        /** @var User $pupil */
        if($type == 'pupil')
        {
            $cm->assignPupil($user, true);
        }elseif($type == 'teacher')
        {
            $cm->assignTeacher($user);
        }

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
    }

    protected function createAgendaEvents(Group $group)
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
            $datas = $this->getRandomizedData('agenda', 'event');

            if(!in_array(strtolower(date('l',time() + 3600 * 24 * ($eventCount + 1))),array('sunday', 'saturday')))
            {
                $begin = mktime($datas['start'],0,0,date('m'),date('d') + ($eventCount + 1),date('Y'));
                $end = mktime($datas['end'],0,0,date('m'),date('d') + ($eventCount + 1),date('Y'));

                $eventInfos = array(
                    'dtstart' => $begin,
                    'dtend' => $end,
                    'summary' => $datas['title']
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

        $homeworkCount = 0;
        while($homeworkCount < 15)
        {
            $datas = $this->getRandomizedData('homework', 'homework');
            $subject = new HomeworkSubject();
            $rootSubject = HomeworkSubject::fetchRoot($group->getId());
            $subject->insertAsFirstChildOf($rootSubject);
            $subject->setGroupId($group->getId());
            $subject->setName($datas['subject']);
            $subject->save();

            foreach($datas['homeworks'] as $homework)
            {
                $newHomework = new Homework();
                $newHomework->setName($homework["title"]);
                $newHomework->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
                $newHomework->setSubjectId($subject->getId());
                $newHomework->setDescription($homework["content"]);
                $newHomework->addGroup($group);
                if(isset($homework['help']))
                {
                    $newHomework->setHelptext($homework['help']);
                }
                $date = time() + rand(1,60) * 3600 * 24;
                if(!in_array(strtolower(date('l', $date)),array('sunday', 'saturday')))
                {
                    $newHomework->setDate($date);
                } else {
                    $newHomework->setDate($date + 3600 * 48);
                }

                $homeworkManager->processHomework($newHomework);
                $newHomework->save();

                if(isset($homework['media']))
                {
                    $media = $this->createMedia($teacher, '/homework/' . $homework['media'], $group->getMediaFolderRoot());
                    $newHomework->addResourceAttachment($media->getId());
                }

            }
            $homeworkCount++;
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

    protected function createBlog(Group $group)
    {
        $blog = $group->getBlog();
        $articleCount = 0;
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $pupilsIds = $gm->getUsersByRoleUniqueNameIds('PUPIL');
        $teacher = $gm->getUsersByRoleUniqueName('TEACHER', true)->getFirst();

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
            $pupilId = UserQuery::create()->filterById($pupilsIds)->addAscendingOrderByColumn("rand()")->findOne()->getId();
            $article = new BlogArticle();
            $datas = $this->getRandomizedData('blog', 'blog');
            $article->setTitle($datas['title']);
            $article->setContent($datas['content']);
            $article->setStatus(BlogArticlePeer::STATUS_PUBLISHED);
            $article->setBlogReferenceId($blog->getId());
            $article->setAuthorId($pupilId);
            $article->setIsCommentAllowed(true);
            $article->setPublishedAt(time());
            $article->setCreatedAt(time());
            $article->save(null, true);

            if(isset($datas['images']))
            {
                foreach($datas['images'] as $image)
                {
                    $media = $this->createMedia($teacher, '/blog/' . $image, $group->getMediaFolderRoot());
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

    protected function createMiniSite(Group $group)
    {
        $gm = $this->getContainer()->get('bns.group_manager');
        $gm->setGroup($group);
        $author = UserQuery::create()->filterById($gm->getUsersByRoleUniqueNameIds('TEACHER'))->addAscendingOrderByColumn("rand()")->findOne();

        $data = $this->getRandomizedData('minisite','minisite');

        /** @var MiniSite $miniSite */
        $miniSite = MiniSiteQuery::create()->filterByGroup($group)->findOneOrCreate();
        $banner = $this->createMedia($author, '/minisite/' . $data['banner'] ,$group->getMediaFolderRoot());
        $miniSite->setBannerResourceId($banner->getId());
        $miniSite->setDescription($data['presentation']);
        $miniSite->save();

        $this->createMiniSitePage($miniSite, $data['home'], $author, true);
        $this->createMiniSitePage($miniSite, $data['actu'], $author);
        foreach($data['pages'] as $page)
        {
            $this->createMiniSitePage($miniSite, $page, $author);
        }
        $this->output->writeln('<info>Minisite created</info>');
    }

    public function createMiniSitePage(MiniSite $miniSite, $data, $author, $isHome = false)
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
                    $media = $this->createMedia($author, '/minisite/' . $image, $miniSite->getGroup()->getMediaFolderRoot());
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

        while($lunchCount < 1) {
            $data = $this->getRandomizedData('lunch', 'lunch');
            $week = new LunchWeek();
            $week->setGroupId($group->getId());
            $week->setSections(array('full_menu', 'starter', 'main_course', 'dessert', 'dairy', 'accompaniment', 'afternoon_snack'));
            $week->setLabel($data['label']);
            $week->setDescription($data['description']);
            $week->setDateStart(strtotime("last Monday"));
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

            $lunchCount++;
        }

        $this->output->writeln('<info>Lunches created</info>');
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
}

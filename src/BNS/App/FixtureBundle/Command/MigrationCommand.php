<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BNS\App\FixtureBundle\Command;

use BNS\App\CoreBundle\Model\GroupTypeQuery;

use BNS\App\CoreBundle\Group\BNSGroupManager;

use Gaufrette\Filesystem;

use Gaufrette\Adapter\Local;

//Chargement des écoles TEST

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\BlogArticleComment;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\LiaisonBookSignature;
use BNS\App\CoreBundle\Model\ProfilePreference;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\FixtureBundle\Model\Migration;
use BNS\App\FixtureBundle\Model\MigrationQuery;
use BNS\App\GPSBundle\Model\GpsCategory;
use BNS\App\GPSBundle\Model\GpsPlace;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\RegistrationBundle\Model\SchoolInformation;
use BNS\App\RegistrationBundle\Model\SchoolInformationPeer;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\ResourceBundle\Model\ResourceQuery;

use Criteria;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Propel;
use PropelPDO;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Migration V2 => V3
 */

/*
 *
UPDATE `user`
set `high_role_id` = 9
WHERE  `login` LIKE  '%parents' OR `login` LIKE  '%parent'

UPDATE `user`
set `high_role_id` = 8
WHERE (`high_role_id` NOT LIKE 9 AND `high_role_id` NOT LIKE 7 AND `high_role_id` NOT LIKE 6)

 */

class MigrationCommand extends ContainerAwareCommand
{
    /**
     * @var PropelPDO MySQL connexion
     */
    protected $con;
    protected $test = false;
    protected $already = array();
    protected $newObjects = array();
    protected $olds = array();
    protected $oldResourceLabels = array();

    protected $environment;

    protected function configure()
    {
        $this->setName('bns:migration')
            ->setDescription('V2 / V3 migration')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
            ->addOption('test', "test", InputOption::VALUE_OPTIONAL, 'En test')
            ->addOption('begin', "begin", InputOption::VALUE_OPTIONAL, 'Debut du set de données')
            ->addOption('end', "end", InputOption::VALUE_OPTIONAL, 'Fin du set de données')
            ->addOption('par', "par", InputOption::VALUE_OPTIONAL, 'A paraléliser ?')
            ->addOption('size', "size", InputOption::VALUE_OPTIONAL, 'Taille des morceaux')
            ->addOption('fileNb', "fileNb", InputOption::VALUE_OPTIONAL, 'Num du ficher')
            ->addOption('bnsEnv', null, InputOption::VALUE_OPTIONAL, "nom de l'environnement (www.beneyluschool.net)")
            ->addOption('fileFolder', null, InputOption::VALUE_OPTIONAL, "chemin vers dossier qui contient les anciens fichiers")
            ->addArgument("step");
    }

    protected function isAlreadyDone($objectClass, $oldId)
    {
        if (!isset($this->already[$objectClass][$oldId])) {
            if (MigrationQuery::create()->filterByOldId($oldId)->filterByObjectClass($objectClass)->filterByEnvironment($this->environment)->count() > 0) {
                $this->already[$objectClass][$oldId] = true;
            } else {
                $this->already[$objectClass][$oldId] = false;
            }
        }
        return $this->already[$objectClass][$oldId];
    }

    protected function getFromOldId($objectClass, $oldId)
    {
        if (!isset($this->olds[$objectClass][$oldId])) {
            $rez = MigrationQuery::create()->filterByOldId($oldId)->filterByObjectClass($objectClass)->filterByEnvironment($this->environment)->findOne();
            if (!$rez) {
                $this->olds[$objectClass][$oldId] = false;
            } else {
                $this->olds[$objectClass][$oldId] = $rez;
            }
        }
        return $this->olds[$objectClass][$oldId];
    }

    protected function getQueryFromObjectName($objectClass)
    {
        switch ($objectClass) {
        case "teacher":
        case "user":
        case "pupil_app":
        case "parent_app":
        case "parent_auth":
        case "pupil_auth":
        case "pupil":
        case "parent":
            return UserQuery::create();

        case "classroom":
        case "school":
            return GroupQuery::create();

        case "blog_post":
            return BlogArticleQuery::create();

        case "blog":
            return BlogQuery::create();

        case "blog_category":
            return BlogCategoryQuery::create();

        case "blog_comment":
            return \BNS\App\CoreBundle\Model\BlogArticleComment::create();

        case "message":
            return MessagingMessageQuery::create();

        case 'event':
            return AgendaEvent::create();

        case "correspondance_message":
            return LiaisonBookQuery::create();

        case "gps_place":
            return \BNS\App\GPSBundle\Model\GpsPlaceQuery::create();

        case "gps_category":
            return \BNS\App\GPSBundle\Model\GpsCategoryQuery::create();

        case "homework":
            return \BNS\App\HomeworkBundle\Model\HomeworkQuery::create();

        case "aws_file":
        case "annotation":
            return ResourceQuery::create();
        case "search_annotation_category":
            return ResourceLabelGroup::create();
        }
    }

    protected Function getNewObjectFromOldId($objectClass, $oldId)
    {
        if (!isset($this->newObjects[$objectClass][$oldId])) {
            $migration = $this->getFromOldId($objectClass, $oldId);
            if ($migration) {
                $query = $this->getQueryFromObjectName($objectClass);
                $this->newObjects[$objectClass][$oldId] = $query->findOneById($migration->getNewId());
            } else {
                return false;
            }
        }

        return $this->newObjects[$objectClass][$oldId];
    }

    protected function saveInMigration($oldId, $newId, $objectClass, $datas = null)
    {
        $migration = new Migration();
        $migration->setOldId($oldId);
        $migration->setNewId($newId);
        $migration->setObjectClass($objectClass);
        $migration->setEnvironment($this->environment);
        if (is_array($datas)) {
            $migration->setDatas(serialize($datas));
        }
        $migration->save();
    }

    /**
     *
     * @param InputInterface $input
     * @param array $array
     * @return array
     */
    protected function initOffset($input, $array)
    {
        if (null === $input->getOption('begin') && null === $input->getOption('end')) {
            return $array;
        }

        //Filtrage des données
        $begin = $input->getOption('begin') - 1;
        $end = $input->getOption('end');

        if (!$begin && !$end) {
            die('donner valeurs de debut et de fin');
        }
        $count = $end - $begin;
        //Fin filtrage des données
        return array_slice($array, $begin, $count);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->environment = $input->getOption('bnsEnv')?: $this->getContainer()->getParameter('application_environment');
        $buzz = $this->getContainer()->get('buzz');
        $buzz->setMaxTries(10);
        $curl = $buzz->getClient();
        $curl->setTimeout(60);

        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $this->con = Propel::getConnection($connectionName);
        Propel::setForceMasterConnection(true);
        ini_set("memory_limit", "6000M");

        //C'est parti

        BNSAccess::setContainer($this->getContainer());

        $args = $input->getArguments();
        $opts = $input->getOptions();

        if (isset($opts['par'])) {
            //On paralélise
            $env = $opts['env'];
            ;
            $size = $opts['size'];
            $end = $opts['end'];
            $begin = $opts['begin'];

            $todo = $end - $begin;
            $number = round($todo / $size) + 1;

            $more = $input->hasOption('fileNb') ? "--fileNb=" . $input->getOption('fileNb') : "";

            for ($counter = 0; $counter < $number; $counter++) {
                $n_begin = $counter * $size + $begin;
                $n_end = ($counter + 1) * $size + $begin;
                echo "php app/console bns:migration --env=" . $env . " " . $args["step"] . " --begin=" . $n_begin . " --end=" . $n_end . " " . $more . "> tmp &";
                exec("php app/console bns:migration --env=" . $env . " " . $args["step"] . " --begin=" . $n_begin . " --end=" . $n_end . " " . $more . "> tmp &");
                sleep(60);
            }
            return false;
        }

        if ($opts['test'] == "test") {
            $this->test = true;
        }

        switch ($args["step"]) {
            case "schools":
                $this->loadSchools($input, $output);
                break;
            case "classrooms":
                $this->loadClassrooms($input, $output);
                break;
            case "teachers":
                $this->loadTeachers($input, $output);
                break;
            case "pupils":
                $this->loadPupils($input, $output);
                break;
            case "parents":
                $this->loadParents($input, $output);
                break;
            case "blogCategories":
                $this->loadBlogPostCategories($input, $output);
                break;
            case "blogPosts":
                $this->loadBlogPosts($input, $output);
                break;
            case "events":
                $this->loadEvent($input, $output);
                break;
            case "correspondanceMessages":
                $this->loadCorrespondenceMessage($input, $output);
                break;
            case "messages":
                $this->loadMessages($input, $output);
                break;
            case "gps":
                $this->loadGps($input, $output);
                break;
            case "homeworks":
                $this->loadHomeworks($input, $output);
                break;
            case "blogComments":
                $this->loadBlogComments($input, $output);
                break;
            case "awsFolders":
            case "folders":
                $this->loadAwsFolders($input, $output);
                break;
            case "files":
                $this->loadAwsFiles($input, $output);
                break;
            case 'avatars':
                $this->loadAvatars($input, $output);
                break;
            case 'directors':
                $this->loadDirectors($input, $output);
                break;
            case 'messageFiles':
                $this->loadMessageFiles($input, $output);
                break;
            case 'searchAnnotations':
                $this->loadSearchAnnotations($input, $output);
                break;
        }

        return 0;
    }

    public function getLogger($name)
    {
        // create a log channel
        $logger = new Logger('migration');
        $logger->pushHandler(new StreamHandler($this->getContainer()->get('kernel')->getRootDir() . '/logs/' . $name . '_' . date('d_m_Y') . '_' . '.log', Logger::INFO));

        return $logger;
    }

    /**
     * Step 1
     * Chargement des écoles depuis schools vers school_informations
     */
    public static $countriesCultures = array(
            5  => 'DE',
            12 => 'SA',
            16 => 'AU',
            25 => 'BE',
            34 => 'BR',
            42 => 'CA',
            43 => 'CV',
            45 => 'CN',
            47 => 'CO',
            49 => 'CG',
            53 => 'HR',
            56 => 'DJ',
            58 => 'EG',
            59 => 'AE',
            60 => 'EC',
            62 => 'ES',
            64 => 'US',
            66 => 'FI',
            67 => 'FR',
            78 => 'GT',
            79 => 'GN',
            83 => 'GF',
            107 => 'IN',
            108 => 'ID',
            113 => 'IL',
            114 => 'IT',
            126 => 'LB',
            128 => 'LY',
            131 => 'LU',
            133 => 'MG',
            137 => 'ML',
            140 => 'MA',
            143 => 'MU',
            145 => 'MX',
            152 => 'NP',
            156 => 'NE',
            157 => 'NG',
            160 => 'NC',
            169 => 'PY',
            170 => 'NL',
            175 => 'PT',
            180 => 'CZ',
            183 => 'GB',
            184 => 'RU',
            186 => 'SN',
            201 => 'SG',
            202 => 'SI',
            208 => 'CH',
            224 => 'TN',
            226 => 'TR',
            228 => 'UA',
            229 => 'UY',
            232 => 'VN'
            );

    public static $classroomLevels = array(
            1 => 'CP',
            2 => 'CE1',
            3 => 'CE2',
            4 => 'CM1',
            5 => 'CM2',
            6 => 'PS',
            7 => 'MS',
            8 => 'GS',
            9 => 'CLIS', // Toulouse specification
//             9 => '6ème',
//             10 => '5ème',
//             11 => '4ème',
//             12 => '3ème',
//             13 => 'CLIS',
//             14 => 'SEC',
//             5 => 'PREM',
//             16 => 'TERM'
            );

    //OK à ne plus toucher

    public function loadSchools(InputInterface $input, OutputInterface $output)
    {

        $output->writeln("Creation des ecoles statiques");

        include __DIR__ . '/../Resources/data/Migration/school.php';

        $nbSchools = 1;

        $logger = $this->getLogger('schools');

        $countSchools = count($school);

        $output->writeln("Création des $countSchools écoles dans  le fichier");

        foreach ($school as $school_new) {

            $output->writeln("Creation n " . $nbSchools);

            //Check existence

            if (isset($school_new['name']) && isset($school_new['country_id']) && isset($school_new['city']) && isset($school_new['id'])) {

                if (!SchoolInformationQuery::create()->findPk($school_new['id'])) {

                    $newSchool = new SchoolInformation();
                    $newSchool->setId($school_new['id']);
                    $newSchool->setName($school_new['name']);
                    $newSchool->setCity($school_new['city']);
                    $newSchool->setCountry(self::$countriesCultures[$school_new['country_id']]);

                    if (isset($school_new['address']))
                        $newSchool->setAddress($school_new['address']);

                    if (isset($school_new['roll_number']))
                        $newSchool->setUai($school_new['roll_number']);

                    if (isset($school_new['zip_code']))
                        $newSchool->setZipCode($school_new['zip_code']);

                    if (isset($school_new['phone_number']))
                        $newSchool->setPhoneNumber($school_new['phone_number']);

                    if (isset($school_new['fax_number']))
                        $newSchool->setFaxNumber($school_new['fax_number']);

                    if (isset($school_new['email']))
                        $newSchool->setEmail($school_new['email']);
                    $newSchool->setStatus(SchoolInformationPeer::STATUS_VALIDATED);
                    $newSchool->save();
                    unset($school_new, $newSchool);

                } else {
                    $logger->info("Ecole " . $school_new['id'] . " n'a pas pu être importée, elle existe deja.");
                }

            } else {
                $logger->info("Ecole " . $school_new['id'] . " n'a pas pu être importée;");
            }
            $nbSchools++;
        }
        unset($school);
    }

    /*
     *
     OLD QUERIES

    SELECT *
    FROM  `classroom`
    WHERE (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")

    SELECT  `level_id` ,  `classroom_id`
    FROM  `classroom_level`
    JOIN classroom
    WHERE classroom.id = classroom_level.classroom_id
    AND (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")


    --------------------- NEW QUERIES

    SELECT *
    FROM  `classroom`
    WHERE (classroom.status >= -1)

    SELECT  `level_id` ,  `classroom_id`
    FROM  `classroom_level`
    JOIN classroom
    WHERE classroom.id = classroom_level.classroom_id
    AND (classroom.status >= -1)
     */

    public function loadClassrooms(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger('classrooms');
        //Inclusion des données
        include __DIR__ . '/../Resources/data/Migration/classroom.php';
        include __DIR__ . '/../Resources/data/Migration/classroom_level.php';

        //Parsage des niveaux
        $classroomsLevels = array();
        foreach ($classroom_level as $row) {
            $classroomsLevels[$row['classroom_id']][] = $row['level_id'];
            unset($row);
        }
        unset($classroom_level);
        //Fin parsage des niveaux

        $sliced = $this->initOffset($input, $classroom);
        $count = count($sliced);

        $nbClassroomsDone = 0;

        foreach ($sliced as $row) {

            if (isset($row['name']) && isset($row['id']) && isset($row['status']) && isset($row['school_id'])) {
                if (!$this->isAlreadyDone('classroom', $row['id'])) {

                    //Recupération OU creation de l'école
                    $schoolId = $row['school_id'];

                    $schoolInfo = SchoolInformationQuery::create('si')->joinWith('Group', Criteria::LEFT_JOIN)->where('si.Id = ?', $schoolId)->findOne();

                    if ($schoolInfo) {
                        // Creating school if not exists
                        if (null == $schoolInfo->getGroupId()) {

                            $school = $this->getContainer()->get('bns.classroom_manager')->createSchoolFromInformation($schoolInfo, $this->environment);
                            $newSchoolId = $school->getId();

                        } else {
                            $newSchoolId = $schoolInfo->getGroupId();

                        }

                        $newClassroom = $this->getContainer()->get('bns.classroom_manager')->createClassroom(array('label' => $row['name'], 'validated' => in_array($row['status'], array(0, 1)), 'group_parent_id' => $newSchoolId));

                        $myClassroomsDatas = array('school_id' => $row['school_id'], 'classroom_config_id' => @$row['classroom_config_id'], 'mediatheque_info_id' => @$row['mediatheque_info_id'], 'new_school_id' => $newSchoolId);

                        $this->saveInMigration($row['id'], $newClassroom->getId(), 'classroom', $myClassroomsDatas);

                        $myClassroomsLevels = array();

                        if (isset($classroomsLevels[$row['id']])) {
                            foreach ($classroomsLevels[$row['id']] as $levelId) {
                                $myClassroomsLevels[] = self::$classroomLevels[$levelId];
                            }
                        }

                        $newClassroom->setAttribute('LEVEL', $myClassroomsLevels);

                        unset($schoolInfo, $newClassroom, $myClassroomsLevels, $myClassroomsDatas, $school);

                        $output->writeln("Classes importees : " . $nbClassroomsDone . ' / ' . $count);

                    } else {
                        $logger->info("Classe " . @$row['id'] . " non importée, Ecole " . $row['school_id'] . " inexistante ");
                    }
                } else {
                    $logger->info("Classe " . @$row['id'] . " non importée, Déjà créée");
                }
            } else {
                $logger->info("Classe " . @$row['id'] . " non importée, données manquantes");
            }
            $nbClassroomsDone++;
        }
    }

    /*

     -------------- OLD QUERY ---------------
        SELECT *
        FROM  `sf_guard_teacher_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_teacher_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_teacher_profile.classroom_id = classroom.id
        AND (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")

        -------------- NEW QUERY ---------------
        SELECT *
        FROM  `sf_guard_teacher_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_teacher_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_teacher_profile.classroom_id = classroom.id
        AND (classroom.status >= -1)
     */

    public function loadTeachers(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger('users');

        include __DIR__ . '/../Resources/data/Migration/sf_guard_teacher_profile.php';

        //Chargement des love or not
        include __DIR__ . '/../Resources/data/Migration/love_or_not.php';
        $loveOrNots = array();
        foreach ($love_or_not as $row) {
            $loveOrNots[$row['user_id']][] = array('what' => $row['label'], 'bool' => $row['love']);
            unset($row);
        }

        $sliced = $this->initOffset($input, $sf_guard_teacher_profile);
        $count = count($sliced);
        $nbTeachersDone = 0;

        foreach ($sliced as $row) {

            $output->writeln("Enseignants importes : " . $nbTeachersDone . ' / ' . $count);

            if (isset($row['user_id']) && isset($row['classroom_id']) && isset($row['first_name']) && isset($row['last_name']) && isset($row['gender']) && isset($row['email']) && isset($row['username']) && isset($row['salt']) && isset($row['password']) && isset($row['is_active'])) {
                if (!$this->isAlreadyDone('teacher', $row['user_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();

                    $newTeacher = $this->getContainer()->get('bns.user_manager')
                            ->createUser(
                                    array('first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'gender' => $row['gender'], 'lang' => 'fr', 'email' => $row['email'], 'salt' => $row['salt'], 'password' => $row['password'],
                                            'birthday' => isset($row['birthday']) ? $row['birthday'] : null, 'username' => $row['username']), false);

                    //MAJ de l'objet Profil
                    $profile = $newTeacher->getProfile();
                    $profile->setJob($row['wanted_job']);
                    $profile->setDescription($row['hobbies']);
                    $profile->save();

                    //MAJ des j'aimes / je n'aime pas
                    if (isset($loveOrNots[$row['user_id']])) {
                        foreach ($loveOrNots[$row['user_id']] as $row2) {
                            $pref = new ProfilePreference();
                            $pref->setProfileId($profile->getId());
                            $pref->setItem($row2['what']);
                            $pref->setIsLike($row2['bool']);
                            $pref->save();
                            unset($pref);
                        }
                    }

                    $classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
                    $this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
                    $this->getContainer()->get('bns.classroom_manager')->assignTeacher($newTeacher);

                    $myTeacherDatas = array('private_folder_id' => $row['private_folder_id']);

                    $this->saveInMigration($row['user_id'], $newTeacher->getId(), 'teacher', $myTeacherDatas);

                    unset($row, $myTeacherDatas, $classroom, $profile);

                } else {
                    $logger->info("Enseignant " . @$row['username'] . " non importée, classe non importée");
                }
            } else {
                $logger->info("Enseignant " . @$row['username'] . " non importée, données manquantes");
            }
            $nbTeachersDone++;
        }
    }

    /*
     -------------- OLD QUERY ------------------
        SELECT *
        FROM  `sf_guard_pupil_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_pupil_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_pupil_profile.classroom_id = classroom.id
        AND (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")

        -------------- NEW QUERY ------------------
        SELECT *
        FROM  `sf_guard_pupil_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_pupil_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_pupil_profile.classroom_id = classroom.id
        AND (classroom.status >= -1)
     */
    public function loadPupils(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger('pupils');
        $more = $input->getOption('fileNb') ? "_" . $input->getOption('fileNb') : "";

        include __DIR__ . '/../Resources/data/Migration/sf_guard_pupil_profile' . $more . '.php';

        //Chargement des love or not
        include __DIR__ . '/../Resources/data/Migration/love_or_not.php';
        $loveOrNots = array();
        foreach ($love_or_not as $row) {
            $loveOrNots[$row['user_id']][] = array('what' => $row['label'], 'bool' => $row['love']);
            unset($row);
        }

        $sliced = $this->initOffset($input, $sf_guard_pupil_profile);
        $count = count($sliced);
        $nbPupilsDone = 0;

        foreach ($sliced as $row) {
            $nbPupilsDone++;
            $output->writeln("Eleves importes : " . $nbPupilsDone . ' / ' . $count);
            if (isset($row['user_id']) && isset($row['classroom_id'])
                    && isset($row['first_name']) && isset($row['last_name'])
                    && isset($row['gender'])
                    && isset($row['username']) && isset($row['is_active'])) {
                if (!$this->isAlreadyDone('pupil', $row['user_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();

                    $newPupil = $this->getContainer()->get('bns.user_manager')
                    ->createUser(
                            array('first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'gender' => $row['gender'], 'lang' => 'fr', 'email' => null, 'salt' => $row['salt'], 'password' => $row['password'],
                                    'birthday' => isset($row['birthday']) ? $row['birthday'] : null, 'username' => $row['username']), false);

                    //MAJ de l'objet Profil
                    $profile = $newPupil->getProfile();
                    $profile->setJob($row['wanted_job']);
                    $profile->setDescription($row['hobbies']);
                    $profile->save();

                    //MAJ des j'aimes / je n'aime pas
                    if (isset($loveOrNots[$row['user_id']])) {
                        foreach ($loveOrNots[$row['user_id']] as $row2) {
                            $pref = new ProfilePreference();
                            $pref->setProfileId($profile->getId());
                            $pref->setItem($row2['what']);
                            $pref->setIsLike($row2['bool']);
                            $pref->save();
                            unset($pref);
                        }
                    }

                    $classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
                    $this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
                    $this->getContainer()->get('bns.classroom_manager')->assignPupil($newPupil, false);

                    $myPupilDatas = array('private_folder_id' => $row['private_folder_id']);

                    $this->saveInMigration($row['user_id'], $newPupil->getId(), 'pupil', $myPupilDatas);

                    unset($row, $myPupilDatas, $classroom, $profile);

                } else {
                    $logger->info("Eleve " . @$row['username'] . " non importée, classe non importée");
                }
            } else {
                $logger->info("Eleve " . @$row['username'] . " non importée, données manquantes");
            }
        }
    }

    /** Old func */
    public function oldsloadPupils(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger('pupils');
        $more = $input->getOption('fileNb') ? "_" . $input->getOption('fileNb') : "";

        include __DIR__ . '/../Resources/data/Migration/sf_guard_pupil_profile' . $more . '.php';

        //Chargement des love or not
        include __DIR__ . '/../Resources/data/Migration/love_or_not.php';
        $loveOrNots = array();
        foreach ($love_or_not as $row) {
            $loveOrNots[$row['user_id']][] = array('what' => $row['label'], 'bool' => $row['love']);
            unset($row);
        }

        $sliced = $this->initOffset($input, $sf_guard_pupil_profile);
        $count = count($sliced);
        $nbPupilsDone = 0;

        foreach ($sliced as $row) {
            $output->writeln("Eleves importes : " . $nbPupilsDone . ' / ' . $count);
            if (isset($row['user_id']) && isset($row['classroom_id'])
                    && isset($row['first_name']) && isset($row['last_name'])
                    && isset($row['gender']) && isset($row['username'])
                    && isset($row['is_active'])) {
                if (!$this->isAlreadyDone('pupil_app', $row['user_id'])) {

                    if (!$this->isAlreadyDone('pupil_auth', $row['user_id'])) {
                        if ($this->isAlreadyDone('classroom', $row['classroom_id'])) {

                            $newUserId = $this->getFromOldId('pupil_auth', $row['user_id'])->getNewId();
                            try {
                                $newPupil = UserPeer::createUser(
                                        array('user_id' => $newUserId, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'gender' => $row['gender'], 'lang' => 'fr', 'birthday' => isset($row['birthday']) ? $row['birthday'] : null, 'username' => $row['username']));
                            } catch (\Exception $e) {
                                $newPupil = UserQuery::create()->findOneById($newUserId);
                            }
                            //MAJ de l'objet Profil
                            $profile = $newPupil->getProfile();
                            $profile->setJob($row['wanted_job']);
                            $profile->setDescription($row['hobbies']);
                            $profile->save();
                            //MAJ des j'aimes / je n'aime pas
                            if (isset($loveOrNots[$row['user_id']])) {
                                foreach ($loveOrNots[$row['user_id']] as $row2) {
                                    $pref = new ProfilePreference();
                                    $pref->setProfileId($profile->getId());
                                    $pref->setItem($row2['what']);
                                    $pref->setIsLike($row2['bool']);
                                    $pref->save();
                                    unset($pref);
                                }
                            }

                            $myPupilDatas = array('private_folder_id' => $row['private_folder_id']);

                            $this->saveInMigration($row['user_id'], $newPupil->getId(), 'pupil_app', $myPupilDatas);

                            unset($row, $myPupilDatas, $profile);
                        } else {
                            $logger->info('no classroom', $row);
                        }
                    } else {
                        $logger->info('not_donein_auth', $row);
                    }
                }
            } else {
                $logger->info('not_enough', $row);
            }
            $nbPupilsDone++;
        }
    }

    //Fin Ok à ne plus toucher

    /*
     ---------------- OLD QUERY ------------------
    SELECT *
        FROM  `sf_guard_parent_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_parent_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_parent_profile.classroom_id = classroom.id
        AND (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")


         ---------------- NEW QUERY ------------------
    SELECT *
        FROM  `sf_guard_parent_profile`
        JOIN sf_guard_user
        JOIN classroom
        ON sf_guard_parent_profile.user_id = sf_guard_user.id
        WHERE sf_guard_user.is_active >=0
        AND sf_guard_parent_profile.classroom_id = classroom.id
        AND (classroom.status >= -1)
     */

    public function loadParents(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger('parents');
        $more = $input->getOption('fileNb') ? "_" . $input->getOption('fileNb') : "";

        include __DIR__ . '/../Resources/data/Migration/sf_guard_parent_profile' . $more . '.php';

        $sliced = $this->initOffset($input, $sf_guard_parent_profile);
        $count = count($sliced);
        $nbPupilsDone = 0;

        foreach ($sliced as $row) {
            $output->writeln("Parents importes : " . $nbPupilsDone . ' / ' . $count);


            if (isset($row['user_id']) && isset($row['classroom_id'])
                    && isset($row['username']) && isset($row['is_active'])) {
                if (!$this->isAlreadyDone('parent', $row['user_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();

                    $migration = $this->getFromOldId('pupil', $row['child_id']);

                    //Link parent // enfant
                    $child = UserQuery::create()->findPk($migration ? $migration->getNewId(): null);
                    if ($child) {

                        $newParent = $this->getContainer()->get('bns.user_manager')->createUser(
                                array('first_name' => "Parents de", 'last_name' => $child->getFirstName() . ' ' . $child->getLastName(), 'gender' => 'M', 'lang' => 'fr', 'email' => null, 'salt' => $row['salt'], 'password' => $row['password'],
                                        'birthday' => isset($row['birthday']) ? $row['birthday'] : null, 'username' => $row['username']), false);


                        $classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
                        $this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
                        $this->getContainer()->get('bns.classroom_manager')->assignParent($newParent, false);
                        $this->getContainer()->get('bns.classroom_manager')->linkPupilWithParent($child, $newParent);


                        $this->saveInMigration($row['user_id'], $newParent->getId(), 'parent');

                        unset($row, $myParentDatas, $classroom, $profile);
                    }

                } else {
                    $logger->info("Parent " . @$row['username'] . " non importée, classe non importée");
                }
            } else {
                $logger->info("Parent " . @$row['username'] . " non importée, données manquantes");
            }
            $nbPupilsDone++;
        }
    }

    public function loadBlogPostCategories(InputInterface $input, OutputInterface $output)
    {
        $nb = 0;
        include __DIR__ . '/../Resources/data/Migration/blog_post_category.php';
        foreach ($blog_post_category as $row) {
            $output->writeln("Creation n " . $nb);
            if (isset($row['id']) && isset($row['title']) && isset($row['classroom_id'])) {
                if (!$this->isAlreadyDone('blog_category', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newCategory = new BlogCategory();
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();

                    $blog = BlogQuery::create()->filterByGroupId($newClassroomId)->findOne();
                    $root = BlogCategoryQuery::create()->filterByBlogId($blog->getId())->filterByLevel(0)->findOne();
                    if ($root) {
                        $newCategory->setBlogId($blog->getId());
                        $newCategory->setTitle($row['title']);
                        $newCategory->save();

                        $newCategory->insertAsLastChildOf($root);
                        $newCategory->save();

                        $this->saveInMigration($row['id'], $newCategory->getId(), 'blog_category', array());

                        unset($cat, $newCategory, $root, $blog, $row);
                    }
                }
            }
            $nb++;
        }
    }

    //Export des articles du blog
    /**
     *
    ----------- OLD QUERY ----------------
    SELECT blog_post.`id` ,  `user_id` ,  `classroom_id` ,  `blog_post_category_id` ,  `title` ,  `subtitle` ,  `content` , blog_post.`status` , blog_post.`created_at` , blog_post.`published_at`, blog_post.allow_comments
    FROM  `blog_post`
    JOIN classroom
    ON classroom.id = blog_post.classroom_id
    WHERE (classroom.status >= -1 OR classroom.created_at > "2012-09-01 00:00:00")
    ORDER BY blog_post.`id`

    ----------- NEW QUERY ----------------
    SELECT p.*
    FROM  `blog_post` p
    JOIN classroom
    ON classroom.id = p.classroom_id
    WHERE (classroom.status >= -1)
    ORDER BY p.`id`

    //pour les commentaires

    Extract brut

     *
     *
     *
     * 	 */

    public function loadBlogPosts(InputInterface $input, OutputInterface $output)
    {

//         include __DIR__ . '/../Resources/data/Migration/blog_comment.php';
//         $comments = array();
//         foreach ($blog_comment as $row) {
//             $comments[$row['blog_post_id']][] = array('content' => $row['content'], 'status' => $row['status']);
//         }

        $file = 1;
        $nb = 0;

        include __DIR__ . '/../Resources/data/Migration/blog_post.php';

        $sliced = $this->initOffset($input, $blog_post);
        $count = count($sliced);

        foreach ($sliced as $row) {
            $output->writeln("Creation n " . $nb);
            if (isset($row['id']) && isset($row['user_id']) && isset($row['classroom_id']) && isset($row['title']) && isset($row['content']) && isset($row['status'])) {
                if (!$this->isAlreadyDone('blog_post', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id']);

                    $blog = BlogQuery::create()->filterByGroupId($newClassroomId->getId())->findOne();

                    $newArticle = new BlogArticle();
                    $newArticle->setTitle($row['title']);
                    $newArticle->setContent($row['content']);
                    if ($row['status'] == '1') {
                        $status = 'PUBLISHED';
                    } elseif ($row['status'] == '-1') {
                        $status = 'DRAFT';
                    } else {
                        $status = 'FINISHED';
                    }
                    $newArticle->setStatus($status);
                    $newArticle->setBlogId($blog->getId());
                    if ('PUBLISHED' == $status) {
                        $newArticle->setPublishedAt(isset($row['created_at']) ? $row['created_at'] : null);
                    }
                    $newArticle->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null);
                    $newArticle->setIsCommentAllowed(isset($row['allow_comments']) ? $row['allow_comments'] : null);

                    $author = $this->getNewObjectFromOldId('teacher', $row['user_id']);

                    if ($author)
                        $authorId = $author->getId();
                    else {
                        $author = $this->getNewObjectFromOldId('pupil', $row['user_id']);
                        if ($author)
                            $authorId = $author->getId();
                    }

                    if (isset($authorId)) {
                        $newArticle->setAuthorId($authorId);
                        $newArticle->save(null, true);

                        if ($row['blog_post_category_id'] != null) {
                            if ($this->isAlreadyDone('blog_category', $row['blog_post_category_id'])) {
                                $link = new BlogArticleCategory();
                                $link->setArticleId($newArticle->getId());
                                $link->setCategoryId($this->getNewObjectFromOldId('blog_category', $row['blog_post_category_id'])->getId());
                                $link->save();
                            }
                        }
//                         if (isset($comments[$row['id']])) {
//                             if (is_array($comments[$row['id']])) {
//                                 foreach ($comments[$row['id']] as $oldComment) {

//                                     $author = $this->getNewObjectFromOldId('teacher', $row['user_id']);
//                                     if ($author)
//                                         $sauthorId = $author->getId();
//                                     else {
//                                         $sauthor = $this->getNewObjectFromOldId('pupil', $row['user_id']);
//                                         if ($author)
//                                             $sauthorId = $author->getId();
//                                     }
//                                     if (isset($sauthorId)) {
//                                         $newComment = new BlogArticleComment();
//                                         $newComment->setContent($oldComment['content']);
//                                         $newComment->setAuthorId($sauthorId);
//                                         if ($oldComment['status'] == 1) {
//                                             $status = 'VALIDATED';
//                                         } elseif ($oldComment['status'] = 0) {
//                                             $status = 'PENDING_VALIDATION';
//                                         } else {
//                                             $status = 'REFUSED';
//                                         }
//                                         $newComment->setStatus($status);
//                                         $newComment->setObjectId($newArticle->getId());
//                                         $newComment->save(null, true);
//                                     }
//                                 }
//                             }
//                         }
                        $this->saveInMigration($row['id'], $newArticle->getId(), 'blog_post', array());
                        unset($row, $newArticle, $blog, $newComment);
                    }
                } else {

                }
            } else {

            }
            $nb++;
        }
    }

    public function loadBlogComments(InputInterface $input, OutputInterface $output)
    {

        include __DIR__ . '/../Resources/data/Migration/blog_comment.php';

        $nb = 0;

        $sliced = $this->initOffset($input, $blog_comment);
        $count = count($sliced);

        foreach ($sliced as $row) {
            $output->writeln("Creation n " . $nb . ' / ' . $count);
            if (isset($row['id']) && isset($row['user_id']) && isset($row['classroom_id']) && isset($row['blog_post_id']) && isset($row['content']) && isset($row['status']) && isset($row['created_at'])) {
                if (!$this->isAlreadyDone('blog_comment', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id']) && $this->isAlreadyDone('blog_post', $row['blog_post_id'])) {

                    $newBlogPost = $this->getNewObjectFromOldId('blog_post', $row['blog_post_id']);

                    if ($newBlogPost && is_object($newBlogPost)) {
                        $newBlogPostId = $newBlogPost->getId();
                        $author = $this->getNewObjectFromOldId('teacher', $row['user_id']);

                        if ($author)
                            $authorId = $author->getId();
                        else {
                            $author = $this->getNewObjectFromOldId('pupil', $row['user_id']);
                            if ($author)
                                $authorId = $author->getId();
                        }
                        if (!isset($authorId)) {
                            $author = $this->getNewObjectFromOldId('parent', $row['user_id']);
                            if ($author)
                                $authorId = $author->getId();
                        }

                        if (isset($authorId)) {
                            $newComment = new BlogArticleComment();
                            $newComment->setContent($row['content']);
                            $newComment->setAuthorId($authorId);
                            if ($row['status'] == 1) {
                                $status = 'VALIDATED';
                            } elseif ($row['status'] == 0) {
                                $status = 'PENDING_VALIDATION';
                            } else {
                                $status = 'REFUSED';
                            }
                            $newComment->setStatus($status);
                            $newComment->setObjectId($newBlogPostId);
                            $newComment->setDate($row['created_at']);
                            $newComment->save(null, true);

                            $this->saveInMigration($row['id'], $newComment->getId(), 'blog_comment', array());
                        }
                    }
                    unset($row, $author, $newComment);
                } else {

                }
            } else {

            }
            $nb++;
        }
    }

    /*
     * Carnet de liaison
     *

    ----------- OLD QUERY -------------
    SELECT correspondence_message.`id` ,  `title` ,  `author_id` ,  `classroom_id` ,  `content` , correspondence_message.`created_at`
    FROM correspondence_message
    JOIN classroom ON classroom.id = correspondence_message.classroom_id
    WHERE (
        classroom.status >= -1
        OR classroom.created_at >  "2012-09-01 00:00:00"
    )

    ----------- NEW QUERY -------------
    SELECT correspondence_message.`id` ,  `title` ,  `author_id` ,  `classroom_id` ,  `content` , correspondence_message.`created_at`
    FROM correspondence_message
    JOIN classroom ON classroom.id = correspondence_message.classroom_id
    WHERE (
        classroom.status >= -1
    )

     */

    public function loadCorrespondenceMessage(InputInterface $input, OutputInterface $output)
    {

        include __DIR__ . '/../Resources/data/Migration/correspondence_message.php';
        include __DIR__ . '/../Resources/data/Migration/correspondence_message_read.php';

        $signatures = array();
        foreach ($correspondence_message_read as $row) {
            $signatures[$row['correspondence_message_id']][] = $row['user_id'];
        }

        $nb = 1;

        foreach ($correspondence_message as $row) {
            $output->writeln("Creation n " . $nb);
            if (isset($row['id']) && isset($row['author_id']) && isset($row['classroom_id']) && isset($row['title']) && isset($row['created_at']) && isset($row['content'])) {
                if (!$this->isAlreadyDone('correspondance_message', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();
                    $group = GroupQuery::create()->findPk($newClassroomId);

                    if ($group) {
                        $author = $this->getNewObjectFromOldId('teacher', $row['author_id']);
                        if ($author) {
                            $newMessage = new LiaisonBook();
                            $newMessage->setAuthorId($authorId = $author->getId());
                            // fix bug conversion Ẻ pour le slug
                            $newMessage->setTitle(str_replace('Ẻ', 'É', $row['title']));
                            $newMessage->setContent($row['content']);
                            $newMessage->setGroupId($newClassroomId);
                            $newMessage->setDate(strstr($row['created_at'], ' ', true));
                            $newMessage->setCreatedAt($row['created_at']);
                            $newMessage->save(null, true);
                            $this->saveInMigration($row['id'], $newMessage->getId(), 'correspondance_message', array());

                        }
                    }
                }
                $newMessage = $this->getNewObjectFromOldId('correspondance_message', $row['id']);
                if ($newMessage) {
                    if (isset($signatures[$row['id']])) {
                        foreach ($signatures[$row['id']] as $userId) {
                            $sign = new LiaisonBookSignature();
                            $sign->setLiaisonBookId($newMessage->getId());
                            $parent = $this->getNewObjectFromOldId('parent', $userId);
                            if ($parent) {
                                $sign->setUserId($parent->getId());
                                $sign->save();
                            }
                        }
                    }
                }

            }
            $nb++;
        }
    }

    /**

    CALENDRIER

    SELECT event.*
    FROM event
    JOIN classroom ON classroom.id = event.classroom_id
    WHERE (
    classroom.status >= -1
    )
     */

    public function loadEvent(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/event.php';
        $nb = 0;
        foreach ($event as $row) {
            $output->writeln("Creation n " . $nb);
            if (isset($row['id']) && isset($row['description']) && isset($row['content']) && isset($row['eventStartDate']) && isset($row['eventEndDate']) && isset($row['classroom_id'])) {
                if (!$this->isAlreadyDone('event', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    $group = GroupQuery::create()->findPk($this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId());

                    $agenda = AgendaQuery::create()->findOneByGroupId($group->getId());

                    if ($group && $agenda) {

                        $eventInfos = array('summary' => $row['description'], 'description' => str_replace(array('\n', CHR(13), CHR(10)), '', $row['content']), 'location' => $row['place'], 'dtstart' => strtotime($row['eventStartDate']), 'dtend' => strtotime($row['eventEndDate']),
                                'allday' => false, 'rrule' => '',);

                        $newEvent = $this->getContainer()->get('bns.calendar_manager')->createEvent($agenda->getId(), $eventInfos, true);
                        $this->saveInMigration($row['id'], $newEvent->getId(), 'event', array());
                        unset($row, $eventInfos, $newEvent);
                    }
                }
            }
            $nb++;
        }
    }

    /*

    SELECT gmap_category.`id` ,  `title` , gmap_category.`slug` ,  `classroom_id` , gmap_category.`created_at` , gmap_category.`updated_at`
    FROM  `gmap_category`
    LEFT JOIN classroom ON gmap_category.classroom_id = classroom.id
    WHERE classroom.status >= -1

    SELECT *
    FROM  `gmap_place`
    LEFT JOIN gmap_place_i18n ON gmap_place.id = gmap_place_i18n.id

     *  */
    public function loadGps(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/gmap_category.php';

        $nb = 0;
        $count = count($gmap_category);
        foreach ($gmap_category as $row) {
            $output->writeln("Creation categorie n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['title']) && isset($row['classroom_id'])) {
                if (!$this->isAlreadyDone('gps_category', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();

                    $newCategory = new GpsCategory();
                    $newCategory->setLabel($row['title']);
                    $newCategory->setIsActive(true);
                    $newCategory->setGroupId($newClassroomId);
                    $newCategory->setOrder(1);
                    $newCategory->save();
                    $this->saveInMigration($row['id'], $newCategory->getId(), 'gps_category', array());
                }
            }
            $nb++;
        }

        include __DIR__ . '/../Resources/data/Migration/gmap_place.php';

        unset($this->already['gps_category']);

        $nb = 0;
        $count = count($gmap_place);
        foreach ($gmap_place as $row) {
            $output->writeln("Creation lieu n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['classroom_id']) && isset($row['gmap_category_id']) && isset($row['name'])) {
                if (!$this->isAlreadyDone('gps_place', $row['id']) && $this->isAlreadyDone('gps_category', $row['gmap_category_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();
                    $category = $this->getNewObjectFromOldId('gps_category', $row['gmap_category_id']);
                    $newCategoryClassroomId = $category->getGroupId();

                    if ($newClassroomId == $newCategoryClassroomId) {

                        $newPlace = new GpsPlace();
                        $newPlace->setLabel($row['name']);
                        $newPlace->setDescription($row['description']);
                        $newPlace->setIsActive($row['status']);
                        $newPlace->setGpsCategoryId($category->getId());
                        $newPlace->setAddress($row['address']);
                        if (@$row['coordinates'] != "" && @$row['coordinates'] != null)
                            $newPlace->setLatitude(substr(strstr($row['coordinates'], ','), 1));
                        if (@$row['coordinates'] != "" && @$row['coordinates'] != null)
                            $newPlace->setLongitude(strstr($row['coordinates'], ',', true));
                        $newPlace->save();
                        $this->saveInMigration($row['id'], $newPlace->getId(), 'gps_place', array());
                    } else {
                        $output->writeln('.. error with group ID');
                    }
                } else {
                    $output->writeln('.. error with id or category or classroom');
                }
            } else {
                $output->writeln('.. error missing data');
            }
            $nb++;
        }
    }


    public function loadSearchAnnotations(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/search_annotation_category.php';

        $nb = 0;
        $count = count($search_annotation_category);
        foreach ($search_annotation_category as $row) {
            $output->writeln("Creation categorie n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['label']) && isset($row['classroom_id'])) {
                if (!$this->isAlreadyDone('search_annotation_category', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();
                    $oldLabel = ResourceLabelGroupQuery::create()->filterByGroupId($newClassroomId)->filterByTreeLevel(0)->findOne();

                    $newFolder = new ResourceLabelGroup();
                    $newFolder->setLabel($row['label']);
                    $newFolder->insertAsLastChildOf($oldLabel);
                    $newFolder->setGroupId($newClassroomId);
                    $newFolder->save();

                    $this->saveInMigration($row['id'], $newFolder->getId(), 'search_annotation_category', array());
                }
            }
            $nb++;
        }

        include __DIR__ . '/../Resources/data/Migration/search_annotation.php';

        $nb = 0;
        $count = count($search_annotation);

        $cm = $this->getContainer()->get('bns.classroom_manager');
        $rc = $this->getContainer()->get('bns.resource_creator');

        foreach ($search_annotation as $row) {
            $output->writeln("Creation lien n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['classroom_id']) && isset($row['category_id']) && isset($row['annotation'])) {
                if (!$this->isAlreadyDone('search_annotation', $row['id']) && $this->isAlreadyDone('search_annotation_category', $row['category_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $newClassroomId = $this->getNewObjectFromOldId('classroom', $row['classroom_id'])->getId();
                    $category = $this->getNewObjectFromOldId('search_annotation_category', $row['category_id']);
                    $newCategoryClassroomId = $category->getGroupId();

                    if ($newClassroomId == $newCategoryClassroomId) {
                        $cm->setGroupById($newClassroomId);
                        $teachers = $cm->getTeachers();
                        if($teachers){
                            foreach($teachers as $teacher)
                            {
                                $teacherId = $teacher->getId();
                            }

                            if($teacherId){
                                $annotationDatas = array(
                                    'url' => $row['annotation'],
                                    'title' => $row['annotation'],
                                    'description' => $row['description'],
                                    'type' => 'LINK',
                                    'destination' => 'group_' . $newClassroomId . '_' . $category->getId(),
                                    'user_id' => $teacherId
                                );

                                $rc->createFromUrl($annotationDatas);
                                $this->saveInMigration($row['id'], $rc->getObject()->getId(), 'search_annotation', array());
                            }
                        }

                    } else {
                        $output->writeln('.. error with group ID');
                    }
                } else {
                    $output->writeln('.. error with id or category or classroom');
                }
            } else {
                $output->writeln('.. error missing data');
            }
            $nb++;
        }
    }

    public function loadMessages(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/message.php';

        $nb = 0;
        $count = count($message);

        $eq = array('1' => '1', '2' => '1', '0' => '2', '-1' => '0');

        $messagesConversationStatus = array('SENT' => 4, 'IN_MODERATION' => 3, 'NONE_READ' => 2, 'READ' => 1, 'DELETED' => 0);

        include __DIR__ . '/../Resources/data/Migration/message_receiver.php';
        $receivers = array();
        foreach ($message_receiver as $row) {
            $receivers[$row['message_id']][] = array('user_id' => $row['user_id'], 'is_read' => $row['is_read'], 'is_trashed' => $row['is_trashed']);
        }

        $sliced = $this->initOffset($input, $message);
        $count = count($sliced);

        foreach ($sliced as $row) {
            $output->writeln("Creation message n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['title']) && isset($row['content']) && isset($row['author_id']) && isset($row['status'])) {

                if (!$this->isAlreadyDone('message', $row['id'])) {

                    $author = $this->getNewObjectFromOldId('teacher', $row['author_id']);

                    if ($author)
                        $authorId = $author->getId();
                    else {
                        $author = $this->getNewObjectFromOldId('pupil', $row['author_id']);
                        if ($author)
                            $authorId = $author->getId();
                    }

                    if ($author) {
                        $newMessage = new MessagingMessage();
                        $newMessage->setAuthorId($author->getId());
                        $newMessage->setSubject($row['title']);
                        $newMessage->setContent($row['content']);
                        $newMessage->setCreatedAt($row['created_at']);
                        $newMessage->setStatus($eq[$row['status']]);
                        $newMessage->save();
                        $this->saveInMigration($row['id'], $newMessage->getId(), 'message', array());
                        if (isset($receivers[$row['id']])) {
                            if (is_array($receivers[$row['id']])) {
                                foreach ($receivers[$row['id']] as $rec) {

                                    $recUser = $this->getNewObjectFromOldId('teacher', $rec['user_id']);

                                    if ($recUser)
                                        $authorId = $recUser->getId();
                                    else {
                                        $recUser = $this->getNewObjectFromOldId('pupil', $rec['user_id']);
                                        if ($recUser)
                                            $authorId = $author->getId();
                                    }

                                    if ($recUser) {

                                        $conversation = new MessagingConversation();
                                        $conversation->setUserId($recUser->getId());
                                        $conversation->setUserWithId($author->getId());
                                        $conversation->setMessageParentId($newMessage->getId());
                                        $conversation->setCreatedAt($row['created_at']);
                                        if ($rec['is_trashed']) {
                                            $conversation->setStatus($messagesConversationStatus['DELETED']);
                                        } elseif ($rec['is_read']) {
                                            $conversation->setStatus($messagesConversationStatus['READ']);
                                        } else {
                                            $conversation->setStatus($messagesConversationStatus['NONE_READ']);
                                        }
                                        $conversation->save();
                                        $conversation->link($newMessage);

                                        $myConversation = new MessagingConversation();
                                        $myConversation->setUserId($author->getId());
                                        $myConversation->setUserWithId($recUser->getId());
                                        $myConversation->setMessageParentId($newMessage->getId());
                                        $myConversation->setStatus($messagesConversationStatus['SENT']);
                                        $myConversation->setCreatedAt($row['created_at']);
                                        $myConversation->save();
                                        $myConversation->link($newMessage);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $nb++;
        }
    }
    /*

    SELECT aws_folder.id, label, classroom_id, tree_left, tree_right, is_private, author_id, is_deposit
    FROM  `aws_folder`
    JOIN classroom ON aws_folder.classroom_id = classroom.id
    WHERE (classroom.status >= -1) AND aws_folder.partnership_id IS NULL AND aws_folder.label NOT LIKE "Espace élèves"


     */
    public function loadAwsFolders(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/aws_folder.php';

        $nb = 0;

        $sliced = $this->initOffset($input, $aws_folder);
        $count = count($sliced);

        foreach ($sliced as $row) {
            $output->writeln("Creation répertoire n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['classroom_id']) && isset($row['label'])) {
                if (!$this->isAlreadyDone('aws_folder', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $classroom = $this->getNewObjectFromOldId('classroom', $row['classroom_id']);

                    if ($classroom) {
                        if ($row['is_private'] == true && $row['author_id'] != null) {
                            //Dossier utilisateur
                            $author = $this->getNewObjectFromOldId('pupil', $row['author_id']);

                            if ($author)
                                $authorId = $author->getId();
                            else {
                                $author = $this->getNewObjectFromOldId('teacher', $row['author_id']);
                                if ($author)
                                    $authorId = $author->getId();
                            }

                            if ($author) {

                                $oldLabel = ResourceLabelUserQuery::create()->filterByUserId($authorId)->filterByTreeLevel(0)->findOne();
                                if ($row['tree_left'] == 1) {
                                    $newFolder = $oldLabel;
                                } else {
                                    if ($oldLabel) {
                                        $newFolder = new ResourceLabelUser();
                                        $newFolder->setLabel($row['label']);
                                        $newFolder->setUserId($authorId);
                                        $newFolder->insertAsLastChildOf($oldLabel);
                                        $newFolder->save();
                                        $this->saveInMigration($row['id'], $newFolder->getId(), 'aws_folder', array('type' => 'user', 'deposit' => $row['is_deposit']));
                                    }
                                }
                            }

                        } else {
                            $oldLabel = ResourceLabelGroupQuery::create()->filterByGroupId($classroom->getId())->filterByTreeLevel(0)->findOne();
                            if ($row['tree_left'] == 1) {
                                $newFolder = $oldLabel;
                            } else {
                                $newFolder = new ResourceLabelGroup();
                                $newFolder->setLabel($row['label']);
                                $newFolder->insertAsLastChildOf($oldLabel);
                                $newFolder->setGroupId($classroom->getId());
                                $newFolder->save();
                            }
                            $this->saveInMigration($row['id'], $newFolder->getId(), 'aws_folder', array('type' => 'group', 'deposit' => $row['is_deposit']));
                        }
                    } else {
                        $output->writeln('error classroom ' . $row['classroom_id'] . ' non importer');
                    }
                }
            }
            $nb++;
        }
    }

    /**
     *

    SELECT aws_file.id, aws_file.file_name, aws_file.label, aws_file.size, aws_file.classroom_id,
    aws_file.aws_folder_id, aws_file.author_id, aws_file.is_private, aws_file.is_deleted, aws_file.created_at, aws_file.updated_at
    FROM  `aws_file`
    JOIN classroom ON aws_file.classroom_id = classroom.id
    WHERE (
    classroom.status >= -1
    )


    SELECT classroom.id, classroom_config.mediatheque_root_folder_id
    FROM  `classroom_config`
    JOIN classroom ON classroom_config.id = classroom.classroom_config_id
    WHERE (
    classroom.status >= -1
    )


     */
    public function loadAwsFiles(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/aws_file.php';
        $appDir = $this->getContainer()->get('kernel')->getRootDir();
        $nb = 0;

        $sliced = $this->initOffset($input, $aws_file);
        $count = count($sliced);

        $rm = $this->getContainer()->get('bns.resource_manager');
        $rc = $this->getContainer()->get('bns.resource_creator');

        $oldFileSystemAdapter = new Local($input->getOption('fileFolder')?: $appDir . '/data/old/');

        $oldFileSystem = new Filesystem($oldFileSystemAdapter);

        include __DIR__ . '/../Resources/data/Migration/classroom_config.php';
        $configs = array();
        foreach ($classroom_config as $row) {
            $configs[$row['id']] = $row['mediatheque_root_folder_id'];
        }

        foreach ($sliced as $row) {
            $output->writeln("Creation fichier n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['classroom_id']) && isset($row['label'])
                && '1' != $row['is_deleted'] // ne reimporte pas les fichiers dans la corbeille
            ) {
                if (!$this->isAlreadyDone('aws_file', $row['id']) && $this->isAlreadyDone('aws_folder', $row['aws_folder_id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {

                    if ($oldFileSystem->has('mediatheque/' .  $row['id'])) {
                        //Dossier utilisateur
                        $author = $this->getNewObjectFromOldId('pupil', $row['author_id']);

                        if ($author)
                            $authorId = $author->getId();
                        else {
                            $author = $this->getNewObjectFromOldId('teacher', $row['author_id']);
                            if ($author)
                                $authorId = $author->getId();
                        }

                        if (isset($authorId)) {
                            $newFile = new Resource();
                            $newFile->setLabel($row['label']);
                            $newFile->setLang('fr');
                            $newFile->setFilename($row['file_name']);


                            $extension = substr(strrchr($row['file_name'], '.'), 1);

                            try {
                                $mimeType = $rc->extensionToContentType($extension);
                                $modelType = $rc->getModelTypeFromMimeType($mimeType);

                                $newFile->setTypeUniqueName($modelType);
                                $newFile->setStatusCreation(1);
                                $newFile->setStatusDeletion(1);
                                $newFile->setFileMimeType($mimeType);
                                $newFile->setUserId($authorId);

                                $newFile->setSlug('document-' . $row['id']);

                                $migrationDatas = $this->getFromOldId('aws_folder', $row['aws_folder_id']);

                                $migDatas = $migrationDatas->getDatas();
                                $migDatas = unserialize($migDatas);
                                $migType = $migDatas['type'];

                                $newFile->setIsPrivate($migDatas['deposit'] || $row['is_private']);

                                $newFile->save();

                                //Rappatriement

                                $rc->setObject($newFile);

//                                $fileContent = $oldFileSystem->read('classrooms/' . $configs[$row['classroom_id']] . '/' . $row['id'] . '_' . $row['file_name']);
                                $fileContent = $oldFileSystem->read('mediatheque/' .  $row['id']);

                                $file_path = $newFile->getFilePath();
                                $rc->writeFile($file_path, $fileContent);

                                if ($rm->isThumbnailable($newFile)) {
                                    $rc->createThumbs();
                                }



                                if ($migType == 'user' || $migType == 'group') {
                                    $link = $newFile->linkLabel($migType, $migrationDatas->getNewId(), true);
                                    $rm->addSize($newFile, $row['size'], $link->getLabel());
                                }
                                $this->saveInMigration($row['id'], $newFile->getId(), 'aws_file', array());

                            } catch (\Exception $e) {
                                $output->writeln('AWS IMPORT failure ' . $row['id'] . ' : ' . $e->getMessage() . ' -- ' . $e->getTraceAsString());
                                $this->saveInMigration($row['id'], 0, 'aws_file', array());
                            }
                        }
                    } else {
                        $output->writeln('AWS IMPORT failure ' . $row['id'] . ' :  le fichier n\'est pas présent sur le disque');
                    }
                }
            }
            $nb++;
        }
    }


    protected function loadMessageFiles(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/message_aws_file.php';


        $nb = 0;
        $count = count($message_aws_file);
        foreach ($message_aws_file as $fileData) {
            $nb++;
            if (!$this->isAlreadyDone('message_aws_file', $fileData['aws_file_id'] .'000' . $fileData['message_id'])) {
                /* @var $file Resource */
                /* @var $message MessagingMessage */
                if (($file = $this->getNewObjectFromOldId('aws_file', $fileData['aws_file_id']))
                    && ($message = $this->getNewObjectFromOldId('message', $fileData['message_id']))) {

                    $output->writeln('import message file ' . $nb . ' / ' . $count);

                    if ($attachment = $message->addResourceAttachment($file->getId())) {
                        $users = UserQuery::create()
                            ->useMessagingConversationRelatedByUserIdQuery()
                                ->filterByMessageParentId($message->getId())
                            ->endUse()
                            ->find();
                        foreach ($users as $user) {
                            $message->addResourceAttachmentsLinkUsers($attachment->getId(), $user->getId());
                        }
                        $this->saveInMigration($fileData['aws_file_id'] .'000' . $fileData['message_id'], $attachment->getId(), 'message_aws_file', array());
                    }
                }
            }
        }
    }


    /*

    SELECT *
    FROM  `homework`
    JOIN classroom ON homework.classroom_id = classroom.id
    WHERE 1'



     */

    public function loadHomeworks(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/homework.php';
        include __DIR__ . '/../Resources/data/Migration/homework_done.php';

        $dones = array();
        foreach ($homework_done as $row) {
            $dones[$row['homework_id']][] = $row['user_id'];
        }

        $nb = 0;
        $count = count($homework);

        foreach ($homework as $row) {
            $output->writeln("Creation devoir n " . $nb . "/" . $count);
            if (isset($row['id']) && isset($row['classroom_id']) && isset($row['subject']) && isset($row['date'])) {
                if (!$this->isAlreadyDone('homework', $row['id']) && $this->isAlreadyDone('classroom', $row['classroom_id'])) {
                    $classroom = $this->getNewObjectFromOldId('classroom', $row['classroom_id']);

                    $strDate = strtoupper(substr(date('D', strtotime($row['date'])), 0, 2));
                    $nbDate = date('N', strtotime($row['date']));

                    $root = HomeworkSubject::fetchRoot($classroom->getId());

                    if ($root->hasChildren()) {
                        $matiere = $root->getFirstChild();
                    } else {
                        $matiere = new HomeworkSubject();
                        $matiere->setName('Travaux');
                        $matiere->insertAsLastChildOf($root);
                        $matiere->save();
                    }

                    $newHomework = new Homework();
                    $newHomework->setName($row['subject']);
                    $newHomework->setDescription($row['description']);
                    $newHomework->setHelptext($row['help']);
                    $newHomework->setDate($row['date']);
                    $newHomework->addGroup($classroom);
                    $newHomework->setSubjectId($matiere->getId());
                    $newHomework->setRecurrenceType("ONCE");
                    $newHomework->setRecurrenceDays(array($strDate));
                    $newHomework->setRecurrenceEndDate($row['date']);
                    $newHomework->save();

                    $due = new \BNS\App\HomeworkBundle\Model\HomeworkDue();
                    $due->setHomeworkId($newHomework->getId());
                    $due->setDueDate($row['date']);
                    $due->setNumberOfTasksDone(0);
                    $due->setNumberOfTasksTotal(0);
                    $due->setDayOfWeek($strDate);
                    $due->save();

                    if (isset($dones[$row['id']])) {
                        foreach ($dones[$row['id']] as $userId) {
                            $done = new \BNS\App\HomeworkBundle\Model\HomeworkTask();
                            $done->setHomeworkDueId($due->getId());
                            $done->setDone(true);
                            $pupil = $this->getNewObjectFromOldId('pupil', $userId);
                            if ($pupil) {
                                $done->setUserId($pupil->getId());
                                $done->save();
                            }
                        }
                    }

                    $this->saveInMigration($row['id'], $newHomework->getId(), 'homework', array());
                }
            }
            $nb++;
        }
    }


    protected function loadAvatars(InputInterface $input, OutputInterface $output)
    {
        $appDir = $this->getContainer()->get('kernel')->getRootDir();
        $oldFileSystemAdapter = new Local($input->getOption('fileFolder')?: $appDir . '/data/old/');
        $oldFileSystem = new Filesystem($oldFileSystemAdapter);

        include __DIR__ . '/../Resources/data/Migration/sf_guard_pupil_profile.php';
        $nb = 0;
        $count = count($sf_guard_pupil_profile);
        foreach ($sf_guard_pupil_profile as $userData) {
            $nb++;
            $output->write("Import avatar pupil " . $nb . "/" . $count . ' : ' . $userData['username'] . ' ... ');
            if ($this->loadAvatarFor($userData, 'pupil', $oldFileSystem, $output)) {
                $output->writeLn('<info>done</info>');
            } else {
                $output->writeLn('<error>error</error>');
            }

        }

        include __DIR__ . '/../Resources/data/Migration/sf_guard_teacher_profile.php';
        $nb = 0;
        $count = count($sf_guard_teacher_profile);
        foreach ($sf_guard_teacher_profile as $userData) {
            $nb++;
            $output->write("Import avatar teacher " . $nb . "/" . $count . ' : ' . $userData['username'] . ' ... ');
            if ($this->loadAvatarFor($userData, 'teacher', $oldFileSystem, $output)) {
                $output->writeLn('<info>done</info>');
            } else {
                $output->writeLn('<error>error</error>');
            }
        }

    include __DIR__ . '/../Resources/data/Migration/sf_guard_parent_profile.php';
        $nb = 0;
        $count = count($sf_guard_parent_profile);
        foreach ($sf_guard_parent_profile as $userData) {
            $nb++;
            $output->write("Import avatar parent " . $nb . "/" . $count . ' : ' . $userData['username'] . ' ... ');
            if ($this->loadAvatarFor($userData, 'parent', $oldFileSystem, $output)) {
                $output->writeLn('<info>done</info>');
            } else {
                $output->writeLn('<error>error</error>');
            }
        }
    }

    protected function loadAvatarFor($userData, $type, $oldFileSystem, OutputInterface $output)
    {
        $resourceCreator = $this->getContainer()->get('bns.resource_creator');
        $resourceManager = $this->getContainer()->get('bns.resource_manager');

        if ($oldFileSystem->has('/avatars/' . $userData['username'] . '.png')) {
            /** @var $pupil BNS\App\CoreBundle\Model\User */
            $user = $this->getNewObjectFromOldId($type, $userData['user_id']);
            if ($user) {
                // we found the new user so we can set him his new avatar
                if (!$user->hasAvatar()) {

                    if ($oldFileSystem->has('/avatars/' . $userData['username'] . '.png')) {
                        $newFile = new Resource();
                        $newFile->setLabel('Avatar');
                        $newFile->setLang('fr');
                        $newFile->setFilename($userData['username'] . '.png');
                        $newFile->setIsPrivate(false);

                        try {
                            $mimeType = $resourceCreator->extensionToContentType('png');
                            $modelType = $resourceCreator->getModelTypeFromMimeType($mimeType);

                            $newFile->setTypeUniqueName($modelType);
                            $newFile->setStatusCreation(1);
                            $newFile->setStatusDeletion(1);
                            $newFile->setFileMimeType($mimeType);
                            $newFile->setUserId($user->getId());

                            $newFile->setSlug('avatar-' . $userData['user_id'] . '-' . $userData['username']);

                            $newFile->save();

                            //Rappatriement
                            $resourceCreator->setObject($newFile);
                            $size = $resourceCreator->writeFile( $newFile->getFilePath(), $oldFileSystem->read('/avatars/' . $userData['username'] . '.png'));

                            if ($resourceManager->isThumbnailable($newFile)) {
                                $resourceCreator->createThumbs();
                            }

                            $userFolder = ResourceLabelUserQuery::create()->filterByUser($user)->orderByTreeLeft(Criteria::ASC)->findOne();


                            if ($userFolder) {
                                $link = $newFile->linkLabel('user', $userFolder->getId(), true);
                                $resourceManager->addSize($newFile, $size, $link->getLabel());
                            }

                            $user->getProfile()->setAvatarId($newFile->getId());
                            $user->getProfile()->save();


                            $this->saveInMigration($userData['user_id'], $newFile->getId(), 'avatars', array());

                            return true;

                        } catch (\Exception $e) {
                            $output->writeln('AWS IMPORT avatar failure ' . $userData['user_id'] . ' : ' . $e->getMessage() . ' -- ' . $e->getTraceAsString());
                            $this->saveInMigration($userData['user_id'], 0, 'avatars', array());
                        }
                    } else {
                        $output->writeln('AWS IMPORT avatar failure ' . $userData['username'] . '.png :  le fichier n\'est pas présent sur le disque');
                    }
                }
            }
        }

        return false;
    }


    /*

     SELECT
        ug.user_id,
        g.name
        FROM
        sf_guard_user_group ug
        INNER JOIN sf_guard_group g ON ug.group_id = g.id
        WHERE g.`name` IN ('Directors', 'Erip')
        ORDER BY user_id


     */
    protected function loadDirectors(InputInterface $input, OutputInterface $output)
    {
        include __DIR__ . '/../Resources/data/Migration/sf_guard_user_group.php';
        //include __DIR__ . '/../Resources/data/Migration/erip_school.php';

        $eripSchools = array();

        foreach ($erip_school as $eripSchool) {
            $eripSchools[$eripSchool['erip_id']][] = $eripSchool['school_id'];
        }

        $userManager = $this->getContainer()->get('bns.user_manager');
        $schoolGroupType = GroupTypeQuery::create()->filterByType('SCHOOL')->findOne();
        $directorGroupType = GroupTypeQuery::create()->filterByType('DIRECTOR')->findOne();
        if (!$directorGroupType) {
            throw new \Exception('No group type DIRECTOR found');
        }
        $aticeGroupType = GroupTypeQuery::create()->filterByType('ATICE')->findOne();
        if (!$aticeGroupType) {
            throw new \Exception('No group type ATICE found');
        }

        $nb = 0;
        $count = count($sf_guard_user_group);
        foreach ($sf_guard_user_group as $userData) {
            $nb++;
            /* @var $user \BNS\App\CoreBundle\Model\User */
            if (!$this->isAlreadyDone('director_atice', $userData['user_id']) && $user = $this->getNewObjectFromOldId('teacher', $userData['user_id'])) {
                $userManager->setUser($user);
                $groups = $userManager->getGroupsUserBelong();

                $output->write("Import right for user " . $nb . "/" . $count . ' : ' . $user->getUsername());

                if ('Directors' == $userData['name']) {
                    /* @var $group \BNS\App\CoreBundle\Model\Group */
                    foreach ($groups as $group) {
                        if ($schoolGroupType->getId() === $group->getGroupTypeId()) {
                            // we have a school group so we assign the Director/Atice role
                            $userManager->linkUserWithGroup($user, $group, $directorGroupType);
                        }
                    }
                    $output->writeln(' director');
                } else if ('Erip' == $userData['name']) {
                    if (isset($eripSchools[$userData['user_id']])) {
                        foreach ($eripSchools[$userData['user_id']] as $schoolId) {
                            if ($school = SchoolInformationQuery::create()->joinWith('Group')->findOneById($schoolId)) {
                                $userManager->linkUserWithGroup($user, $school->getGroup(), $aticeGroupType);
                            }
                        }
                    }
                    $output->writeln(' atice');
                }

                $this->saveInMigration($userData['user_id'], 0, 'director_atice', array());
            }
        }

    }




    /*
    public function getOldResourceLabel($group){
        if(!isset($this->oldResourceLabels[$group->getId()])){
            $old = ResourceLabelGroupQuery::create()
                ->filterByGroupId($group->getId())
                ->filterByLabel('Anciens Documents')
                ->filterByTreeLevel(1)
            ->findOne();
            if($old){
                $this->oldResourceLabels[$group->getId()] = $old;
            }else{
                $oldRoot = ResourceLabelGroupQuery::create()
                ->filterByGroupId($group->getId())
                ->filterByTreeLevel(0)->findOne();
                $newFolder = new ResourceLabelGroup();
                $newFolder->setLabel('Anciens Documents');
                $newFolder->insertAsLastChildOf($oldRoot);
                $newFolder->save();
                $this->oldResourceLabels[$group->getId()] = $newFolder;
            }
        }
        return $this->oldResourceLabels[$group->getId()];
    }
     */

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return type
     *
     * @throws InvalidArgumentException
     */
    protected function getConnection(InputInterface $input, OutputInterface $output)
    {/*
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);*/
    }

    /**
     * @param OutputInterface $output
     * @param type $text
     * @param type $style
     */
    protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array('', $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true), '',));
    }
}

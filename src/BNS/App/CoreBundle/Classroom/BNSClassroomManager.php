<?php

namespace BNS\App\CoreBundle\Classroom;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\PupilAssistantLinkQuery;
use BNS\App\CoreBundle\Module\BNSModuleManager;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\InfoBundle\Model\SponsorshipQuery;
use BNS\App\MailerBundle\Mailer\BNSMailer;
use BNS\App\PaasBundle\Manager\PaasManager;
use \Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use BNS\App\CoreBundle\Module\IBundleActivation;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\RegistrationBundle\Model\SchoolInformation;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;

/**
 * @author Eymeric Taelman
 *
 * Classe permettant la gestion des Classe (d'école !)
 */
class BNSClassroomManager extends BNSGroupManager implements IBundleActivation
{
    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var BNSApi
     */
    protected $api;

    protected $classroom;
    /**
     * @var BNSRoleManager
     */
    protected $roleManager;

    /**
     * @var BNSMailer
     */
    protected $mailer;

    /**
     * @param ContainerInterface $container
     * @param BNSRoleManager $roleManager
     * @param BNSUserManager $userManager
     * @param BNSApi $api
     * @param BNSModuleManager $moduleManager
     * @param int $domainId
     * @param BNSMailer $mailer
     */
    public function __construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId, $mailer)
    {
        parent::__construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId);

        $this->mailer = $mailer;
    }

    /*
     * Création d'une classe
     * @params array $params
     * @return Group
     */
    public function createClassroom($params)
    {
        if (!isset($params['label'])) {
            throw new HttpException(500, 'Please provide a classroom name!');
        }

        $classroomGroupTypeRole = GroupTypeQuery::create()->findOneByType('CLASSROOM');
        $newClassroomsParams = array(
            'group_type_id' => $classroomGroupTypeRole->getId(),
            'type' => $classroomGroupTypeRole->getType(),
            'domain_id' => $this->domainId,
            'label' => $params['label'],
            'validated' => isset($params['validated']) && $params['validated'] ? true : false
        );
        foreach ($params as $key => $param) {
            if (!isset($newClassroomsParams[$key])) {
                $newClassroomsParams[$key] = $param;
            }
        }
        $this->classroom = $this->createSubgroupForGroup($newClassroomsParams, $params['group_parent_id']);
        $this->setClassroom($this->classroom);

        if ($this->container->hasParameter('registration.current_year')) {
            $params['attributes']['CURRENT_YEAR'] = $this->container->getParameter('registration.current_year');
        }

        if (isset($params['attributes'])) {
            foreach ($params['attributes'] as $name => $value) {
                $this->classroom->setAttribute($name, $value);
            }
        }

        return $this->classroom;
    }

    /**
     * Création d'une école à partir de School Informations
     */

    public function createSchoolFromInformation($schoolInformation, $applicationEnvironment = null)
    {
        $applicationEnvironment = $applicationEnvironment ?: $this->container->getParameter('application_environment');

        $environment = GroupQuery::create()->filterById($applicationEnvironment)->findOne();

        //$groupManager = $this->get('bns.group_manager');
        $params = array();

        // Setting params
        $params['label'] = $schoolInformation->getName();
        $params['type'] = 'SCHOOL';
        $params['validated'] = true;

        // Create group
        $school = $this->createGroup($params);

        // Create attributes
        $school->setAttribute('NAME', $schoolInformation->getName());
        $school->setAttribute('UAI', $schoolInformation->getUai());
        $school->setAttribute('ADDRESS', $schoolInformation->getAddress());
        $school->setAttribute('CITY', $schoolInformation->getCity());
        $school->setAttribute('EMAIL', $schoolInformation->getEmail());
        $school->setAttribute('ZIPCODE', $schoolInformation->getZipCode());

        // Link with env
        $this->linkGroupWithSubgroup($environment->getId(), $school->getId());

        // Update school info with the new linked group_id
        $schoolInformation->setGroupId($school->getId());
        $schoolInformation->save();
        return $school;
    }

    /**
     * @deprecated
     * @param string $slug
     *
     * @return Group
     *
     * @throws NotFoundHttpException
     */
    public function findBySlug($slug)
    {
        $classroom = GroupQuery::create()
            ->joinWith('GroupType')
            ->useGroupTypeQuery()
                ->filterByType('CLASSROOM')
            ->endUse()
            ->findOneBySlug($slug);

        if (null == $classroom) {
            throw new NotFoundHttpException('The group with the slug ' . $slug . ' does not exist !');
        }

        $this->setClassroom($classroom);

        return $classroom;
    }

    /**
     * @return Group
     */
    public function getClassroom()
    {
        return $this->classroom;
    }

    /**
     * @param Group $classroom
     *
     * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    public function setClassroom($classroom)
    {
        return $this->setGroup($classroom);
    }

    /**
     * @param Group $classroom
     *
     * @return $this
     */
    public function setGroup($classroom)
    {
        $this->classroom = $classroom;
        parent::setGroup($classroom);

        return $this;
    }

    /**
     * @return Group[]|array
     */
    public function getTeams()
    {
        return $this->getSubgroupsByGroupType('TEAM');
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $user
     *
     * @return boolean
     */
    public function isOneOfMyTeachers(User $user)
    {
        $teachers = $this->getTeachers();
        $isOneOfMyTeachers = false;

        foreach ($teachers as $teacher) {
            if ($user->getId() == $teacher->getId()) {
                $isOneOfMyTeachers = true;
                break;
            }
        }

        return $isOneOfMyTeachers;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isOneOfMyAssistants(User $user)
    {
        $userId = $user->getId();
        foreach ($this->getAssistants() as $assistant) {
            if ($userId === $assistant->getId()) {
                return true;
            }
        }

        // check if $user is a dedicated assistant to one of my pupil
        $pupils = $this->getPupils(false);
        $query = PupilAssistantLinkQuery::create()
            ->filterByAssistantId($user->getId())
            ->filterByPupilId(array_map(function($item){
                return isset($item['id']) ? $item['id'] : null;
            }, $pupils), \Criteria::IN);

        return $query->count() > 0;
    }

    /**
     * @param \BNS\App\CoreBundle\Model\Group $team
     *
     * @return bool
     */
    public function isOneOfMyTeams(Group $team)
    {
        $isOneOfMyTeams = false;
        if (0 == strcmp($team->getGroupType()->getType(), 'TEAM')) {
            $isOneOfMyTeams = $this->isSubgroup($team);
        }

        return $isOneOfMyTeams;
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $user
     *
     * @return boolean
     */
    public function isOneOfMyPupils(User $user)
    {
        $pupils = $this->getPupils();
        $isOneOfMyPupils = false;
        if ($pupils != null) {
            foreach ($pupils as $pupil) {
                if ($user->getId() == $pupil->getId()) {
                    $isOneOfMyPupils = true;
                    break;
                }
            }
        }
        return $isOneOfMyPupils;
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $user
     *
     * @return boolean
     */
    public function isOneOfMyPupilsParents(User $user)
    {
        $parents = $this->getPupilsParents();
        $isOneOfMyPupilsParents = false;
        if ($parents != null) {
            foreach ($parents as $parent) {
                if ($user->getId() == $parent->getId()) {
                    $isOneOfMyPupilsParents = true;
                    break;
                }
            }
        }
        return $isOneOfMyPupilsParents;
    }

    /**
     * @return User[]|array
     */
    public function getPupils($returnObject = true)
    {
        return $this->getUsersByRoleUniqueName('PUPIL', $returnObject);
    }

    /**
     * @param bool|true $returnObject
     * @return array<User>
     */
    public function getAssistants($returnObject = true)
    {
        return $this->getUsersByRoleUniqueName('ASSISTANT', $returnObject);
    }

    /**
     * @param boolean $returnObject
     *
     * @return array
     */
    public function getPupilsParents($returnObject = true)
    {
        return $this->getUsersByRoleUniqueName('PARENT', $returnObject);
    }

    /**
     * @return User[]|array
     */
    public function getTeachers($returnObject = true)
    {
        return $this->getUsersByRoleUniqueName('TEACHER', $returnObject);
    }

    /**
     * @param User $pupil
     */
    public function assignPupil(User $pupil, $withParent = true)
    {
        $this->roleManager->setGroupTypeRoleFromType('PUPIL')->assignRole($pupil, $this->classroom->getId());
        if($this->classroom->getLang() != null)
        {
            $pupil->setLang($this->classroom->getLang());
            $pupil->save();
        }


        if ($withParent) {
            $this->createParentAccount($pupil, true);
        }
    }

    /**
     * @param array $pupils
     * @param Group $targetGroup
     *
     * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    public function migratePupils($pupils, $targetGroup)
    {
        $currentGroup = $this->getClassroom();

        $this->setClassroom($targetGroup);
        $this->assignPupils($pupils);

        $this->setClassroom($currentGroup);

        return $this;
    }

    /**
     * @param array $pupils
     *
     * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    public function assignPupils($pupils)
    {
        $this->roleManager->setGroupTypeRoleFromType('PUPIL')->assignRoleForUsers($pupils, $this->classroom->getId());

        return $this;
    }

    /**
     * @param array $parents
     * @param Group $targetGroup
     *
     * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    public function migrateParents($parents, $targetGroup)
    {
        $currentGroup = $this->getClassroom();

        $this->setClassroom($targetGroup);
        $this->assignParents($parents);

        $this->setClassroom($currentGroup);

        return $this;
    }

    /**
     * @param array $parents
     *
     * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    public function assignParents($parents)
    {
        $this->roleManager->setGroupTypeRoleFromType('PARENT')->assignRoleForUsers($parents, $this->classroom->getId());

        return $this;
    }

    /**
     * @param User $teacher
     */
    public function assignTeacher($teacher)
    {
        $this->roleManager->setGroupTypeRoleFromType('TEACHER')->assignRole($teacher, $this->classroom->getId());
    }

    /**
     * @param string $assistant
     */
    public function assignAssistant($assistant)
    {
        $this->roleManager->setGroupTypeRoleFromType('ASSISTANT')->assignRole($assistant, $this->classroom->getId());
    }

	/**
     * @param User $parent
     */
    public function assignParent($parent)
    {
        $this->roleManager->setGroupTypeRoleFromType('PARENT');
        $this->roleManager->assignRole($parent, $this->classroom->getId());
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $pupil
     * @param \BNS\App\CoreBundle\Model\User $parent
     */
    public function linkPupilWithParent(User $pupil, User $parent)
    {
        $this->userManager->addParent($pupil, $parent);
    }

    /**
     * Utiliser cette fonction pour un aouter un premier compte parent pour un
     * eleve lors de se creation
     * @param \BNS\App\CoreBundle\Model\User $pupil
     * @param $firstAccount
     */
    public function createParentAccount(User $pupil, $firstAccount = false)
    {
        if ($firstAccount) {
            $parent = $this->userManager->createUser(
                array(
                    'first_name' => 'Parent de ',
                    'last_name' => $pupil->getFullName(),
                    'lang' => $pupil->getLang(),
                    'username' => $pupil->getUsername() . 'PAR',
                    'gender' => 'M',
                    'lang' => $pupil->getLang()
                ,
                    false
                ));
        } else {
            $newParentNumber = 1;
            $usersPrents = UserQuery::create()
                ->filterByLogin($pupil->getUsername() . 'PAR%', Criteria::LIKE)
                ->find();

            foreach ($usersPrents as $parent) {
                if (preg_match("#[0-9]+$#", $parent->getUsername(), $matches)) {
                    if (intval($matches[0]) >= $newParentNumber) {
                        $newParentNumber = intval($matches[0]) + 1;
                    }
                }
            }

            $parent = $this->userManager->createUser(
                array(
                    'first_name' => 'Parent ' . $newParentNumber . ' de ',
                    'last_name' => $pupil->getFullName(),
                    'lang' => $pupil->getLang(),
                    'username' => $pupil->getUsername() . 'PAR' . $newParentNumber
                ,
                    false
                )
            );
        }
        $this->assignParent($parent);
        $this->linkPupilWithParent($pupil, $parent);
    }

    /**
     * Créé sur la centrale d'authtification une invitation à l'utilisateur $user à rejoindre la classe courante
     * (il faut faire un setClassroom()) en tant que professeur
     *
     * @param User $user l'utilisateur à inviter
     * @param User $author l'utilisateur qui invite
     */
    public function inviteTeacherInClassroom(User $user, User $author)
    {
        $this->inviteUserInGroup($user, $author, $this->roleManager->findGroupTypeRoleByType('TEACHER'));
    }

    /**
     * Créé sur la centrale d'authtification une invitation à l'utilisateur $user à rejoindre la classe courante
     * (il faut faire un setClassroom()) en tant que professeur
     *
     * @param User $user
     * @param User $author
     * @throws \BNS\App\CoreBundle\Group\InvalidArgumentException
     */
    public function inviteAssistantInClassroom(User $user, User $author)
    {
        $this->inviteUserInGroup($user, $author, $this->roleManager->findGroupTypeRoleByType('ASSISTANT'));
    }

	/**
     * Permet de vérifier si un enseignant est déjà invité dans la classe courante ou non (il faut faire un setClassroom())
     *
     * @param User $user l'utilisateur dont on veut vérifier s'il fait déjà l'objet d'une invitation à rejoindre la classe courante ou non
     * @return boolean true si l'utilisateur est déjà invité à rejoindre le groupe, false sinon
     */
    public function isInvitedInClassroom(User $user)
    {
        return $this->isInvitedInGroup($user, $this->roleManager->findGroupTypeRoleByType('TEACHER'));
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isInvitedAsAssistantInClassroom(User $user)
    {
        return $this->isInvitedInGroup($user, $this->roleManager->findGroupTypeRoleByType('ASSISTANT'));
    }

	/**
     * Méthode qui permet d'importer des élèves dans la classe courante ($this->classroom) depuis un fichier CSV
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file le fichier CSV
     * @param type $format Format Beneylu School (=0) || Format Base Elèves (=1)
     * @return array tableau contenant les informations sur l'opération d'importation : key 'user_count' = le nombre d'utilisateur que le processus
     * a essayé d'insérer; key 'success_insertion_count' : le nombre d'utilisateur inséré avec succès
     */
    public function importPupilFromCSVFile(UploadedFile $file, $format)
    {
        return $this->userManager->importUserFromCSVFile($file, $format, $this->classroom,
            $this->roleManager->findGroupTypeRoleByType('PUPIL'));
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $teacher
     * @param \BNS\App\RegistrationBundle\Model\SchoolInformation $schoolInfo
     *
     * @throws \RuntimeException
     */
    public function sendConfirmation(User $teacher, SchoolInformation $schoolInfo = null)
    {
        //TODO AME
        // Check before sending mails
        if ('CLASSROOM' != $this->getClassroom()->getGroupType()->getType()) {
            throw new \InvalidArgumentException('The group must be a CLASSROOM ! Current group type : ' . $this->getClassroom()->getGroupType()->getType());
        }

        if (null == $schoolInfo) {
            $schoolInfo = SchoolInformationQuery::create('si')
                ->where('si.GroupId = ?', $this->getParent()->getId())
                ->findOne();

            if (null == $schoolInfo) {
                throw new \RuntimeException('Unknown school information for the group parent with id : ' . $this->getParent()->getId() . ' (child group id : ' . $this->getGroup()->getId() . ') !');
            }
        }

        // L'e-mail de l'école est manquant, on averti la classe
        if (null == $schoolInfo->getEmail()) {
            $this->getClassroom()->setValidationStatus(GroupPeer::VALIDATION_STATUS_PENDING_SCHOOL_EMAIL_ADDRESS);
            $this->getClassroom()->setPendingValidationDate(time() + $this->container->getParameter('classroom_pending_time_missing_school_email'));

            $this->mailer->send('MISSING_EMAIL_FOR_SCHOOL', array(
                    'school_name' => $schoolInfo->getName(),
                    'edition_url' => $this->container->get('router')->generate('BNSAppClassroomBundle_back', array(),
                        true) // TODO correct URL
                ),
                $teacher->getEmail(),
                $teacher->getLang());
        } // Tout est correct, on averti l'école que la classe a été créée pour la confirmer
        else {
            $this->createConfirmationToken($teacher, $schoolInfo);
        }

        // Finally
        $this->getClassroom()->save();
    }

    /**
     * @param \BNS\App\CoreBundle\Model\User $teacher
     * @param \BNS\App\RegistrationBundle\Model\SchoolInformation $schoolInfo
     *
     * @throws \RuntimeException
     */
    public function createConfirmationToken(User $teacher, SchoolInformation $schoolInfo = null)
    {
        //TODO AME
        if (null == $schoolInfo) {
            $schoolInfo = SchoolInformationQuery::create('si')
                ->where('si.GroupId = ?', $this->getParent()->getId())
                ->findOne();

            if (null == $schoolInfo) {
                throw new \RuntimeException('Unknown school information for the group parent with id : ' . $this->getParent()->getId() . ' (child group id : ' . $this->getGroup()->getId() . ') !');
            }
        }

        // Setting validation status
        $this->getClassroom()->setValidationStatus(GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION);
        $this->getClassroom()->setPendingValidationDate(time() + $this->container->getParameter('classroom_pending_time_confirmation_by_school'));

        // Setting confirmation token
        $this->getClassroom()->setConfirmationToken(md5($this->getClassroom()->getLabel() . time() . '2012bns3' . $schoolInfo->getName()));

        $this->mailer->send('CLASSROOM_CONFIRMATION_FOR_SCHOOL', array(
                'classroom_name' => $this->getClassroom()->getLabel(),
                'confirmation_url' => $this->container->get('router')->generate('registration_confirm_classroom', array(
                    'token' => $this->getClassroom()->getConfirmationToken()
                ), true)
            ),
            $schoolInfo->getEmail(),
            $teacher->getLang());
    }

    /**
     * Confirm a classroom
     */
    public function confirmClassRoom()
    {
        $classRoom = $this->getClassroom();
        $classRoom->validateStatus();

        //Vérification de parrainages en cours sur version publique
        if ($this->isOnPublicVersion()) {
            $teachers = $this->getUsersByRoleUniqueName('TEACHER', true);
            foreach ($teachers as $teacher) {
                $sponsorShips = SponsorshipQuery::create()->findByEmail($teacher->getEmail());
                if ($sponsorShips) {
                    foreach ($sponsorShips as $sponsorShip) {
                        if ($sponsorShip && $sponsorShip->isRegistered()) {
                            $sponsorShip->activate();
                            $this->container->get('bns.mailer')->send('SPONSORSHIP_VALIDATED',
                                array('sponsored_full_name' => $sponsorShip->getUserRelatedByToUserId()->getFullName()),
                                $sponsorShip->getUserRelatedByFromUserId()->getEmail());
                            if ($sponsorShip->isValidated()) {
                                $sponsorshipper = $sponsorShip->getUserRelatedByFromUserId();
                                $this->container->get('bns.paas_manager')->generateSubscription(GroupQuery::create()->findOneById($sponsorShip->getSchoolId()),
                                    PaasManager::PREMIUM_SUBSCRIPTION, 'P1M', $sponsorshipper->getLogin(),
                                    $sponsorshipper->getEmail());
                            }
                        }
                    }
                }

            }
        }

    }

    /**
     * Déconfirmer une classe
     */
    public function unconfirmClassRoom()
    {
        $classRoom = $this->getClassroom();
        $classRoom->setValidationStatus(GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION);
        $classRoom->removePendingValidationDate();
        $classRoom->setConfirmationToken(null);
        $classRoom->save();
    }

    /**
     * Refuser une classe
     */
    public function refuseClassRoom()
    {
        $classRoom = $this->getClassroom();
        $classRoom->refuse();
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     */
    public function exportPupilsToCSV()
    {
        if (null == $this->getGroup()) {
            throw new \RuntimeException('You must call "setGroup(Group $group)" before using this method !');
        }

        $pupils = $this->getPupils();
        $csv = array(
            array(
                $this->container->get('translator')->trans('CSV_FIELD_LASTNAME', array(), 'CORE'),
                $this->container->get('translator')->trans('CSV_FIELD_FIRSTNAME', array(), 'CORE'),
                $this->container->get('translator')->trans('CSV_FIELD_BIRTHDAY', array(), 'CORE'),
                $this->container->get('translator')->trans('CSV_FIELD_GENDER', array(), 'CORE')
            )
        );

        foreach ($pupils as $pupil) {
            $csv[] = array(
                $pupil->getFirstName(),
                $pupil->getLastName(),
                $pupil->getBirthday(User::BIRTHDAY_FORMAT_FULL),
                $pupil->getGender()
            );
        }

        $string = '';
        $count = count($csv);

        foreach ($csv as $i => $line) {
            $string .= join(';', $line) . ($i < $count - 1 ? "\r\n" : '');
        }

        return $string;
    }

    /*
     * Obtenir le code UAI d'une classe
     */
    public function getUai()
    {
        $classroom = $this->getClassroom();
        $this->setGroup($classroom);
        return $this->getAttribute('UAI');
    }

    /**
     * Méthode qui permet d'importer des élèves dans la classe courante ($this->classroom) depuis un textarea
     *
     * @param array $users le tableau d'utilisateurs déjà formaté
     * @return array tableau contenant les informations sur l'opération d'importation : key 'user_count' = le nombre d'utilisateur que le processus
     * a essayé d'insérer; key 'success_insertion_count' : le nombre d'utilisateur inséré avec succès
     */
    public function importPupilFromTextarea(array $users)
    {
        return $this->userManager->importUserFromTextarea($users, $this->classroom, $this->roleManager->findGroupTypeRoleByType('PUPIL'));
    }

    /**
     * Send sponsorship for a teacher (called after registration)
     * @param User $teacher
     */
    public function sponsorshipAfterRegistration(User $teacher)
    {
        $sponsorships = SponsorshipQuery::create()->filterByStatus('PENDING')->findByEmail($teacher->getEmail());
        if ($sponsorships) {
            foreach ($sponsorships as $sponsorship) {
                //Parrainage pris en compte
                $this->handleSponsorship($sponsorship);
            }
        }
    }

    /**
     * Handle sponsorships subscription
     */
    public function handleSponsorship($sponsorship)
    {
        $sponsorship->activate();
        if ($sponsorship->isValidated()) {
            $sponsorshipper = $sponsorship->getUserRelatedByFromUserId();
            $this->container->get('bns.paas_manager')->generateSubscription(GroupQuery::create()->findOneById($sponsorship->getSchoolId()),
                PaasManager::PREMIUM_SUBSCRIPTION, 'P1M', $sponsorshipper->getLogin(),
                $sponsorshipper->getEmail());

            //Placement de l'utilisateur en REF ENT
            $this->userManager->setUser($sponsorshipper);
            $this->userManager->linkUserWithGroup(
                $sponsorshipper,
                $this->classroom,
                GroupTypeQuery::create()->findOneByType('ENT_REFERENT'));
            $this->userManager->resetRights();
        }
    }

}

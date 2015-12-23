<?php

namespace BNS\App\CoreBundle\Classroom;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Module\IBundleActivation;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkPeer;
use BNS\App\RegistrationBundle\Model\SchoolInformation;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;

/**
 * @author Eymeric Taelman
 * 
 * Classe permettant la gestion des Classe (d'école !)
 */
class BNSClassroomManager extends BNSGroupManager implements IBundleActivation
{	
	protected $userManager;
	protected $api;	
	protected $classroom;
	protected $roleManager;
	protected $mailer;

	/**
	 * @param type $userManager
	 * @param type $api
	 * @param type $roleManager
	 * @param type $domainId
	 * @param type $mailer
	 */
	public function __construct($container, $userManager, $api, $roleManager, $domainId, $mailer)
	{
		$this->container	= $container;
		$this->api			= $api;
		$this->userManager	= $userManager;
		$this->roleManager	= $roleManager;
		$this->domainId		= $domainId;
		$this->mailer		= $mailer;
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
			'type'			=> $classroomGroupTypeRole->getType(),
			'domain_id'		=> $this->domainId,
			'label'			=> $params['label'],
			'validated'		=> isset($params['validated']) && $params['validated'] ? true : false
		);
		$this->classroom = $this->createSubgroupForGroup($newClassroomsParams, $params['group_parent_id']);
		$this->setClassroom($this->classroom);
		
		return $this->classroom;
	}
	
	/**
	 * Création d'une école à partir de School Informations
	 */
	
	public function createSchoolFromInformation($schoolInformation)
	{
		$environment = GroupQuery::create('g')
			->where('g.Label = ?', $this->container->getParameter('application_environment'))
		->findOne();

		if (null == $environment) {
			throw new \RuntimeException('The application environment with label : ' . $this->container->getParameter('application_environment') . ' is NOT found ! Please check your configurations file.');
		}

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

		// Link with env
		$this->linkGroupWithSubgroup($environment->getId(), $school->getId());

		// Update school info with the new linked group_id
		$schoolInformation->setGroupId($school->getId());
		$schoolInformation->save();
		return $school;
	}
	
	/**
	 * @param type $slug
	 * 
	 * @return type
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
			->joinWith('GroupType.GroupTypeI18n')
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
	 * @param type $classroom
	 * 
	 * @return \BNS\App\CoreBundle\Classroom\BNSClassroomManager
	 */
	public function setClassroom($classroom)
	{
		$this->classroom = $classroom;
		$this->setGroup($classroom);
		
		return $this;
	}
	
	/**
	 * @return type
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
		$userId = $user->getId();
		foreach ($teachers as $teacher) {
			if ($userId == $teacher->getId()) {
				$isOneOfMyTeachers = true;
				break;
			}
		}

		return $isOneOfMyTeachers;
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\Group $team
	 * 
	 * @return type
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
		$userId = $user->getId();
		foreach ($pupils as $pupil) {
			if ($userId == $pupil->getId()) {
				$isOneOfMyPupils = true;
				break;
			}
		}

		return $isOneOfMyPupils;
	}
        
	/**
	 * @param type $groupTypeRoleType
	 * 
	 * @return type
	 * 
	 * @throws HttpException
	 */
	public function getRuleWhoFromGroupTypeRole($groupTypeRoleType)
	{
		$groupTypeRole = GroupTypeQuery::create()->findOneByType($groupTypeRoleType);

		if (null == $groupTypeRole) {
			throw new HttpException(500, 'Group type role type given is invalid; must be equals to : TEACHER || PARENTS || PUPIL!');
		}

		return array(
			'domain_id'			=> $this->domainId,
			'group_parent_id'	=> $this->getGroup()->getId(),
			'group_type_id'		=> $groupTypeRole->getId()
		);
	}
       
	/**
	 * @return type
	 */
	public function getPupils()
	{
		return $this->getUsersByRoleUniqueName('PUPIL', true);
	}

	/**
	 * @return type
	 */
	public function getTeachers()
	{
	    return $this->getUsersByRoleUniqueName('TEACHER', true);
	}
	
	/**
	 * @param type $pupil
	 */
	public function assignPupil($pupil)
	{
		$this->roleManager->setGroupTypeRoleFromType('PUPIL')->assignRole($pupil, $this->classroom->getId());
		$this->createParentAccount($pupil);
	}
	
	/**
	 * @param type $teacher
	 */
	public function assignTeacher($teacher)
	{
		$this->roleManager->setGroupTypeRoleFromType('TEACHER')->assignRole($teacher, $this->classroom->getId());
	}

	/**
	 * @param type $parent
	 */
	public function assignParent($parent)
	{
		$this->roleManager->setGroupTypeRoleFromType('PARENT')->assignRole($parent, $this->classroom->getId());
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\User $pupil
	 * @param \BNS\App\CoreBundle\Model\User $parent
	 */
	public function linkPupilWithParent(User $pupil, User $parent)
	{
		PupilParentLinkPeer::createPupilParentLink($pupil, $parent);
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\User $pupil
	 */
	public function createParentAccount(User $pupil)
	{
		$parent = $this->userManager->createUser(
			array(
				'first_name'    => 'M. ou Mme',
				'last_name'     => $pupil->getLastName(),
				'lang'          => $pupil->getLang(),
				'username'		=> $pupil->getUsername() . 'PAR'
			,false)
		);
		$this->assignParent($parent);
		$this->linkPupilWithParent($pupil, $parent);
	}
	
	/**
	 * @return type
	 */
	public function getParentActivatedModules()
	{
		return $this->getActivatedModules(array(
			'role' => 'PARENT',
			'module_peer_default_role_rank' => ModulePeer::DEFAULT_PARENT_RANK
		));
	}
	
	/**
	 * @return type
	 */
	public function getPupilActivatedModules()
	{
		return $this->getActivatedModules(array(
			'role' => 'PUPIL',
			'module_peer_default_role_rank' => ModulePeer::DEFAULT_PUPIL_RANK
		));
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
	 * Méthode qui permet d'importer des élèves dans la classe courante ($this->classroom) depuis un fichier CSV
	 * 
	 * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file le fichier CSV
	 * @param type $format Format Beneylu School (=0) || Format Base Elèves (=1)
	 * @return array tableau contenant les informations sur l'opération d'importation : key 'user_count' = le nombre d'utilisateur que le processus
	 * a essayé d'insérer; key 'success_insertion_count' : le nombre d'utilisateurs insérés avec succès
	 */
	public function importPupilFromCSVFile(UploadedFile $file, $format)
	{
		return $this->userManager->importUserFromCSVFile($file, $format, $this->classroom, $this->roleManager->findGroupTypeRoleByType('PUPIL'));
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\User $teacher
	 * @param \BNS\App\RegistrationBundle\Model\SchoolInformation $schoolInfo
	 * 
	 * @throws \RuntimeException
	 */
	public function sendConfirmation(User $teacher, SchoolInformation $schoolInfo = null)
	{
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
				'school_name'	=> $schoolInfo->getName(),
				'edition_url'	=> $this->container->get('router')->generate('BNSAppClassroomBundle_back', array(), true) // TODO correct URL
			),
			$teacher->getEmail(),
			$teacher->getLang());
		}
		// Tout est correct, on averti l'école que la classe a été créée pour la confirmer
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
			'classroom_name'	=> $this->getClassroom()->getLabel(),
			'confirmation_url'	=> $this->container->get('router')->generate('registration_confirm_classroom', array(
				'token' => $this->getClassroom()->getConfirmationToken()
			), true)
		),
		$schoolInfo->getEmail(),
		$teacher->getLang());
	}
	
	/**
	 * Confirm a classroom, sending e-mail to all classroom's teachers
	 */
	public function confirmClassRoom()
	{
		$classRoom = $this->getClassroom();
		$classRoom->setValidationStatus(GroupPeer::VALIDATION_STATUS_VALIDATED);
		$classRoom->removePendingValidationDate();
		$classRoom->setConfirmationToken(null);
		$classRoom->save();
		
		// Sending e-mail to all teachers in this classroom
		$teachers = $this->getUsersByRoleUniqueName('TEACHER');
		if (!isset($teachers[0])) {
			return;
		}
		
		$this->mailer->sendMultiple('CLASSROOM_CONFIRMED_BY_SCHOOL', array(
			'classroom_name'	=> $classRoom->getLabel()
		),
		$teachers);
	}
}
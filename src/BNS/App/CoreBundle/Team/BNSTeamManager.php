<?php

namespace BNS\App\CoreBundle\Team;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Module\IBundleActivation;

/**
 * @author Eric Chau
 * Classe permettant la gestion des Equipes
 */
class BNSTeamManager extends BNSGroupManager implements IBundleActivation
{	
	protected $api;	
	protected $team;
	protected $roleManager;
	protected $ruleManager;

	public function __construct($api, $roleManager, $ruleManager, $userManager, $domainId)
	{
		$this->api = $api;
		$this->roleManager = $roleManager;
		$this->ruleManager = $ruleManager;
		$this->user_manager = $userManager;
		$this->domainId = $domainId;
	}
	
	public function createTeam(array $params)
	{
		//Vérification des données
		if (!isset($params['label']))
		{
			throw new HttpException(500, 'Please provide a team name!');
		}

		$teamGroupTypeRole = GroupTypeQuery::create()->findOneByType('TEAM');
		$newTeamParams = array(
			'group_type_id' => $teamGroupTypeRole->getId(),
			'type'			=> $teamGroupTypeRole->getType(),
			'domain_id'		=> $this->domainId,
			'label'			=> $params['label']
		);
		$this->team = $this->createSubgroupForGroup($newTeamParams, $params['group_parent_id']);
		$this->setTeam($this->team);
		
		// On donne le rôle d'enseignant dans la nouvelle équipe à tous les enseignants de la classe
		$teacherGroupType = GroupTypeQuery::create()->findOneByType('TEACHER');
		$this->roleManager->assignRoleForUsersInGroup($teacherGroupType->getId(), $params['group_parent_id'], $teacherGroupType->getId(), $this->team->getId());
		
		return $this->team;
	}

	public function findBySlug($slug)
	{
		$team = GroupQuery::create()
			->joinWith('GroupType')
				->useGroupTypeQuery()
				->filterByType('TEAM')
				->endUse()
			->joinWith('GroupType.GroupTypeI18n')
		->findOneBySlug($slug);

		if (null == $team)
		{
			throw new NotFoundHttpException('The group with the slug ' . $slug . ' does not exist !');
		}

		$this->setTeam($team);

		return $team;
	}
	
	public function getTeam()
	{
            return $this->team;
	}
	
	public function setTeam($team)
	{
            $this->team = $team;
            $this->setGroup($team);
	}

	public function getRuleWhoFromGroupTypeRole($groupTypeRoleType)
	{
		$ruleWho = null;
		switch ($groupTypeRoleType)
		{
			case 'MEMBER':
				$ruleWho = array(
					'domain_id'			=> $this->domainId,
					'group_parent_id'	=> $this->getGroup()->getId(),
					'group_type_id'		=> GroupTypeQuery::create()->findOneByType('PUPIL')->getId()
				);
				break;
			case 'OTHER':
				$ruleWho = array(
					'id' => $this->getParent()->getId(),
				);
				break;
			default:
				throw new NotFoundHttpException(500, 'You provide wrong groupTypeRoleType parameter value!');
		}
		
		return $ruleWho;
	}
	
	public function assignPupil($pupil)
	{
            $this->addUser($pupil);
            $this->roleManager->setRole('pupil', $pupil, $this->team);
	}
	
	public function assignTeacher($teacher)
	{
            $this->addUser($teacher);
            $this->roleManager->setRole('teacher', $teacher, $this->team);
	}
	
	public function getMemberActivatedModules()
	{
		return $this->getActivatedModules(array(
			'role' => 'PUPIL',
			'module_peer_default_role_rank' => ModulePeer::DEFAULT_PUPIL_RANK
		));
	}
	
	public function getOtherActivatedModules()
	{
		return $this->getActivatedModules(array(
			'module_peer_default_role_rank' => ModulePeer::DEFAULT_OTHER_RANK
		));
	}
	
	/**
	 * Redéfinition de la méthode addUser(); permet, en plus d'ajouter l'utilisateur dans l'équipe courante, d'également lui assigner
	 * le même rôle dans l'équipe qu'il a dans la classe
	 * 
	 * @param User $user utilisateur à ajouter
	 * @throws HttpException
	 */
	public function addUser(User $user)
	{
		$currentUser = $this->user_manager->getUser();
		$this->user_manager->setUser($user);
		$this->user_manager->resetRights();
		$userRights = $this->user_manager->getRights();
		$this->user_manager->resetRights();
		$this->user_manager->setUser($currentUser);
		$myOwner = $this->getParent();
		if (!isset($userRights[$myOwner->getId()]))
		{
			throw new HttpException(500, 'You try to add an illegal user in your team, forbidden!');
		}
		
		$roles = $userRights[$myOwner->getId()]['roles'];
		if (0 >= count($roles))
		{
			throw new HttpException(500, 'The user yo try to add has not any role in his classroom!');
		}
		
		$this->roleManager->setGroupTypeRole(GroupTypeQuery::create()->findOneById($roles[0]))->assignRole($user, $this->team->getId());
	}
}
<?php

namespace BNS\App\CoreBundle\Team;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Rule\BNSRuleManager;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Module\IBundleActivation;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Eric Chau
 * Classe permettant la gestion des Equipes
 */
class BNSTeamManager extends BNSGroupManager implements IBundleActivation
{
    /**
     * @var Group
     */
    protected $team;

    /**
     * @var BNSRuleManager
     */
    protected $ruleManager;

    public function __construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId, $ruleManager)
    {
        parent::__construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId);

        $this->ruleManager = $ruleManager;
    }

    public function createTeam(array $params)
    {
        //Vérification des données
        if (!isset($params['label'])) {
            throw new HttpException(500, 'Please provide a team name!');
        }

        $teamGroupTypeRole = GroupTypeQuery::create()->findOneByType('TEAM');
        $newTeamParams = array(
            'group_type_id' => $teamGroupTypeRole->getId(),
            'type' => $teamGroupTypeRole->getType(),
            'domain_id' => $this->domainId,
            'label' => $params['label'],
            'lang' => $params['lang'] ?? 'fr'
        );
        $this->team = $this->createSubgroupForGroup($newTeamParams, $params['group_parent_id']);
        $this->setTeam($this->team);


        return $this->team;
    }

    public function findBySlug($slug)
    {
        $team = GroupQuery::create()
            ->joinWith('GroupType')
            ->useGroupTypeQuery()
            ->filterByType('TEAM')
            ->endUse()
            ->findOneBySlug($slug);

        if (null == $team) {
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

    public function assignParent($parent)
    {
        $this->addUser($parent);
        $this->roleManager->setGroupTypeRoleFromType('PARENT')->assignRole($parent, $this->team->getId());
    }

    public function assignPupil($pupil)
    {
        $this->addUser($pupil);
        $this->roleManager->setGroupTypeRoleFromType('PUPIL')->assignRole($pupil, $this->team->getId());
    }

    public function assignTeacher($teacher)
    {
        $this->addUser($teacher);
        $this->roleManager->setGroupTypeRoleFromType('TEACHER')->assignRole($teacher, $this->team->getId());
    }

    public function getPupils($returnObject = true)
    {
        return $this->getUsersByRoleUniqueName('PUPIL', $returnObject);
    }

    public function getTeachers()
    {
        return $this->getUsersByRoleUniqueName('TEACHER', true);
    }

    public function getPupilsParents()
    {
        return $this->getUsersByRoleUniqueName('PARENT', true);
    }

    /**
	 * Renvoie les utilisateurs de la team depuis la centrale
	 *
	 * @return array
	 */
	public function getTeamUsers()
	{
		$route = array(
			'group_id' => $this->team->getId(),
		);

		return $this->api->send('group_get_users', array('route' => $route));
	}

    /**
	 * Renvoie les utilisateurs de la team depuis la centrale
	 *
	 * @return array
	 */
	public function getTeamUsersIds()
	{
		$users = $this->getUsers(true);
        $usersId = array();
        foreach($users as $user) {
            $usersId[] = $user->getId();
        }

		return $usersId;
	}

    /**
     * Redéfinition de la méthode addUser(); permet, en plus d'ajouter l'utilisateur dans l'équipe courante, d'également lui assigner
     * le même rôle dans l'équipe qu'il a dans la classe
     *
     * @param User $user utilisateur à ajouter
     * @param GroupType $roleGroupType
     * @throws HttpException
     *
     * @return boolean
     */
    public function addUser(User $user, GroupType $roleGroupType = null)
    {
        if (!$roleGroupType) {
            $roleId = $user->getHighRoleId();
            $roleGroupType = GroupTypeQuery::create()->filterBySimulateRole(true)->findOneById($roleId);
        }
        if (!$roleGroupType) {
            // we can't addUser to a team without knowing his Role
            return false;
        }

        // Ajout de l'utilisateur dans l'équipe courante avec $roleGroupeType ou son plus haut role
        $this->roleManager->setGroupTypeRole($roleGroupType)->assignRole($user, $this->team->getId());

        $this->api->resetGroup($this->team->getId(), false);

        return true;
    }
}

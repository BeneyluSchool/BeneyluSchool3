<?php

namespace BNS\App\CoreBundle\Group;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Model\RankDefaultQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Module\BNSModuleManager;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\BlogPeer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des Groupes / groupes types
 */
class BNSGroupManager
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var BNSApi
     */
    protected $api;
    protected $domainId;
    protected $group_id;
    protected $subgroups;
    protected $rules;
    protected $groupDatas;
    protected $groupTypeDatas;

    /**
     * @var BNSRoleManager
     */
    protected $roleManager;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var BNSModuleManager
     */
    protected $moduleManager;

    /**
     * Au cas où on change de current group, on enregistre l'ID en temps de key
     *
     * @var array<GroupId, array<Group>>
     */
    protected $parents;

    /** @var Group $group */
    protected $group;

    protected $modeBeta = false;

    /**
     * @param ContainerInterface $container
     * @param BNSRoleManager $roleManager
     * @param BNSUserManager $userManager
     * @param BNSApi $api
     * @param BNSModuleManager $moduleManager
     * @param int $domainId
     */
    public function __construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId)
    {
        $this->container = $container;
        $this->api = $api;
        $this->roleManager = $roleManager;
        $this->userManager = $userManager;
        $this->moduleManager = $moduleManager;
        $this->domainId = $domainId;
        $this->group = null;
        $this->group_id = null;
        $this->parents = array();

        if ($container->hasParameter('bns_beta_enabled')) {
            $this->modeBeta = $container->getParameter('bns_beta_enabled');
        }
    }

	///////////   FONCTIONS LIEES AUX GROUPES DIRECTEMENT  \\\\\\\\\\\

	/**
	 * Set du groupe, généralement depuis les controleurs
	 *
	 * @param Group $group Groupe en question
	 *
	 * @return $this
	 */
	public function setGroup($group)
	{
		$this->group = $group;
		unset($this->rules);

		return $this;
	}

	/**
	 * @param int $group_id
	 *
	 * @return \BNS\App\CoreBundle\Group\BNSGroupManager
	 */
	public function setGroupById($group_id)
	{
		$group = GroupQuery::create()->findOneById($group_id);

		if (null == $group) {
            return null;
		}

		$this->group = $group;

		return $this;
	}

    public function hasGroup()
    {
        return isset($this->group);
    }

    /*
	 * Get du group
	 *
	 * @return Group le groupe en question
	 */
	public function getGroup()
	{
		if (!$this->hasGroup()) {
			$stacktrace = debug_backtrace();
			throw new HttpException(500, sprintf("Group is NOT set, please set it before using %s::%s() method", $stacktrace[1]['class'], $stacktrace[1]['function']));
		}

		return $this->group;
	}



        /**
	 * @param string $id
	 */
	public function getGroupFromCentral($id)
	{
		return $this->api->send('group_read',
			array(
				'route' =>  array(
					'id' => $id
				)
			)
		);
	}

    //Renvoie le projet auquel fait partie l'école, construit sur la base des Ids parents du group en cours
    public function getProjectInfo($info = null)
    {
        $projectInfo = $this->container->get('bns_common.manager.project_info');
        foreach ($this->getAncestors() as $ancestor) {
            $groupId = $ancestor->getId();

            if ($projectInfo->hasProjectInfoForGroup($groupId)) {
                if (null === $info) {
                    return $projectInfo->getProjectInfo($groupId, $info);
                }

                $value = $projectInfo->getProjectInfo($groupId, $info);

                if (null === $value) {
                    return false;
                }

                return $value;
            }
        }

        $groupId = $this->getGroup()->getId();
        if ($projectInfo->hasProjectInfoForGroup($groupId)) {
            if (null === $info) {
                return $projectInfo->getProjectInfo($groupId, $info);
            }

            $value = $projectInfo->getProjectInfo($groupId, $info);

            if (null === $value) {
                return false;
            }

            return $value;
        }

        return false;
    }

    public function getProjectInfoCurrentFirst($info = null)
    {
        $projectInfo = $this->container->get('bns_common.manager.project_info');
        $groupId = $this->getGroup()->getId();
        if ($projectInfo->hasProjectInfoForGroup($groupId)) {
            if (null === $info) {
                return $projectInfo->getProjectInfo($groupId, $info);
            }

            $value = $projectInfo->getProjectInfo($groupId, $info);

            if (null === $value) {
                return false;
            }

            return $value;
        }

        return $this->getProjectInfo($info);
    }

    public function isOnPublicVersion()
    {
        return $this->getProjectInfo('public_version') === true;
    }

	/*
	 * Renvoie les informations d'un groupe de manière sur (groupe en local ou sur centrale)
	 */
	public function getSafeGroup($groupId)
	{
		if(!isset($this->groupDatas[$groupId])){
			$group = GroupQuery::create()->findOneById($groupId);
			if ($group) {
				$this->groupDatas[$groupId] = array(
					'id' => $group->getId(),
					'label' => $group->getLabel(),
					'group_type_id' => $group->getGroupTypeId()
				);
			}
			else {
				//Group forcément sur la centrale
				$this->groupDatas[$groupId] = $this->getGroupFromCentral($groupId);
			}
		}
		return $this->groupDatas[$groupId];
	}

	public function getAllGroups(){
		$groups = GroupQuery::create()->find();
		return $groups;
	}

	public function getId()	 {
		return $this->getGroup()->getId();
	}

	/*
	 * Recherche d'un groupe depuis son Id
	 * @param int $group_id L'ID du groupe recherché
	 * @return Group Le groupe recherché
	 */
	public function findGroupById($id){
		if($this->group_id == $id && isset($this->group))
			return $this->group;
		else{
			$this->group = GroupQuery::create()->findOneById($id);
			return $this->group;
		}
	}

	public function findGroupBySlug($slug)
	{
		$group = GroupQuery::create()
			->joinWith('GroupType')
			->add(GroupPeer::SLUG, $slug)
		->findOne();

		if (null == $group)
		{
			throw new HttpException(500, 'No group exists for slug given: '.$slug);
		}

		return $group;
	}

	/**
	 * @param array		$params
	 *	- (optionnal) domain_id
	 *  - type
	 *  - label
	 *  - (optionnal) attributes (array: key, value)
	 * @param boolean	$generateModules Si vrai, la méthode créer les modules associés au groupe. Si faux aucun module ne sera créé pour le groupe.
	 * @return Group
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createGroup($params, $generateModules = true)
	{
		// Vérification que nous avons assez d'infos : domaine, group_type, label
		if (!isset($params['domain_id'])) {
			$params["domain_id"] = $this->domainId;
		}

		if (
			isset($params['domain_id']) &&
			isset($params['type']) &&
			isset($params['label'])
		) {
			$label = $params['label'];
			$type = $params['type'];

			$group_type_id = GroupTypeQuery::create()->findOneByType($type)->getId();
			$values = array(
				'label'         => $label,
				'group_type_id' => $group_type_id,
				'domain_id'     => $params['domain_id']
			);

			$response = $this->api->send('group_create',array('values' => $values));
            if(isset($this->container))
            {
                if(
                    $this->container->hasParameter('registration.current_year') &&
                    $type == "CLASSROOM"
                )
                {
                    $params['attributes']['CURRENT_YEAR'] = $this->container->getParameter('registration.current_year');
                }
            }

			$values['group_id'] = $response['id'];
			$values['validated'] = isset($params['validated']) && $params['validated'] ? true : false;
			$values['attributes'] = isset($params['attributes']) ? $params['attributes'] : array();
            $values['import_id'] = isset($params['import_id']) ? $params['import_id'] : false;
            $values['aaf_id'] = isset($params['aaf_id']) ? $params['aaf_id'] : false;
            $values['aaf_academy'] = isset($params['aaf_academy']) ? $params['aaf_academy'] : false;

            foreach($params as $key => $param)
            {
                if(!isset($values[$key]))
                {
                    $values[$key] = $param;
                }
            }

            if (!isset($values['country'])) {
                $countries = $this->container->getParameter('preferred_countries');
                $values['country'] = isset($countries[0]) ? $countries[0] : 'FR';
            }

			// Création des données indispensables aux groupes, quelqu'ils soient
			$newGroup = GroupPeer::createGroup($values);

			// Création des modules associés au groupe
			if ($generateModules) {
				AgendaPeer::createAgenda($values);
                MediaFolderGroupPeer::createMediaFolderGroup($values);
				BlogPeer::create($values);
			}

			$this->setGroup($newGroup);

            if(BNSAccess::isConnectedUser())
            {
                if($newGroup->getType() == 'CLASSROOM')
                {
                    $this->container->get('bns.right_manager')->trackAnalytics('REGISTERED_CLASSROOM', $newGroup);
                }elseif($newGroup->getType() == 'SCHOOL'){
                    $this->container->get('bns.right_manager')->trackAnalytics('REGISTERED_ACCOUNT', $newGroup);
                }
            }

			return $newGroup;
		}
		else {
			throw new \InvalidArgumentException('Not enough datas to create group : please provide label, type and domain_id');
		}
	}

    /*
     * Fonction prenant en paramètre un lot de groupes en "commande" et traitant en masse leur création
     * @params array $groups Les groupes sous la forme de tableau de tableau
     */
    public function createGroups($askedGroups, $impotData = null)
    {
        $defaultCountry = $this->container->getParameter('preferred_countries')[0];
        foreach($askedGroups as &$tmp)
        {
            $tmp['domain_id'] = $this->domainId;
        }

        $response = $this->api->send('groups_create',array('values' => array('groups' => $askedGroups)));
        $count = 0;

        foreach($response as $group)
        {
            $askedGroup = $askedGroups[$count];

            if(isset($this->container))
            {
                if(
                    $this->container->hasParameter('registration.current_year') &&
                    $askedGroup['type_unique_name'] == "CLASSROOM"
                )
                {
                    $askedGroup['attributes']['CURRENT_YEAR'] = $this->container->getParameter('registration.current_year');
                }
            }

            $askedGroup['group_id'] = $group['id'];
            $askedGroup['validated'] = isset($askedGroup['validated']) && $askedGroup['validated'] ? true : false;

            if (!(isset($askedGroup['country'])) && $askedGroup['country']) {
                $askedGroup['country'] = $defaultCountry;
            }

            // Création des données indispensables aux groupes, quelqu'ils soient
            $newGroup = GroupPeer::createGroup($askedGroup);

            // Création des modules associés au groupe
            AgendaPeer::createAgenda($askedGroup);
            MediaFolderGroupPeer::createMediaFolderGroup($askedGroup);
            BlogPeer::create($askedGroup);

            if(!isset($askedGroup['parent_id']) && (isset($askedGroup['parent_filter']['INSEE_ID']) || isset($askedGroup['parent_filter']['CIRCO_ID'])))
            {
                //On recherche le groupe parent qui a été commandé
                if(isset($askedGroup['parent_filter']['INSEE_ID']))
                {
                    $parent = GroupQuery::create()
                        ->filterBySingleAttribute('INSEE_ID',$askedGroup['parent_filter']['INSEE_ID'])
                        ->findOne();
                    $this->addParent($group['id'],$parent->getId());
                }elseif(isset($askedGroup['parent_filter']['CIRCO_ID']))
                {
                    $parent = GroupQuery::create()
                        ->filterBySingleAttribute('CIRCO_ID',$askedGroup['parent_filter']['CIRCO_ID'])
                        ->findOne();
                    $this->addParent($group['id'],$parent->getId());
                }else{

                }
            }
            $count++;
        }
    }

    public function updateGroupLanguage($language)
    {
        $this->getGroup()->setLang($language);
        $this->getGroup()->save();
        //Mise à jour pour tous les utilisateurs élèves du groupe
        $pupilIds = $this->getUsersByRoleUniqueNameIds('PUPIL');
        $users = UserQuery::create()
            ->filterById($pupilIds)
            ->update(array('Lang' => $this->getGroup()->getLang()));
    }


	public function updateGroup($params)
	{
		$group = $this->getGroup();
		//On ballaie tous les params pour mettre à jour l'objet en local, puis on envoie les modifications à la centrale
		if(isset($params['label'])){
			$group->setLabel($params['label']);
		}
		$group->save();

		$datas['id'] = $group->getId();
		$datas['domain_id'] = $this->domainId;
		$datas['label'] = $group->getLabel();
		$datas['group_type_id'] = $group->getGroupTypeId();

		$withParent = false;

		if(isset($params['parent_ids'])){
			/**
                         * Nous resetons tous les parents pour mettre à jour avec les nouveaux
                         */
			$oldParents = $this->getParents();
                        foreach ($oldParents as $oldParent) {
                $this->api->resetGroup($oldParent->getId(), false);
                        }

			$datas['group_parent_ids'] = $params['parent_ids'];
			$withParent = true;

			unset($this->parents[$this->getGroup()->getId()]);
		}
		$this->api->send('group_update',array('route' => array('group_id' => $group->getId()),'values' => $datas));

		unset($this->groupDatas[$group->getId()]);


		if($withParent){
			unset($this->parents[$this->getGroup()->getId()]);
			$this->api->resetGroup($group->getId());

                        $parents = $this->getParents();
                        foreach ($parents as $parent) {
                $this->api->resetGroup($parent->getId(), false);
                        }
		}

        if($group->getAgenda()) {
            $group->getAgenda()->setTitle($group->getLabel())->save();
        }

        if($group->getMediaFolderRoot()) {
            $group->getMediaFolderRoot()->setLabel($group->getLabel())->save();
        }
    }

	//TODO AME
	public function updateParents($parentIds)
	{
            $params['parent_ids'] = $parentIds;
            $this->updateGroup($params);
	}

    public function addParent($groupId, $parentId)
    {
        $values = array(
            'parent_id' => $parentId
        );

        $this->api->send(
            'group_add_parent', array('values' => $values, 'route' => array('group_id' => $groupId))
        );
    }

    public function deleteParent($groupId, $parentId)
    {
        unset($this->parents);
        $this->api->send(
            'group_delete_parent', array('route' => array('group_id' => $groupId, 'parent_id' => $parentId))
        );
        unset($this->parents);
    }

	public function createEnvironment($params)
	{
		if(isset($params['label']))
		{
			$environmentGroupTypeId = GroupTypeQuery::create('gt')
				->findOneByType('ENVIRONMENT')
			->getId();

			$values = array(
				'label'         => $params['label'],
				'group_type_id' => $environmentGroupTypeId,
				'domain_id'     => $this->domainId,
			);

			$response = $this->api->send('group_create',array('values' => $values));

			$values['group_id'] = $response['id'];

			$newEnvironment = GroupPeer::createGroup($values);

			return $newEnvironment;
		}
		else
		{
			throw new HttpException(500, 'Not enough datas to create group: please provide label.');
		}
	}

    public function getEnvironment($group = null)
    {
        /** @var Group $parent */
        foreach ($this->getAncestors($group) as $parent) {
            if ($parent->getType() == "ENVIRONMENT") {
                return $parent;
            }
        }

        return false;
    }



        public function getGroupeType($groupId)
        {
            if (null == $groupId)
            {
                throw new InvalidArgumentException('You provide invalide group (potential issue origin: no id, equals to null');
            }

            $groupType = \BNS\App\CoreBundle\Model\GroupTypeQuery::create()
                        ->useGroupQuery()
                            ->filterById($groupId)
                        ->endUse()
                        ->findOne();

            if (null == $groupType)
            {
                throw new HttpException(500, 'No group exists for id given: '.$groupId);
            }

            return $groupType;
        }

	////// FONCTIONS LIEES AUX REGLES \\\\\\\

	/*
	 * retourne les règles liées au groupe (lui plus ses parents)
	 */
	public function getRules($type = "all",$useCache = true)
	{
		if(!isset($this->rules)){
			$route = array(
				'where-group_id' => $this->getId()
			);
			$this->rules = $this->api->send(
				"rule_search",
                array('route' => $route),
                $useCache
			);
		}

		$rules = $this->rules;

		switch($type){
			case 'all':
				//all = rule_where.group_id => group.id
				return $rules;
			break;
			case 'mine':
				//Mine = celles du groupe uniquement = rule_where.group_type_id == NULL
				$returnedRules = array();
				foreach($rules as $rule){
					if(!isset($rule['rule_where']["group_type_id"]) || $rule['rule_where']["group_type_id"] == null){
						$returnedRules[] = $rule;
					}
				}
				return $returnedRules;
			break;
			case 'delegated':
				//delegated = celles dédiées aux sous groupes du groupe = rule_where.group_type_id != NULL
				$returnedRules = array();

				foreach($rules as $rule){
					if(isset($rule['rule_where']["group_type_id"]) && $rule['rule_where']["group_type_id"] != null){
						$returnedRules[] = $rule;
					}
				}
				return $returnedRules;
			break;
			case 'rooted':
				//rooted = celles récupérées d'autres groupes (parents) = pour tous les parents où rule_where.group_type_id != thid.groupTypeId
				$returnedRules = array();
				$myGroupTypeId = $this->getGroup()->getGroupTypeId();
				foreach($this->getAncestors() as $parentGroup){
					$this->setGroup($parentGroup);
					$parentRules = $this->getRules();
					foreach($parentRules as $parentRule){
						if(isset($parentRule['rule_where']["group_type_id"]) && $parentRule['rule_where']['group_type_id'] == $myGroupTypeId){
							$returnedRules[] = $parentRule;
						}
					}
				}
				return $returnedRules;
			break;
		}
	}





	///////////   FONCTIONS LIEES AUX TYPES DE GROUPES GROUPES DIRECTEMENT  \\\\\\\\\\\


	/*
	 * Renvoie les informations d'un groupe de manière sur (type de groupe en local ou sur centrale)
	 */
	public function getSafeGroupType($groupTypeId){

		if(!isset($this->groupTypeDatas[$groupTypeId])){
			$groupType = GroupTypeQuery::create()->findOneById($groupTypeId);
			if($groupType){
				$this->groupTypeDatas[$groupTypeId] = array(
					'id' => $groupType->getId(),
					'label' => $groupType->getLabel(),
					'centralize' => $groupType->getCentralize(),
					'simulate_role' => $groupType->getSimulateRole(),
					'type' => $groupType->getType()
				);
			}else{
				//Group forcément sur la centrale
				$this->groupTypeDatas[$groupTypeId] = $this->api->send('grouptype_read',array('route' => array('id' => $groupTypeId)));
			}
		}
		return $this->groupTypeDatas[$groupTypeId];
	}

	/*
	 * Création d'un type de groupe : toujours passer par cette méthode pour créer un type de groupe
	 * @params array $params
	 * @return GroupType
	 */
	public function createGroupType($values, $createAuth = true)
	{
		// Vérification que nous avons assez d'infos : #domaine, label , type, centralize
		if(!isset($values['type']) || !isset($values['centralize']) || !isset($values['simulate_role'])) {
			throw new \InvalidArgumentException('Not enough data to create group type, please provide label, type and centralize parameters !');
		}

		if (!isset($values['domain_id'])) {
			$values['domain_id'] = null;
		}
		if (!isset($values['description'])) {
			$values['description'] = '';
		}

		// check if group type exist in auth
		$existInAuth = true;
		try {
			$this->api->send('grouptype_read_type', [
				'route' => [
					'type' => $values['type']
				],
			]);
		} catch (NotFoundHttpException $e) {
			$existInAuth = false;
		}

		if ($createAuth && !$existInAuth) {
			// Send to API
			$response = $this->api->send('grouptype_create',array(
				'values' => $values
			));

			$values['group_type_id'] = $response['id'];
		}
		else {
			$response = $this->api->send('grouptype_read_type', array(
				'route' => array(
					'type' => $values['type']
				)
			));

			$values['group_type_id'] = $response['id'];
		}

		$newGroupType = GroupTypePeer::createGroupType($values);

		// Création des permissions / rangs associés en Mode CRUD
		$groupModule = ModuleQuery::create()->findOneByUniqueName('GROUP');
		if (null == $groupModule) {
			return $newGroupType;
		}

		$groupModuleId = $groupModule->getId();

		if ($createAuth) {
			// Création des permissions
			$createPermission = $this->createGroupTypePermission($values['type'], 'CREATE', $groupModuleId);
			$editPermission = $this->createGroupTypePermission($values['type'], 'EDIT', $groupModuleId);
			$deletePermission = $this->createGroupTypePermission($values['type'], 'DELETE', $groupModuleId);
            $viewPermission = $this->createGroupTypePermission($values['type'], 'VIEW', $groupModuleId);
            $giveRightsPermission = $this->createGroupTypePermission($values['type'], 'GIVE_RIGHTS', $groupModuleId);
			//Si c'est un rôle, on ajoute la permission "voir comme"
            if($newGroupType->getSimulateRole() == true)
            {
                $viewAsPermission = $this->createGroupTypePermission($values['type'], 'VIEW_AS', $groupModuleId);

            } else {
                $createChildPermission = $this->createGroupTypePermission($values['type'], 'CREATE_CHILD', $groupModuleId);
                $createRulePermission = $this->createGroupTypePermission($values['type'], 'CREATE_RULE', $groupModuleId);
            }

			// Création du rang manage
			$manageRank = $this->createGroupTypeRank($values['type'], 'TYPE_MANAGE', $groupModuleId);

			// Les liaisons permissions / rangs
            $this->moduleManager->addRankPermission($manageRank->getUniquename(), $createPermission->getUniqueName());
			$this->moduleManager->addRankPermission($manageRank->getUniquename(), $editPermission->getUniqueName());
			$this->moduleManager->addRankPermission($manageRank->getUniquename(), $deletePermission->getUniqueName());
            $this->moduleManager->addRankPermission($manageRank->getUniquename(), $viewPermission->getUniqueName());
            $this->moduleManager->addRankPermission($manageRank->getUniquename(), $giveRightsPermission->getUniqueName());
            //Si c'est un rôle, on ajoute la permission "voir comme"
            if($newGroupType->getSimulateRole() == true)
            {
                $this->moduleManager->addRankPermission($manageRank->getUniquename(), $viewAsPermission->getUniqueName());

            } else {
                $this->moduleManager->addRankPermission($manageRank->getUniquename(), $createChildPermission->getUniqueName());
                $this->moduleManager->addRankPermission($manageRank->getUniquename(), $createRulePermission->getUniqueName());
            }
		}

		return $newGroupType;
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param int	 $moduleId
	 * @param string $label is deprecated
	 *
	 * @return
	 */
	private function createGroupTypePermission($type, $name, $moduleId, $label = null)
	{
        if ($label) {
            @trigger_error('createGroupTypePermission label parameter is deprecated', E_USER_DEPRECATED);
        }

		return $this->moduleManager->createPermission(
			array(
				'unique_name' => $type . '_' . $name,
				'module_id' => $moduleId,
			)
		);
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param int	 $moduleId
	 * @param string $label is deprecated
	 *
	 * @return
	 */
	private function createGroupTypeRank($type, $name, $moduleId, $label = null)
	{
        if ($label) {
            @trigger_error('createGroupTypeRank label parameter is deprecated', E_USER_DEPRECATED);
        }

		return $this->moduleManager->createRank(
			array(
				'unique_name' => $type . '_' . $name,
				'module_id' => $moduleId,
			)
		);
	}


	///////////   FONCTIONS LIEES AUX LIAISONS   \\\\\\\\\\\


	/**
	 * Retourne la liste des groupes fils du groupe courant ($this->group)
	 * /!\ L'attribut $this->group doit être impérativement défini sinon une exception sera levée
	 *
	 * @return array|Group[]
	 */
	public function getSubgroups($returnObject = true, $returnSimulateRoleGroup = true, $groupTypeId = false)
	{
		// On vérifie qu'un groupe est bien setté sinon une exception est levée dans la méthode getGroup()
		$group = $this->getGroup();

		// On set les paramètres à fournir à la route dans un tableau
		$route = array(
			'id'	=> $group->getId(),
		);

		$response = $this->api->send('group_subgroups', array(
			'route'	=> $route
		));

		// On test si oui ou non le groupe a un/des sous-groupe(s)
		if (count($response) > 0 && true === $returnObject) {
			// Le groupe a un/des sous-groupe(s), on construit les objets de type Group à partir des informations reçues
			$subgroupIds = array();
			$subgroupsRole = array();
			foreach($response as $r) {
				$groupType = null;
                if($returnSimulateRoleGroup)
                {
                    try {
                        $groupType = $this->roleManager->getGroupTypeRoleFromId($r['group_type_id']);
                    }
                    catch (Exception $e) { } // Aucun traitement à faire
                }

				if ($returnSimulateRoleGroup && $groupType != null && ($groupType->getSimulateRole() || false === $groupType->getIsRecursive())) {
					$subgroupRole = new Group();
					$subgroupRole->setId($r['id']);
					$subgroupRole->setLabel($r['label']);
					$subgroupRole->setGroupType($groupType);
					$subgroupsRole[] = $subgroupRole;
				}
				elseif ($groupType == null) {
					$subgroupIds[] = $r['id'];
				}
			}

			$responseQuery = GroupQuery::create()
                ->filterByArchived(false)
                ->filterByValidationStatus(GroupPeer::VALIDATION_STATUS_REFUSED, \Criteria::NOT_EQUAL)
				->joinWith('GroupType')
				->add(GroupPeer::ID, $subgroupIds, \Criteria::IN)
				->orderByLabel();

			if($groupTypeId){
				$responseQuery->filterByGroupTypeId($groupTypeId);
			}
            if ($this->getCheckGroupEnabled()) {
                $responseQuery->filterByValidationStatus(array(GroupPeer::VALIDATION_STATUS_VALIDATED, GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION));
            }

			$response = $responseQuery->find();

			if ($returnSimulateRoleGroup) {
				foreach($subgroupsRole as $subgroupRole) {
					$response[] = $subgroupRole;
				}

			}
		}

		return $response;
	}

        /**
	 * Retourne la liste des groupes fils du groupe courant (en récursif)
	 * /!\ L'attribut $this->group doit être impérativement défini sinon une exception sera levée
     *
	 * @return type
	 */
    public function getAllSubgroups($id, $groupTypes = null, $returnObjects = true)
	{
        $subgroupIds = $this->getOptimisedAllSubGroupIds($id);

        $responseQuery = GroupQuery::create()
            ->filterById($subgroupIds, \Criteria::IN)
            ->orderByLabel();

        if ($groupTypes) {
            $responseQuery
                ->useGroupTypeQuery()
                    ->filterByType($groupTypes)
                ->endUse();
        }

        if (!$returnObjects) {
            return $responseQuery->select('Id')->find()->getArrayCopy();
        } else {
            return $responseQuery->find();
        }
    }

    /**
     * @deprecated use getOptimisedAllSubGroupIds
     * @param $id
     * @return array
     */
    public function getAllSubgroupIds($id)
    {
        return $this->getOptimisedAllSubGroupIds($id);
        // On set les paramètres à fournir à la route dans un tableau
//        $route = array(
//            'id' => (int)$id,
//        );
//
//        $response = $this->api->send(
//            'group_allsubgroups',
//            array(
//                'route' => $route
//            )
//        );
//
//        $subgroupIds = array();
//        if (is_array($response)) {
//            //Pour chaque groupe en réponse
//            foreach ($response as $r) {
//                // Le groupe a un/des sous-groupe(s), on construit les objets de type Group à partir des informations reçues
//                $subgroupIds[] = $r['Id'];
//            }
//        }
//
//        return $subgroupIds;
            }

    public function getOptimisedAllSubGroupIds($id)
    {
        // On set les paramètres à fournir à la route dans un tableau
        $route = array(
            'id' => (int)$id,
        );

        try {
            $response = $this->api->send(
                'group_allsubgroupids',
                array(
                    'route' => $route
                )
            );

            if ($response && is_array($response)) {
                return $response;
            }
        } catch (\Exception $e) {}

        return [];
    }

	/**
	 * Retourne la liste des groupes fils qui sont d'un type particulier ($type)
	 *
	 * @param String $type est une chaîne de caractère représentant le type de groupe que l'on recherche (CLASSROOM/SCHOOL/TEAM/PARTNERSHIP/CUSTOM/ENVIRONMENT)
	 * @param boolean $returnObject
	 * @return Array<Group> liste des groupes fils du groupe courant ($this->group) de type $type
	 * @throws HttpException
	 */
	public function getSubgroupsByGroupType($type, $returnObject = true)
	{
		$groupType = GroupTypeQuery::create()
			->add(GroupTypePeer::TYPE, $type)
		->findOne();
		if (null === $groupType) {
			throw new InvalidArgumentException('You provide an invalid type!');
		}

		// on récupère tous les sous-groupes du groupe courant
		$response = $this->getSubgroups($returnObject);
		if (count($response) > 0) {
			$subgroupsSorted = array();
			foreach ($response as $r)
			{
				if ($r instanceof Group) {
					if ($r->getGroupTypeId() == $groupType->getId()) {
						$subgroupsSorted[] = $r;
					}
				}
				elseif ($r['group_type_id'] == $groupType->getId()) {
					$subgroupsSorted[] = $r;
				}
			}

			$response = $subgroupsSorted;
		}

		return $response;
	}

	/**
	 * Retourne les groupes parents du groupe $this->group
	 *
	 * @return Group le groupe parent recherché
	 */
	public function getParents($group = null)
	{
            if ($group === null) {
                $group = $this->getGroup();
            }
            if (isset($this->parents[$group->getId()])) {
                    return $this->parents[$group->getId()];
            }

            $parentsId = $this->getParentsId($group);

            $parents = array();
            if (null != $parentsId) {
                foreach ($parentsId as $parentId) {
                    $parents[] = GroupQuery::create()
                        ->joinWith('GroupType')
                        ->orderByLabel()
                    ->findOneById($parentId['id']);
                }
            }
            $this->parents[$group->getId()] = $parents;

            return $parents;
	}

    public function getParentsId($group = null)
    {
        if ($group === null) {
            $group = $this->getGroup();
        }

        $route = array(
            'id' => $group->getId(),
        );

        return $this->api->send('group_parent', array(
            'route'	=> $route
        ));
    }

    public function getPartnersIds()
    {
        $route = array(
            'id' => $this->getGroup()->getId(),
        );
        $response = $this->api->send('group_partners', array(
            'route'	=> $route
        ),false);
        $partnersIds = array();

        //Pour chaque groupe en réponse
	    if(is_array($response))
	    {
		    foreach($response as $r) {
			    // Le groupe a un/des sous-groupe(s), on construit les objets de type Group à partir des informations reçues
			    $partnersIds[] = $r['id'];
		    }
	    }

        return $partnersIds;
    }

    /**
     * @return array|Group[]
     */
    public function getPartners()
    {
        return GroupQuery::create()
            ->add(GroupPeer::ID, $this->getPartnersIds(), \Criteria::IN)
            ->orderByLabel()
            ->find();
    }

    /**
     * Renvoie le premier parent, indispensable dans les structure simples
     * A utiliser avec parcimonie
     * @return null|Group
     */
    public function getParent()
    {
        $parents = $this->getParents();
        return isset($parents[0]) ? $parents[0] : null;
    }


    /**
	 * Récupère la liste de tous les parents (le parent du groupe courant, le parent du groupe parent du groupe courant, etc.)
	 * du groupe courant ($this->group)
	 *
	 * @return Array<Group> Liste des parents du groupe parent
	 */
	public function getAncestors($group = null)
	{
            if ($group === null) {
                $group = $this->getGroup();
            }
            $parents = $this->getParents($group);
            $result = $parents;
            foreach ($parents as $parent) {
                $result = array_merge($result, $this->getAncestors($parent));
            }
            return $result;
	}

	/**
	 * Vérifie que le groupe fourni en paramètre est bien un sous-groupe du groupe $this->group
	 * @param Group $subgroup groupe dont on veut vérifier s'il appartient à la liste des sous-groupes de $this->group ou non
	 * @return boolean vaut true si c'est un sous-groupe du groupe courant, false sinon
	 */
	public function isSubgroup(Group $subgroup)
	{
		$isSubgroup = false;
		foreach ($this->getSubgroups() as $group)
		{
			if ($group->getId() == $subgroup->getId())
			{
				$isSubgroup = true;
				break;
			}
		}

		return $isSubgroup;
	}

	/**
	 * Créer un sous-groupe et le lie au groupe référencer par le groupParentId
	 * @param array $subgroupParams tableau contenant les informations nécessaire pour la création de groupe (type de groupe, label, domain_id, [attributes])
	 * @param int $groupParentId l'id du groupe parent avec lequel on veut lier le nouveau groupe
	 * @return Group retourne le sous-groupe qui vient d'être créé
	 */
	public function createSubgroupForGroup(array $subgroupParams, $groupParentId, $autoGenerateFeatures = true)
	{
		$newSubgroup = $this->createGroup($subgroupParams, $autoGenerateFeatures);

		$this->linkGroupWithSubgroup($groupParentId, $newSubgroup->getId());

		return $newSubgroup;
	}

	/**
	 * Créer la liaison groupe parent/groupe fils entre les ids de groupe fournis en paramètre ($groupParentId, $groupChildId)
	 *
	 * @param type $groupParentId l'id du groupe parent
	 * @param type $groupChildId l'id du groupe fils
	 */
	public function linkGroupWithSubgroup($groupParentId, $groupChildId)
	{
		// On vérifie que les paramètres requis pour créer la liaison groupe parent/enfant sont fournis par l'utilisateur
		if (!$groupParentId && !$groupChildId)
		{
			throw new HttpException(500, 'Not enough datas to create group : please provide group_parent_id and group_child_id');
		}

		// On fournit dans un tableau les paramètres nécessaires pour effectuer la requête à la centrale
		$route = array(
			'group_id' 	=> $groupParentId
		);

		$values = array(
			"id" => $groupChildId,
		);

		$response = $this->api->send('group_subgroup_link', array(
			'route'		=> $route,
			'values'	=> $values
		));

        $child = GroupQuery::create()->findOneById($groupChildId);
        switch($child->getType())
        {
            case 'SCHOOL':
                $this->container->get('bns.analytics.manager')->track('REGISTERED_ACCOUNT',$child);
                break;
            case 'CLASSROOM':
                $this->container->get('bns.analytics.manager')->track('REGISTERED_CLASSROOM', $child);
                break;
        }

	}

	///////////////   FONCTIONS LIEES AUX UTILISATEURS   \\\\\\\\\\\\\\\\\\
	/**
	 * Retourne la liste des utilisateurs du groupe courant ($this->group)
	 *
	 * @param boolean $deprecatedArgument n'est plus d'actualité mais laisser pour ne pas casser la signature
	 * la méthode retourne directement la réponse donnée par la centrale
	 * @return Array<Users> Liste des utilisateurs du groupe courant
	 */
	public function getUsers($deprecatedArgument = true)
	{
        return  UserQuery::create()
            ->filterByArchived(false)
            ->joinWith('Profile')
            ->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
            ->add(UserPeer::ID, $this->getUsersIds(), \Criteria::IN)
            ->orderByLastName()
        ->find();
	}

	/**
	 * Permet de récupérer sous forme d'objet BNS\App\CoreBundle\Model\Group tous les sous-groupes rôles
	 * du groupe courant ($this->group); pour chacun des sous-groupes les utilisateurs sont setté (vous pouvez faire un $subgroupRole->getUsers())
	 *
	 * @param array<string> $roleFilters tableau contenant seulement les rôles dont vous souhaitez récupérer;
	 * par défaut, la méthode renvoi tous les sous-groupes rôles avec ses utilisateurs
	 *
	 * @return array<Group> tableau d'objet de type Group qui correspondent aux sous-groupes rôle du groupe courant
	 */
	public function getSubgroupRoleWithUsers(array $roleFilters = array())
	{
		$roleSubgroups = array();


            $subgroups = $this->getSubgroups();



		foreach($subgroups as $subgroup){
			if (count($roleFilters) > 0 && !in_array($subgroup->getGroupType()->getType(), $roleFilters)) {
				continue;
			}

			if ($subgroup->getGroupType()->getSimulateRole() || !$subgroup->getGroupType()->getIsRecursive()) {
				$subgroup->setUsers($this->setGroup($subgroup)->getUsers(true));
				$roleSubgroups[] = $subgroup;
			}
		}
		return $roleSubgroups;
	}

	/**
	 * Renvoie les ids des utilisateurs du groupe
	 *
	 * @return array<Integer>
	 */
	public function getUsersIds()
	{
        $ids = array();
        $users = $this->api->send('group_get_users', array('route' => array('group_id' => $this->getGroup()->getId())));

        $ids	= array();

        foreach ($users as $user) {
            if(is_array($user))
            {
                $ids[] = $user['user_id'];
            }else{
                $ids[] = $user;
            }
        }
		return count($ids) > 0 ? $ids : $users;
	}


	/**
	 * Retourne la liste des utilisateurs du groupe courant selon leur rôle (équivalent au GroupType avec le champ simulate_role = true)
	 *
	 * @param string $roleUniqueName chaîne de caractère correspondant au rôle avec lequel on souhaite trier les utilisateurs (TEACHER/PUPIL/PARENT/DIRECTOR/etc.)
	 * @param boolean $returnObject si vaut true, on souhaite que la méthode nous retourne les utilisateurs en objet de type User; sinon (false)
	 * la méthode retourne directement la réponse donnée par la centrale
	 * @throws HttpException lève une exception si le $roleUniqueName ne correspond à aucun type de groupe
	 * @return array|User[] liste des utilisateurs qui ont le rôle $roleUniqueName dans le groupe courant ($this->group)
	 */
	public function getUsersByRoleUniqueName($roleUniqueName = false, $returnObject = false, $searchParams = null)
	{

        $groupTypeRole = GroupTypeQuery::create()->findOneByType($roleUniqueName);

        if (null == $this->group) {
            $this->setGroup($this->container->get('bns.right_manager')->getCurrentGroup());
        }

        if (null == $groupTypeRole) {
            throw new \InvalidArgumentException('Role unique name given (' . $roleUniqueName . ') is NOT valid !');
        }
        $route = array(
            'group_id'	=> $this->group->getId(),
            'role_id'	=> $groupTypeRole->getId()
        );

        if($searchParams != null)
        {
            foreach($searchParams as $key => $param)
            {
                $route[$key] = $param;
            }
        }
        $usersResponse = $this->api->send('group_get_users_by_roles', array('route' => $route), true);
		if (true === $returnObject) {
			return $this->hydrateUsers($usersResponse);
		}
		return $usersResponse != null ? $usersResponse : array();
	}

    public function getUsersByRoleUniqueNameIds($roleUniqueName,$searchParams = null)
    {
        $results = $this->getUsersByRoleUniqueName($roleUniqueName,false, $searchParams);
        $final = array();
        if(is_array($results))
        {
            foreach($results as $result)
            {
                $final[] = $result['id'];
            }
        }

        return $final;
    }

    public function getNbUsers($roleUniqueName = null)
    {
        if($roleUniqueName == null)
        {
            return count($this->getUsersIds());
        }else{
            return count($this->getUsersByRoleUniqueNameIds($roleUniqueName));
        }
    }

	/**
	 * @param array $usersResponse
	 *
	 * @return array<User>
	 */
	private function hydrateUsers($usersResponse)
	{
		$usersIds = array();
		if (count($usersResponse)) {
			foreach ($usersResponse as $user) {
				$usersIds[] = $user['id'];
			}
		}

		$users = UserQuery::create()
            ->filterByArchived(false)
			->orderByLastName()
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add(UserPeer::ID, $usersIds, \Criteria::IN)
		->find();

		// One or more users don't exist in APP instance
        //Commenté ar Eymeric le 02/12/2014 car engendrait des erreurs de remontées
		/*if (count($users) != count($usersResponse)) {
			$found = false;
			foreach ($usersResponse as $userData) {
				foreach ($users as $user) {
					if ($userData['id'] == $user->getId()) {
						$found = true;
						break;
					}
				}

				if (!$found) {
					$users[] = $this->userManager->createTemporaryUser($userData);
				}

				$found = false;
			}

			return $users;
		}*/

		// Inject extra data
		foreach ($users as $user) {
			foreach ($usersResponse as $userResponse) {
				if ($user->getId() == $userResponse['id']) {
					$user->setIsEnabled($userResponse['enabled']);
					if (isset($userData['password_requested_at'])) {
						$user->setPasswordRequestedAt($userData['password_requested_at']);
					}
					if (isset($userData['password_created_at'])) {
						$user->setPasswordCreatedAt($userData['password_created_at']);
					}

					break 1;
				}
			}
		}

		return $users;
	}

	/**
	 * @param string $permissionUniqueName
	 * @param boolean $returnObject
	 *
	 * @return array
	 */
	public function getUsersByPermissionUniqueName($permissionUniqueName, $returnObject = false)
	{
		$usersResponse = $this->container->get('bns.right_manager')->getUsersThatHaveThePermissionInGroup($permissionUniqueName, $this->getGroup()->getId());
		if (true === $returnObject && 0 < count($usersResponse)) {
			return $this->hydrateUsers($usersResponse);
		}

		return $usersResponse;
	}

	/**
	 * @param string $permissionUniqueName
	 * @param boolean $returnObject
	 *
	 * @return array
	 */
	public function getUsersByRankUniqueName($rankUniqueName, $returnObject = false)
	{
		$usersResponse = $this->api->send('group_get_users_with_rank',
			array (
				'route' =>  array(
					'group_id'		   => $this->getGroup()->getId(),
					'rank_unique_name' => $rankUniqueName
				)
			)
		);

		if (true === $returnObject && 0 < count($usersResponse)) {
			return $this->hydrateUsers($usersResponse);
		}

		return $usersResponse;
	}

	/**
	 * Ajoute un utilisateur (paramètre $user) au groupe courant ($this->group)
	 * @param User $user
	 *
	 * @deprecated Do not use it anymore!
	 */
	public function addUser(User $user)
	{
		//Appel API
		$route = array(
			'group_id' => $this->getId()
		);

		$values = array(
			'id' => $user->getId()
		);

		$this->api->send(
			'group_add_user', array('values' => $values, 'route' => $route)
		);
	}

	/**
	 * retire un utilisateur (paramètre $user) du groupe courant
	 * @param User $user correspond à l'utilisateur à supprimer
     * @param String $roleType Type du role à supprimer (facultatif)
	 */
	public function removeUser(User $user, $roleType = null)
	{
		$route = array(
			'group_id' 	=> $this->getId(),
			'user_id'	=> $user->getId(),
		);

        if($roleType != null)
        {
            $route['roleType'] = $roleType;
        }
		$this->api->send(
            'group_delete_user',
            array(
                'route' => $route
            )
        );
        $this->api->resetGroup($this->getId(), false);

        $this->userManager->setUser($user);
        $this->userManager->resetRights();

        if($user->isChild())
        {
            $parents = $user->getParents();
            if($parents)
            {
                foreach($parents as $parent)
                {
                    if(count($parent->getChildren()) == 1)
                    {
                        $this->removeUser($parent, 'PARENT');
                    }
                }
            }
        }

        if(count($this->userManager->getRights()) == 0)
        {
            $this->userManager->deleteUser($user);
        }
	}

    /**
     * @param array<Integer> $usersId
     */
    public function removeUsers($usersId)
    {
		$this->api->send('group_delete_users', array(
			'route'	 => array(
                'group_id' => $this->getId()
            ),
            'values' => array(
                'users_id' => $usersId
            )
		));
		$this->api->resetGroupUsers($this->getId());
    }

    //Nouveau 27/07/2013
    /**
     * Délie un utilisateur d'un groupe
     */
    public function unlinkUser($user,$role = false)
    {

    }

    /**
	 * Supprime définitivement le groupe
	 */
	public function deleteGroup($id, $deleteFromCentral = true)
	{
		if (null == $id) {
			throw new InvalidArgumentException('You provide invalide group (potential issue origin: no id, equals to null');
		}
        //Sous groupes à supprimer
        $subgroupIds = $this->getOptimisedAllSubGroupIds($id);
        //On doit : supprimer App + Supprimer auth

        if($deleteFromCentral)
        {
		$this->api->send('group_delete', array(
			'route' 	=> array(
				'id' => $id
			),
			'values'	=> array(),
		));
        }

		$group = GroupQuery::create()->findOneById($id);

		$group->archive($this->container->getParameter('group_archive_duration'));

        if(BNSAccess::isConnectedUser())
        {
            if($group->getType() == 'CLASSROOM')
            {
                $this->container->get('bns.right_manager')->trackAnalytics('ARCHIVED_CLASSROOM', $group);
            }elseif($group->getType() == 'SCHOOL'){
                $this->container->get('bns.right_manager')->trackAnalytics('ARCHIVED_ACCOUNT', $group);
            }
        }

        switch($group->getType())
        {
            case 'SCHOOL':
                $this->container->get('bns.analytics.manager')->track('ARCHIVED_ACCOUNT',$group);
                break;
            case 'CLASSROOM':
                $this->container->get('bns.analytics.manager')->track('ARCHIVED_CLASSROOM', $group);
                break;
        }

		//suppression des sous groupes
		foreach ($subgroupIds as $subgroupId) {
			$this->deleteGroup($subgroupId);
		}
	}

    /**
     * Restaure le groupe
     */
    public function restoreGroup($id)
    {
        //Sous groupes à restaurer
        $subgroupIds = $this->getOptimisedAllSubGroupIds($id);
        $this->api->send('group_restore', array(
            'route' 	=> array(
                'group_id' => $id
            )
        ));
        $group = GroupQuery::create()->findOneById($id);
        $group->restore();
        //suppression des sous groupes
        foreach ($subgroupIds as $subgroupId) {
            $this->restoreGroup($subgroupId);
        }
    }


	/**
	 * Méthode qui renvoie les derniers utilisateurs à s'être connectés du groupe courant ($this->group)
	 *
	 * @param int $limitResult limite le nombre de résultat que l'on veut récupérer; par défaut la valeur est à 6
	 * @return array<User> tableau contenant les $limitResult derniers utilisateurs connectés
	 */
	public function getLastUsersConnected($limitResult = 6)
	{
	    $lastUsersConnected = UserQuery::create('u')
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->where('u.Id IN ?', $this->getUsersIds())
            ->where('u.LastConnection IS NOT NULL')
			->orderBy('u.LastConnection', \Criteria::DESC)
			->limit($limitResult)
		->find();

		return $lastUsersConnected;
	}

	///////////////   FONCTIONS LIEES AUX MODULES   \\\\\\\\\\\\\\\\\\


	/*
	 * Renvoie les modules que le groupe peut utiliser (acceptés dans le type de groupe)
	 */
	public function getAvailableModules()
	{
		return $this->group->getGroupType()->getModules();
	}


	/**
	 * Active un module pour le groupe courant pour les utilisateurs ciblés par le paramètre $ruleWho
	 *
	 * @param Module $module correspond au module que l'on veut activer
	 * @param array $ruleWho correspond aux paramètres qui permettent de cibler des utilisateurs
	 */
	public function activateModule(Module $module, $ruleWho)
	{
		$this->activationModuleRequest($module, $ruleWho, true);
	}

	/**
	 * Désactive un module pour le groupe courant pour les utilisateurs ciblés par le paramètre $ruleWho
	 *
	 * @param Module $module correspond au module que l'on veut activer
	 * @param array $ruleWho correspond aux paramètres qui permettent de cibler des utilisateurs
	 */
	public function desactivateModule(Module $module, $ruleWho)
	{
		$this->activationModuleRequest($module, $ruleWho, false);
	}

    /**
     * Fait l'appel API pour activer ou désactiver un module pour un groupe donné et pour des utilisateurs donnés
     *
     * @param Module $module module que l'on souhaite activer/désactiver
     * @param GroupType $groupTypeRole
     * @param boolean $state
     * @param string $groupType
     * @param int $groupId
     * @return bool
     */
    public function activationModuleRequest(Module $module, $groupTypeRole, $state, $groupType = null, $groupId = null)
    {
        $permissionToCheck = '_ACTIVATION';
        if ($groupType != null) {
            $permissionToCheck = $permissionToCheck . '_' . $groupType;
        }
        if ($this->container->get('bns.right_manager')->hasRight(strtoupper($module->getUniqueName()) . $permissionToCheck, $groupId)) {
            $defaultRank = RankDefaultQuery::create()
                ->_if(!$this->modeBeta)
                    // exclude beta rank if not in beta
                    ->filterByBeta(false)
                ->_endif()
                ->filterByGroupType($this->getGroup()->getGroupType()->getType())
                ->filterByRole($groupTypeRole->getType())
                ->filterByModuleUniqueName($module->getUniqueName())
                // if mode beta is on then we get beta rank first or default one
                ->orderByBeta($this->modeBeta ? \Criteria::DESC : \Criteria::ASC)
                ->findOne();


            if ($defaultRank) {
                $rankUniqueName = $defaultRank->getRankDefault();
            } else {
                switch ($groupTypeRole->getType()) {
                    case 'PUPIL':
                        $rankUniqueName = $module->getDefaultPupilRank();
                        break;
                    case 'PARENT':
                        $rankUniqueName = $module->getDefaultParentRank();
                        break;
                    case 'TEACHER':
                        $rankUniqueName = $module->getDefaultTeacherRank();
                        break;
                    default:
                        $rankUniqueName = $module->getDefaultOtherRank();
                }
            }

            // override base rank by a custom rank in high school partnerships
            // TODO: make this configurable
            if ('PUPIL' === $groupTypeRole->getType()
                && in_array($rankUniqueName, ['MEDIA_LIBRARY_USE', 'BLOG_USE'])
                && 'PARTNERSHIP' === $this->getGroup()->getType()
                && $this->getGroup()->getAttribute('IS_HIGH_SCHOOL')
            ) {
                $overridenRank = RankQuery::create()->findOneByUniqueName($rankUniqueName.'_PARTNERSHIP_HIGH_SCHOOL');
                if ($overridenRank) {
                    $rankUniqueName = $overridenRank->getUniqueName();
                }
            }

            $ruleWho = array(
                'domain_id' => $this->domainId,
                'group_parent_id' => $this->getGroup()->getId(),
                'group_type_id' => $groupTypeRole->getId()
            );

            $values = array(
                'state' => $state,
                'rank_unique_name' => $rankUniqueName,
                'who_group' => $ruleWho,
                'rule_where' => array(
                    'group_id' => $this->getGroup()->getId()
                ),
            );

            //Sécurisation
            $response = $this->api->send('rule_create', array('route' => array(), 'values' => $values));

            if (false === $state && !$module->isAutoOpen()) {
                $this->api->send('rule_delete', array('route' => array('id' => $response['id'])));
            }

            if ($state) {
                $this->container->get('bns.right_manager')->trackAnalytics('ACTIVATED_MODULE', $module);
            } else {
                $this->container->get('bns.right_manager')->trackAnalytics('DESACTIVATED_MODULE', $module);
            }

            $redis = $this->api->getRedisConnection();
            $groupId = $this->getGroup()->getId();
            if (!$groupTypeRole) {
                $redis->pipeline(function($pipe) use ($groupId, $module){
                    $pipe->hdel('group_' . $groupId, 'group_get_permissions_for_role_7_' . $groupId);
                    $pipe->hdel('group_' . $groupId, 'group_get_permissions_for_role_8_' . $groupId);
                    $pipe->hdel('group_' . $groupId, 'group_get_permissions_for_role_9_' . $groupId);
                    $pipe->hdel('group_' . $groupId, 'group_get_ranks_permissions_for_role_7');
                    $pipe->hdel('group_' . $groupId, 'group_get_ranks_permissions_for_role_6');
                    $pipe->hdel('group_' . $groupId, 'group_get_ranks_permissions_for_role_8');
                    $pipe->hdel('group_' . $groupId, 'group_get_users_with_permission_new_' . strtoupper($module->getUniqueName()) . '_ACCESS');
                });
            } else {
                $redis->pipeline(function($pipe) use ($groupId, $groupTypeRole, $module) {
                    $pipe->hdel('group_' . $groupId, 'group_get_permissions_for_role_' . $groupTypeRole->getId() . '_' . $groupId);
                    $pipe->hdel('group_' . $groupId, 'group_get_ranks_permissions_for_role_' . $groupTypeRole->getId());
                    $pipe->hdel('group_' . $groupId, 'group_get_users_with_permission_new_' . strtoupper($module->getUniqueName()) . '_ACCESS');
                });
            }
            $this->api->resetGroupUsers($groupId, true, false);

            return true;
        }

        return false;
    }

	/**
	 * Fait l'appel API pour attribuer ou non un rang à un groupe donné et pour des utilisateurs donnés
	 *
	 * @param string $rank Rank que l'on souhaite activer/désactiver
	 * @param GroupType $groupTypeRole
	 * @param boolean $state
	 */
	public function activationRankRequest($rankUniqueName, $groupTypeRole, $state)
	{

        $groupId = $this->getGroup()->getId();
		$ruleWho = array(
			'domain_id'			=> $this->domainId,
			'group_parent_id'	=> $groupId,
			'group_type_id'		=> $groupTypeRole->getId()
		);
		$values = array(
			'state' => $state,
			'rank_unique_name' => $rankUniqueName,
			'who_group' => $ruleWho,
			'rule_where' => array(
				'group_id' => $groupId
			),
		);
		$response = $this->api->send('rule_create', array('route' => array(), 'values' => $values));

		if (!$state) {
			$this->api->send('rule_delete', array('route' => array('id' => $response['id'])));
		}

        $this->api->getRedisConnection()->hdel('group_' . $groupId, 'group_get_permissions_for_role_' . $groupTypeRole->getId() . '_' . $groupId);
        $this->api->getRedisConnection()->hdel('group_' . $groupId, 'group_get_ranks_permissions_for_role_'  . $groupTypeRole->getId());
        $this->api->resetGroupUsers($groupId, true, false);
	}
	/**
	 * Retourne un tableau contenant des objets de type "Module" qui représentent les modules activés dans le groupe courant
	 * /!\ Le groupe courant est pris comme référence pour les traitements, il ne faut donc pas oublié d'appeler la méthode
	 * setGroup() pour initialiser le BNSGroupManager avec le groupe voulu.
	 *
	 * @param GroupType $groupTypeRole correspond au type de groupe pour lequel on recherche les modules activés pour ce groupe type rôle dans le
	 * group courant; Si le paramètre $groupTypeRole est à null, alors on récupère la liste des modules activés dans le groupe courant pour les utilisateurs
	 * du groupe parent
	 * @throws HttpException lève une exception si $groupTypeRole est différent de null et que le champ simulate_role de ce même type de groupe est à false
	 * @return array<Module> liste des modules activés
	 */
	public function getActivatedModules(GroupType $groupTypeRole)
	{
        $group = $this->getGroup();
		$ranks = null;
		if (null != $groupTypeRole) {
			if (false === $groupTypeRole->getSimulateRole()) {
				throw new \InvalidArgumentException('Please provide a group type type with field `simulate_role` equals to true.');
			}
			$ranks = $this->getRanksForRole($group, $groupTypeRole);
		}
		else {
            /** TODO clean dead code ?? $groupTypeRole not nullable */
            foreach($this->getParents() as $parent){
			    $ranks = array_merge($ranks, $this->getRanksForGroupUserInGroup($parent));
            }
		}

        // include the base rank as well when user has a custom high school partnership rank
        // TODO: make this configurable
        foreach (['MEDIA_LIBRARY_USE', 'BLOG_USE'] as $baseRank) {
            if (in_array($baseRank.'_PARTNERSHIP_HIGH_SCHOOL', $ranks)) {
                $ranks[] = $baseRank;
            }
        }

		if(null != $groupTypeRole && $groupTypeRole->getType() == 'PUPIL')
        {
            $filter = ModulePeer::DEFAULT_PUPIL_RANK;
		}elseif(null != $groupTypeRole && $groupTypeRole->getType() == 'PARENT')
        {
            $filter = ModulePeer::DEFAULT_PARENT_RANK;
		}elseif(null != $groupTypeRole && $groupTypeRole->getType() == 'TEACHER')
        {
            $filter = ModulePeer::DEFAULT_TEACHER_RANK;
		}else
        {
            $filter = ModulePeer::DEFAULT_OTHER_RANK;
		}

        $activatedModules = ModuleQuery::create()
            ->add($filter, $ranks, \Criteria::IN)
            ->filterByIsEnabled(true)
        ->find();

        //On ajoute si besoin les modules issus des ranks defaults
        $moreActivatedModule = ModuleQuery::create()
            ->useRankDefaultQuery()
                ->filterByRankDefault($ranks)
                ->filterByGroupType($group->getType())
            ->endUse()
            ->find();

        foreach ($moreActivatedModule as $moreModule) {
            $activatedModules->append($moreModule);
        }

        return $activatedModules;
	}


	/* Fonctions liées aux ressources */

	/*
	 * Renvoie le ratio d'utilisation des ressources, en %, sans virgule
	 */
	public function getResourceUsageRatio()
	{
		if ($this->getResourceAllowedSize() == 0) {
			return 0.00;
		}

		return round($this->getResourceUsedSize() / $this->getResourceAllowedSize(), 2) * 100;
	}
	/**
	 * Renvoie la place disponible
	 * @return integer
	 */
	public function getAvailableSize()
	{
		return $this->getResourceAllowedSize() - $this->getResourceUsedSize();
	}
	/**
	 * Renvoie la place utilisée
	 * @return integer
	 */
	public function getResourceUsedSize()
	{
		return $this->getAttribute('RESOURCE_USED_SIZE');
	}
	/**
	 * Renvoie la place autorisée
	 * @return integer
	 */
	public function getResourceAllowedSize()
	{
        return $this->getAttribute('RESOURCE_QUOTA_GROUP');
	}

	/* GESTION DES ATTRIBUTS */

    /**
     * @deprecated use the strict version @see getAttributeStrict()
     * @param string $uniqueName
     * @param mixed $defaultValue never used the default is "false"
     *
     * @return mixed
     */
    public function getAttribute($uniqueName, $defaultValue = null)
    {
        //On clone le this pour permettre la récursivité
        $current = clone($this);
        $group = $this->getGroup();
        $attr = $group->getAttribute($uniqueName);
        if ($attr != null) {
            return $attr;
        }

        $parents = $this->getParents();
        foreach ($parents as $parent) {
            $current->setGroup($parent);
            if ($parent) {
                return $current->getAttribute($uniqueName);
            }
        }

        return false;
    }


    /**
     * This return a group attribute or his parent attribute if value is null or ''
     *
     *
     * @param Group $group
     * @param $uniqueName
     * @param null $defaultValue the value to return if attribute is not set
     * @return mixed|null|string
     */
    public function getAttributeStrict(Group $group, $uniqueName, $defaultValue = null)
    {
        $value = $group->getAttribute($uniqueName);
        if (null !== $value && '' !== $value) {
            return $value;
        }
        foreach ($this->getParents($group) as $parent) {
            $value = $this->getAttributeStrict($parent, $uniqueName, null);
            if (null !== $value && '' !== $value) {
                return $value;
            }
        }

        return $defaultValue;
    }

	public function setAttribute($uniqueName,$value)
	{
		//On clone le this pour permettre la récursivité
		$current = clone($this);
		$group = $this->getGroup();
		if($group->hasAttribute($uniqueName)){
			$group->setAttribute($uniqueName,$value);
		}else{
                    $parents = $this->getParents();
                    foreach ($parents as $parent) {
			$current->setGroup($parent);
			$current->setAttribute($uniqueName,$value);
                    }
		}
	}

	///////////////   FONCTIONS LIEES AUX PERMISSIONS   \\\\\\\\\\\\\\\\\\

	/**
     * @deprecated use getPermissionsForRole instead
	 * Récupère la liste des permissions pour les utilisateurs ayant le rôle $groupTypeRole dans le groupe courant
	 *
	 * @param GroupType $groupTypeRole correspond au filtre pour le rôle des utilisateurs dont on souhaite connaître la liste des permissions
	 * @return array<String> liste de nom unique des permissions que possède un utilisateur ayant le rôle $groupTypeRole dans le groupe courant
	 */
	public function getPermissionsForRoleInCurrentGroup(GroupType $groupTypeRole)
	{
		$permissions = array();
		$groups = $this->getAncestors();
		$groups[] = $this->getGroup();
		foreach ($groups as $groupParent)
		{
			$response = $this->api->send('group_get_permissions_for_role', array(
				'route' => array(
					'group_id'				=> $this->getGroup()->getId(),
					'role_id'				=> $groupTypeRole->getId(),
					'group_parent_role_id'	=> $groupParent->getId(),
				)
			));

			if($response != null)
			{
				foreach ($response['finals_permissions_if_belongs'] as $permission)
				{
					if (!in_array($permission['unique_name'], $permissions))
					{
						$permissions[] = $permission['unique_name'];
					}
				}
			}
		}

		return $permissions;
	}

    /**
     * get permissions for a role in a group
     * @param Group $group
     * @param GroupType $groupTypeRole
     * @return array<tstring> a list of permissions unique_name
     */
    public function getPermissionsForRole(Group $group, GroupType $groupTypeRole)
    {
        try {
            $response = $this->api->send('group_get_ranks_permissions_for_role', array(
                'route' => array(
                    'group_id'  => $group->getId(),
                    'role_id'   => $groupTypeRole->getId(),
                )
            ));

            if ($response && isset($response['permissions'])) {
                return $response['permissions'];
            }
        } catch (NotFoundHttpException $e) {
            // no ranks for group/groupType
        }


        return [];
    }

	///////////////   FONCTIONS LIEES AUX RANGS   \\\\\\\\\\\\\\\\\\

	/** @deprecated use getRanksForRole instead
	 * Récupère la liste des rangs pour un rôle donnée ($groupTypeRole) dans le groupe courant ($this->group)
	 * @param GroupType $groupTypeRole un objet de type GroupType avec le champ simulate_role = true
	 * @return array<String> liste des unique_name de chaque rang que possède le type d'utilisateur ($groupTypeRole) dans le groupe courant
	 */
	public function getRanksForRoleInCurrentGroup(GroupType $groupTypeRole)
	{
		$ranks = array();
		$groups = $this->getAncestors();
		$groups[] = $this->getGroup();
		foreach ($groups as $groupParent)
		{
			$response = $this->api->send('group_get_permissions_for_role', array(
				'route' => array(
					'group_id'				=> $this->getGroup()->getId(),
					'role_id'				=> $groupTypeRole->getId(),
					'group_parent_role_id'	=> $groupParent->getId(),
				)
			),true);
			// Dans le cas où on recherche dans un sous groupe role qui n'existe pas encore, la réponse de la centrale sera égale à null
			if (null == $response)
			{
				continue;
			}

			$ranks = array_merge($ranks, $this->extractRanksFromServerResponse($response));
		}
        $ranks = array_filter($ranks, function($value) {
            return false !== $value;
        });

		return $ranks;
	}

    /**
     * get ranks for a role in a group
     * @param Group $group
     * @param GroupType $groupTypeRole
     * @return array<tstring> a list of ranks unique_name
     */
    public function getRanksForRole(Group $group, GroupType $groupTypeRole)
    {
        try {
            $response = $this->api->send('group_get_ranks_permissions_for_role', array(
                'route' => array(
                    'group_id'  => $group->getId(),
                    'role_id'   => $groupTypeRole->getId(),
                )
            ));

            if ($response && isset($response['ranks'])) {
                return $response['ranks'];
            }
        } catch (NotFoundHttpException $e) {
            // no ranks for group/groupType
        }

        return [];
    }

	/**
     * @deprecated
	 * Récupère la liste des rangs communs à tous les utilisateurs du groupe $group fourni en paramètre
	 * @param Group $group groupe pour lequel on souhaite connaître tous les rangs commun à tous les utilisateurs
	 * @return array<String> tableau contenant tous les uniques names des rangs communs à tous les utilisateurs du groupe $group
	 */
	public function getRanksForGroupUserInGroup(Group $group)
	{
		$ranks = array();
		$response = $this->api->send('group_get_permissions_for_group', array(
			'route' => array(
				'group_id'			=> $this->getGroup()->getId(),
				'group_to_test_id'	=> $group->getId(),
			)
		));

		if (null != $response)
		{
			$ranks = $this->extractRanksFromServerResponse($response);
		}
		return $ranks;
	}

    /**
     * ** Cette méthode est une méthode privée !**
     * Cette méthode permet, à partir de la réponse que l'on a obtenu à partir des appels API (Group API)
     * "Récupérer les règles et permissions appliquées à un rôle dans un groupe" et
     * "Récupérer les règles et permissions appliquées aux utilisateurs d'un groupe dans un autre groupe", d'y extraire les rangs
     * @param array $response réponse fournit par les appels API "Récupérer les règles et permissions appliquées à un rôle dans un groupe" ou
     * "Récupérer les règles et permissions appliquées aux utilisateurs d'un groupe dans un autre groupe"
     * @return array<String> tableau contenant tous les rangs fournis par la réponse de l'appel API
     */
    private function extractRanksFromServerResponse($response)
    {
        $ranks = array();
        foreach ($response['rules'] as $rule) {
            if (!isset($ranks[$rule['rank_unique_name']])) {
                $ranks[$rule['rank_unique_name']] = false == $rule['state'] ? false : $rule['rank_unique_name'];
            } elseif (isset($ranks[$rule['rank_unique_name']]) && false == $rule['state']) {
                $ranks[$rule['rank_unique_name']] = false;
            }
        }

        return $ranks;
    }

	///////////////   FONCTIONS LIEES AUX INVITATIONS   \\\\\\\\\\\\\\\\\\
	/**
	 * Invite l'utilisateur passé en paramètre $user à rejoindre le groupe courant ($this->group) avec le rôle
	 * $groupTypeRole (Rôle facultatif)
	 *
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * @param \BNS\App\CoreBundle\Model\GroupType $groupTypeRole
	 * @throws \InvalidArgumentException
	 */
	public function inviteUserInGroup(User $user, User $author, GroupType $groupTypeRole = null)
	{
		if (null == $user || null == $author) {
			throw new \InvalidArgumentException('parameter `user` && `author` must be != null');
		}

		$values = array(
			'user_id'	=> $user->getId(),
			'author_id'	=> $author->getId(),
			'group_id'	=> $this->getGroup()->getId()
		);

		if (null != $groupTypeRole) {
			if (!$groupTypeRole->isSimultateRole()) {
				throw new HttpException(500, 'You must provide a group type with simulate_role equals to true');
			}

			$values['role_id'] = $groupTypeRole->getId();
		}

		$this->api->send('invitation_create', array(
			'values' => $values
		));
	}

	public function isInvitedInGroup(User $user, GroupType $groupTypeRole = null)
	{
		if (null == $user || (null != $groupTypeRole && false === $groupTypeRole->getSimulateRole())) {
			throw new InvalidArgumentException('Parameter `user` must be != null and $groupTypeRole must have `simulate_role` field equals to true');
		}

		$values = array(
			'user_id'	=> $user->getId(),
			'group_id'	=> $this->getGroup()->getId()
		);

		if (null != $groupTypeRole) {
			$values['role_id'] = $groupTypeRole->getId();
		}

		$response = $this->api->send('invitation_search', array(
			'values' => $values
		));

		return 0 < count($response);
	}

	/**
	 * Focntions liées à la mise à jour du cache Redis
	 *
	 */

	/**
     * @deprecated DO NOT USE this, only use precise clear cache when needed
     *
	 * Met à jour le cache pour le groupe : ATTENTION, à utiliser avec parcimonie (groupe = classe ou école maximum)
	 */
	public function clearGroupCache()
	{
        $this->api->resetGroup($this->getGroup()->getId(), false);
		$this->api->resetGroupUsers($this->getGroup()->getId(),true,true);
	}

    /**
     * @param array $groups
     *
     * @return array
     */
    public function buildParentGraph($groups)
    {
        return $this->buildGraph($groups)->getParents();
    }

    /**
     * @param array $groups
     *
     * @return \BNS\App\CoreBundle\Group\GroupGraph
     */
    public function buildGraph($groups)
    {
        $groupIds = array();
        foreach ($groups as $group) {
            $groupIds[] = $group->getId();
        }

        $tree = $this->api->send('groups_tree', array(
			'route' => array(
                'group_ids' => json_encode($groupIds)
            )
		), false);

        return new GroupGraph($tree);
    }

    public function getUais($groups)
    {
        $graph = $this->buildGraph($groups);
        $uais = array();

        foreach ($groups as $group) {
            if ($group->getGroupType()->getType() == 'CLASSROOM') {
                if ($graph->hasNode($group->getId())) {
                    $parents = $graph->getNode($group->getId())->getParents();
                    foreach ($parents as $parent) {
                        if ($parent->getGroup()->hasAttribute('UAI')) {
                            $uais[$parent->getGroup()->getAttribute('UAI')] = true;
                        }
                    }
                }
            }
            elseif ($graph->hasNode($group->getId())) {
                $node = $graph->getNode($group->getId());
                if ($node->getGroup()->hasAttribute('UAI')) {
                    $uais[$node->getGroup()->getAttribute('UAI')] = true;
                }

                // Declare recursive closure
                $getUai = function ($node) use (&$getUai, &$uais) {
                    $children = $node->getChildren();

                    foreach ($children as $child) {
                        if ($child->getGroup()->hasAttribute('UAI')) {
                            $uais[$child->getGroup()->getAttribute('UAI')] = true;
                        }

                        if ($child->hasChildren()) {
                            $getUai($child);
                        }
                    }
                };

                $getUai($node);
            }
        }

        return array_keys($uais);
    }

    public function getClassroomsStatus()
    {
        $classroomTypeId = GroupTypeQuery::create()->findOneByType('CLASSROOM')->getId();
        $validated = 0;
        $refused   = 0;
        $pending   = 0;
        $users     = 0;
        foreach($this->getSubgroups(true, false, $classroomTypeId) as $classroom)
        {
            if(!$classroom->isArchived())
            {
                $this->setGroup($classroom);
                /** @var Group $classroom */
                if($classroom->isValidated())
                {
                    $validated++;
                }
                if($classroom->isPendingConfirmation())
                {
                    $pending++;
                }
                if($classroom->isRefused())
                {
                    $refused++;
                }
                $users += $this->getNbUsers('TEACHER') + $this->getNbUsers('TEACHER');
            }
        }
        return array(
            'validated' => $validated,
            'pending' => $pending,
            'refused' => $refused,
            'all' => $validated + $refused + $pending,
            'users' => $users
        );
    }

    public function getCountry()
    {
        if ($this->group->getCountry()) {
            return $this->group->getCountry();
        }

        if($this->group->hasAttribute('COUNTRY'))
        {
            $val = $this->getAttribute('COUNTRY');
            if($val == null)
            {
                $school = SchoolInformationQuery::create()->findOneByGroupId($this->group->getId());
                if($school)
                {
                    $val = $school->getCountry();
                    $this->group->setAttribute('COUNTRY',$val);
                }
            }
            return $val;
        }
        return 'FR';
    }

    /*
     * Renvoie les traits pour l'analytics
     */
    public function getTraits()
    {
        $group = $this->getGroup();
        $status = $this->getClassroomsStatus();

        $plan = $this->getProjectInfo('plan');

        if($plan == 'Free' && $group->isPremium())
        {
            $plan = 'School';
        }

        $miniSites = $group->getMiniSites();

        if($miniSites->count() > 0)
        {
            /* @var \BNS\App\MiniSiteBundle\Model\MiniSite $miniSite */
            $miniSite = $miniSites->getFirst();
            $miniNbPages = $miniSite->getMiniSitePages()->count();
            $pageViews = 0;
            foreach($miniSite->getMiniSitePages() as $page)
            {
                $pageViews += $page->getViews();
            }
            $miniSiteNbViews = $pageViews;
        }else{
            $miniNbPages = 0;
            $miniSiteNbViews = 0;
        }

        return array(
            'ecoleCity'              => $group->getAttribute('CITY'),
            'ecoleCountry'           => $this->getCountry(),
            'EcolePostalCode'        => $group->getAttribute('ZIPCODE'),
            'EcoleStreet'            => $group->getAttribute('ADDRESS'),
            'createdAt'             => $group->getRegistrationDate('U'),
            'industry'              => $this->getProjectInfo('name'),
            'name'                  => $group->getLabel(),
            'plan'                  => $plan,
            'revenue'               => $plan == 'School' ? 4.90 : 0,
            'Nombre classes'        => $status['all'],
            'Nombre classes confirmées' => $status['validated'],
            'Nombre utilisateurs'       => $status['users'],
            'miniSiteNbPages'           => $miniNbPages,
            'miniSiteNbViews'       => $miniSiteNbViews
        );
    }

    public function getCheckGroupEnabled()
    {
        return $this->container->hasParameter('check_group_enabled') && true === $this->container->getParameter('check_group_enabled');
    }

    public function getCheckGroupValidated()
    {
        return $this->container->hasParameter('check_group_validated') && true === $this->container->getParameter('check_group_validated');
    }

    /**
     * Return an array of activated modules in a group
     * @param Group $group
     * @return array of activated modules (key) for default type with value true or 'partial'
     */
    public function getActivatedModuleUniqueNames(Group $group)
    {
        switch ($group->getType()) {
            case 'CLASSROOM':
                $openRoles = array('PUPIL', 'PARENT');
                break;
            case 'SCHOOL':
                $openRoles = array('TEACHER', 'PUPIL', 'PARENT');
                break;
            default:
                $openRoles = array('PUPIL', 'TEACHER', 'PARENT');
        }

        $roles = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->filterByType($openRoles)
            ->find()
        ;

        $activatedModules = array();
        foreach ($roles as $role) {
            // TODO Optimize me
            foreach ($this->setGroup($group)->getActivatedModules($role) as $activatedModule) {
                if (!isset($activatedModules[$activatedModule->getUniqueName()])) {
                    $activatedModules[$activatedModule->getUniqueName()] = 1;
                } else {
                    $activatedModules[$activatedModule->getUniqueName()] += 1;
                }
            }
        }
        $nbRoles = count($roles);
        foreach ($activatedModules as $key => $count) {
            if ($nbRoles > $count) {
                $activatedModules[$key] = 'partial';
            } else {
                $activatedModules[$key] = true;
            }
        }

        return $activatedModules;
    }

    public function createSchool(Group $classroom, Group $parent = null)
    {
        if ($classroom->getGroupType()->getType() == 'CLASSROOM') {

            $this->setGroup($classroom);
            if (!$parent) {
                $parent = $this->getParent();
            }
            if ($parent->getGroupType()->getType() != 'SCHOOL') {
                $schoolLabel = $classroom->getAttribute('SCHOOL_LABEL');
                $values = array(
                    'label' => $schoolLabel ?: $this->container->get('translator')->trans('LABEL_MY_SCHOOL', [], 'USER'),
                    'type' => 'SCHOOL',
                    'attributes' => array(
                        'CITY' => $classroom->getAttribute('CITY'),
                        'ZIPCODE' => $classroom->getAttribute('ZIPCODE')
                    )
                );
                $newSchool = $this->createGroup($values);
                $newSchool->setCountry($classroom->getCountry());
                $this->deleteParent($classroom->getId(), $parent->getId());
                $this->addParent($newSchool->getId(), $parent->getId());
                $this->addParent($classroom->getId(), $newSchool->getId());

                return $newSchool;
            }
        }

        throw new \InvalidArgumentException("Paramater `classroom` must be a Group of type `Classroom`");
    }

    /**
     * this try to find the right spot url for $group
     * @param Group|null $group
     * @return bool|string the spot autoconnect url
     */
    public function getSpotAutoConnectUrl(Group $group = null)
    {
        $group = $group ? : $this->getGroup();
        if (!$group) {
            return false;
        }

        // get attibute data
        $data = $this->setGroup($group)->getAttribute('SPOT_AUTOCONNECT_URL');
        if (!$data) {
            return false;
        }
        $decoded = null;
        try {
            // try to decode data if we have a json
            $decoded = @json_decode($data, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $decoded = null;
            }
        } catch (\Exception $e) {
            $decoded = null;
        }

        $url = null;
        if (!$decoded || !is_array($decoded)) {
            // we don't have a json we bet for a url
            $url = $data;
        } else {
            $country = $group->getCountry();
            if (!$country && 'CLASSROOM' === $group->getType()) {
                // fallback to school country
                $school = $this->setGroup($group)->getParent();
                if ($school) {
                    $country = $school->getCountry();
                }
            }
            if (!$country || ! isset($decoded['mapping'])) {
                $url = null;
            } else {
                foreach ($decoded['mapping'] as $mapping) {
                    if (isset($mapping['country']) && $country === $mapping['country']) {
                        $url = $mapping['spot_url'];
                        break;
                    }
                }
            }
            if (!$url) {
                $url = isset($decoded['default_spot_url']) ? $decoded['default_spot_url'] : false;
            }
        }

        if ($url) {
            // we check that the url is valid
            $constraint = new Url();
            $validator = $this->container->get('validator');
            $errors = $validator->validate($url, [
                $constraint
            ]);
            if (count($errors)) {
                $this->container->get('logger')->error('GroupManager - getSpotAutoConnectUrl invalid spot url', [
                    'spot_url' => $url,
                    'data' => $data,
                ]);
            }
        }

        return $url ? : false;
    }

    public function getCguUrl(Group $group, User $user)
    {
        $oldGroup = $group;
        if ($this->hasGroup()) {
            $oldGroup = $this->getGroup();
        }

        $data = $this->setGroup($group)->getAttribute('CGU_URL');

        if (!$data) {
            return false;
        }
        $decoded = json_decode($data, true);
        $url = null;
        $default = true;
        foreach ($decoded['mapping'] as $mapping) {
            if (isset($mapping['language']) && $user->getLang() === $mapping['language']) {
                $url = $mapping['cgu_url'];
                $default = false;
                break;
            }
        }
        if (!$url) {
            $url = isset($decoded['default_cgu_url']) ? $decoded['default_cgu_url'] : false;
            if ($user->getLang() == 'fr') {
                $default = false;
            }
        }


        if ($url) {
            $constraint = new Url();
            $validator = $this->container->get('validator');
            $errors = $validator->validate($url, [
                $constraint
            ]);
            if (count($errors)) {
                $this->container->get('logger')->error('GroupManager - getCgutUrl invalid cgu url', [
                    'cgu_url' => $url,
                    'data' => $data,
                ]);
            }
        }

        if ($oldGroup) {
            $this->setGroup($oldGroup);
        }

        return ['url' => $url, 'default' => $default] ? : false;
    }
}

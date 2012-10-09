<?php

namespace BNS\App\CoreBundle\Group;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
use BNS\App\ResourceBundle\Model\ResourceLabelGroupPeer;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\BlogPeer;

/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des Groupes / groupes types
 */
class BNSGroupManager
{
	protected $container;
	protected $api;	
	protected $domainId;
	protected $group;
	protected $group_id;
	protected $roleManager;
	protected $user_manager;
	protected $parents;
	protected $subgroups;
	
	/**
	 * Au cas où on change de current group, on enregistre l'ID en temps de key
	 * 
	 * @var array<GroupId, Group>
	 */
	protected $parent;
	
	public function __construct($container, $roleManager, $user_manager, $api, $module_manager, $domain_id)
	{
		$this->container		= $container;
		$this->api				= $api;
		$this->roleManager		= $roleManager;
		$this->user_manager		= $user_manager;
		$this->module_manager	= $module_manager;
		$this->domainId			= $domain_id;
		$this->group			= null;
		$this->group_id			= null;
		$this->parent			= array();
	}
	
	///////////   FONCTIONS LIEES AUX GROUPES DIRECTEMENT  \\\\\\\\\\\
	
	/*
	 * Set du groupe, généralement depuis les controleurs
	 * @param Group $group Groupe en question
	 */
	public function setGroup($group)
	{
		$this->group = $group;
		
		return $this;
	}
	
	/**
	 * @param type $group_id
	 * 
	 * @return \BNS\App\CoreBundle\Group\BNSGroupManager
	 */
	public function setGroupById($group_id)
	{
		$group = GroupQuery::create()->findOneById($group_id);
		
		if (null == $group) {
			throw new NotFoundHttpException('The group with id : ' . $group_id . ' is NOT found !');
		}
		
		$this->group = $group;
		
		return $this;
	}
	
    /*
	 * Get du group
	 * @return Group le groupe en question
	 */
	public function getGroup()
	{
		if (isset($this->group)) {
			return $this->group;
		}
		else {
			throw new HttpException(500,"Group is not set");
		}
	}
	/*
	 * Renvoie les informations d'un groupe de manière sur (groupe en local ou sur centrale)
	 */
	public function getSafeGroup($groupId)
	{
		$group = GroupQuery::create()->findOneById($groupId);
		if ($group) {
			return array(
				'id' => $group->getId(),
				'label' => $group->getLabel(),
				'group_type_id' => $group->getGroupTypeId() 
			);
		}
		else {
			//Group forcément sur la centrale
			return $this->api->send('group_read',array('route' => array('id' => $groupId)));
		}
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

			$values['group_id'] = $response['id'];
			$values['validated'] = isset($params['validated']) && $params['validated'] ? true : false;
			$values['attributes'] = isset($params['attributes']) ? $params['attributes'] : array();
			
			// Création des données indispensables aux groupes, quelqu'ils soient
			$newGroup = GroupPeer::createGroup($values);
			
			// Création des modules associés au groupe
			if ($generateModules) {				
				AgendaPeer::createAgenda($values);
				ResourceLabelGroupPeer::createResourceLabelGroup($values);
				BlogPeer::create($values);
				/** Blocage à la création ->lazy loader
				 * MiniSitePeer::create($values);
				 */
			}
			
			$this->setGroup($newGroup);
			
			return $newGroup;
		}
		else {
			throw new \InvalidArgumentException('Not enough datas to create group : please provide label, type and domain_id');
		}
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
		
		if(isset($params['parent_id'])){
			$datas['group_parent_id'] = $params['parent_id'];
		}
		$this->api->send('group_update',array('route' => array('group_id' => $group->getId()),'values' => $datas));
	}
	
	public function updateParent($parentId)
	{
		$params['parent_id'] = $parentId;
		$this->updateGroup($params);
	}
	
	public function createEnvironment($params)
	{
		if(isset($params['label']))
		{
			$environmentGroupTypeId = GroupTypeQuery::create()->findOneByType('ENVIRONMENT')->getId();
			
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
	
	////// FONCTIONS LIEES AUX REGLES \\\\\\\
	
	/*
	 * retourne les règles liées au groupe (lui plus ses parents)
	 */
	public function getRules()
	{
		$route = array(
			'where-group_id' => $this->getId()
		);
		$response = $this->api->send(
			"rule_search",array('route' => $route)
			,null
			,false
		);
		return $response;
	}
	
	
	
	///////////   FONCTIONS LIEES AUX TYPES DE GROUPES GROUPES DIRECTEMENT  \\\\\\\\\\\
	
	
	/*
	 * Renvoie les informations d'un groupe de manière sur (type de groupe en local ou sur centrale)
	 */
	public function getSafeGroupType($groupTypeId){
		$groupType = GroupTypeQuery::create()->findOneById($groupTypeId);
		if($groupType){
			return array(
				'id' => $groupType->getId(),
				'label' => $groupType->getLabel(),
				'centralize' => $groupType->getCentralize(),
				'simulate_role' => $groupType->getSimulateRole(),
				'type' => $groupType->getType()
			);
		}else{
			//Group forcément sur la centrale
			return $this->api->send('grouptype_read',array('route' => array('id' => $groupTypeId)));
		}
	}
	
	/*
	 * Création d'un type de groupe : toujours passer par cette méthode pour créer un type de groupe
	 * @params array $params
	 * @return GroupType
	 */
	public function createGroupType($params)
	{
		//vérification que nous avons assez d'infos : #domaine, label , type, centralize

		if(isset($params['type']) && isset($params['label']) && isset($params['centralize']) && isset($params['simulate_role']))
		{
			$label = $params['label'];
			$type = $params['type'];
			$simulateRole = $params['simulate_role'];
			$centralize = $params['centralize'];
			$domainId = isset($params['domain_id']) ? $params['domain_id'] : null;

			$values = array(
				'label'			=> $label,
				'type'			=> $type,
				'centralize'	=> $centralize,
				'domain_id'		=> $domainId,
				'simulate_role' => $simulateRole
			);

			$response = $this->api->send('grouptype_create',array('values' => $values));

			$values['group_type_id'] = $response['id'];
			$values['description'] = isset($params['description']) ? $params['description'] : "";
			if (isset($params['is_recursive'])) {
				$values['is_recursive'] = $params['is_recursive'];
			}
			
			$new_group_type = GroupTypePeer::createGroupType($values);
			
			//Création des permissions / rangs associés en Mode CRUDE
			
			$groupModuleId = ModuleQuery::create()->findOneByUniqueName('GROUP')->getId();
			
			$createPermission = $this->module_manager->createPermission(
				array(
					'unique_name' => $type . "_CREATE",
					'module_id' => $groupModuleId,
					'i18n' => array(
						'fr' => array(
							'label' => "Créer des " . $label . 's'
						)
					)
				)
			);
			
			$editPermission = $this->module_manager->createPermission(
				array(
					'unique_name' => $type . "_EDIT",
					'module_id' => $groupModuleId,
					'i18n' => array(
						'fr' => array(
							'label' => "Editer les " . $label . 's'
						)
					)
				)
			);
			
			$deletePermission = $this->module_manager->createPermission(
				array(
					'unique_name' => $type . "_DELETE",
					'module_id' => $groupModuleId,
					'i18n' => array(
						'fr' => array(
							'label' => "Supprimer les " . $label . 's'
						)
					)
				)
			);
			
			//Création des rangs : create et manage
			
			$createRank = $this->module_manager->createRank(
				array(
					'unique_name' => $type . "_TYPE_CREATION",
					'module_id' => $groupModuleId,
					'i18n' => array(
						'fr' => array(
							'label' => "Créer des " . $label . 's'
						)
					)
				)
			);
			
			$manageRank = $this->module_manager->createRank(
				array(
					'unique_name' => $type . "_TYPE_MANAGE",
					'module_id' => $groupModuleId,
					'i18n' => array(
						'fr' => array(
							'label' => "Gérer les " . $label . 's'
						)
					)
				)
			);
			
			//Les liaisons permissions / rangs
			//Create
			$this->module_manager->addRankPermission($createRank->getUniquename(),$createPermission->getUniqueName());
			//Manage
			$this->module_manager->addRankPermission($manageRank->getUniquename(),$createPermission->getUniqueName());
			$this->module_manager->addRankPermission($manageRank->getUniquename(),$editPermission->getUniqueName());
			$this->module_manager->addRankPermission($manageRank->getUniquename(),$deletePermission->getUniqueName());
			
			return $new_group_type;
		}
		else
		{
			throw new HttpException(500,'Not enough datas to create grouptype : please provide label, type and centralize');
		}

	}
	
	
	///////////   FONCTIONS LIEES AUX LIAISONS   \\\\\\\\\\\
	
	
	/**
	 * Retourne la liste des groupes fils du groupe courant ($this->group)
	 * /!\ L'attribut $this->group doit être impérativement défini sinon une exception sera levée
	 * 
	 * @return type
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
		),false);
		
		// On test si oui ou non le groupe a un/des sous-groupe(s)
		if (count($response) > 0 && true === $returnObject) {
			// Le groupe a un/des sous-groupe(s), on construit les objets de type Group à partir des informations reçues
			$subgroupIds = array();
			$subgroupsRole = array();
			foreach($response as $r) {
				$groupType = null;
				try {
					$groupType = $this->roleManager->getGroupTypeRoleFromId($r['group_type_id']);
				}
				catch (Exception $e) { } // Aucun traitement à faire
				
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
				->joinWith('GroupType')
				->add(GroupPeer::ID, $subgroupIds, \Criteria::IN)
				->orderByLabel();
			
			if($groupTypeId){
				$responseQuery->filterByGroupTypeId($groupTypeId);
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
	 * Retourne le groupe parent du groupe $this->group
	 * 
	 * @return Group le groupe parent recherché
	 */
	public function getParent()
	{
		if (isset($this->parent[$this->getGroup()->getId()])) {
			return $this->parent[$this->getGroup()->getId()];
		}
		
		$group = $this->getGroup();
		$route = array(
			'id'	=> $group->getId(),
		);
		
		$response = $this->api->send('group_parent', array(
			'route'	=> $route
		));
		
		if (null != $response) {
			$response = GroupQuery::create()->orderByLabel()->findOneById($response['id']);
		}
		
		$this->parent[$this->getGroup()->getId()] = $response;
		
		return $response;
	}
	
	/**
	 * Récupère la liste de tous les parents (le parent du groupe courant, le parent du groupe parent du groupe courant, etc.)
	 * du groupe courant ($this->group)
	 *
	 * @return Array<Group> Liste des parents du groupe parent
	 */
	public function getParents()
	{
		$groupParents = array();
		$currentGroup = $this->group;
		$groupParent = $this->getParent();
		while (null != $groupParent)
		{
			$groupParents[] = $groupParent;
			$this->setGroup($groupParent);
			$groupParent = $this->getParent();
		}
		
		$this->setGroup($currentGroup);
		
		return $groupParents;
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
	}
	
	///////////////   FONCTIONS LIEES AUX UTILISATEURS   \\\\\\\\\\\\\\\\\\
	/**
	 * Retourne la liste des utilisateurs du groupe courant ($this->group)
	 * 
	 * @param boolean $returnObject si vaut true, on souhaite que la méthode nous retourne les utilisateurs en objet de type User; sinon (false)
	 * la méthode retourne directement la réponse donnée par la centrale
	 * @return Array<Users> Liste des utilisateurs du groupe courant
	 */
	public function getUsers($returnObject = false)
	{
		if ($returnObject) {
			$users = UserQuery::create()
				->joinWith('Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->add(UserPeer::ID, $this->getUsersIds(), \Criteria::IN)
				->orderByLastName()
			->find();
		}
		else {
			$users = $this->getUsersArray();
		}
		
		return $users;
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
		foreach ($this->getSubgroups() as $subgroup) {
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
	 * Renvoie les utilisateurs depuis la centrale
	 * 
	 * @return array
	 */
	public function getUsersArray()
	{
		$route = array(
			'group_id' => $this->getGroup()->getId(),
		);

		return $this->api->send('group_get_users', array('route' => $route));
	}
	
	/**
	 * Renvoie les ids des utilisateurs du groupe
	 * 
	 * @return array<Integer> 
	 */
	public function getUsersIds()
	{
		$users	= $this->getUsersArray();
		$ids	= array();
		
		foreach ($users as $user) {
			$ids[] = $user['user_id'];
		}
		
		return $ids;
	}

    
	/**
	 * Retourne la liste des utilisateurs du groupe courant selon leur rôle (équivalent au GroupType avec le champ simulate_role = true)
	 * 
	 * @param string $roleUniqueName chaîne de caractère correspondant au rôle avec lequel on souhaite trier les utilisateurs (TEACHER/PUPIL/PARENT/DIRECTOR/etc.)
	 * @param boolean $returnObject si vaut true, on souhaite que la méthode nous retourne les utilisateurs en objet de type User; sinon (false)
	 * la méthode retourne directement la réponse donnée par la centrale
	 * @throws HttpException lève une exception si le $roleUniqueName ne correspond à aucun type de groupe
	 * @return array<User> liste des utilisateurs qui ont le rôle $roleUniqueName dans le groupe courant ($this->group)
	 */
	public function getUsersByRoleUniqueName($roleUniqueName, $returnObject = false, $groupTypeRole = null)
	{
		if (null == $groupTypeRole) {
			$groupTypeRole = GroupTypeQuery::create()->findOneByType($roleUniqueName);
		}

		if (null == $groupTypeRole || null == $this->group) {
			throw new \InvalidArgumentException('Role unique name given (' . $roleUniqueName . ') is NOT valid !');
		}

		$route = array(
			'group_id'	=> $this->group->getId(),
			'role_id'	=> $groupTypeRole->getId()
		);

		$users = $this->api->send('group_get_users_by_roles', array('route' => $route),false);

		if (true === $returnObject && 0 < count($users)) {
			$usersIds = array();
			foreach ($users as $user) {
				$usersIds[] = $user['id'];
			}

			$users = UserQuery::create()
                                ->orderByFirstName()
				->joinWith('Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->add(UserPeer::ID, $usersIds, \Criteria::IN)
			->find();
		}

		return $users;
	}
    
	/**
	 * Ajoute un utilisateur (paramètre $user) au groupe courant ($this->group)
	 * @param User $user
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
	 * 
	 * @param User $user correspond à l'utilisateur à supprimer
	 */
	public function removeUser(User $user)
	{
		$route = array(
			'group_id' 	=> $this->getId(),
			'user_id'	=> $user->getId(),
		);

		//Appel API
		$this->api->send('group_delete_user', array(
			'route'	=> $route,
		));
	}
	
	/**
	 * Méthode qui renvoie les derniers utilisateurs à s'être connectés du groupe courant ($this->group)
	 * 
	 * @param int $limitResult limite le nombre de résultat que l'on veut récupérer; par défaut la valeur est à 6
	 * @return array<User> tableau contenant les $limitResult derniers utilisateurs connectés
	 */
	public function getLastUsersConnected($limitResult = 6)
	{
		$groupUsers = $this->getUsers(false);
		$userIds = array();
		foreach ($groupUsers as $groupUser) {
			$userIds[] = $groupUser['user_id'];
		}

		$lastUsersConnected = UserQuery::create('u')
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->where('u.Id IN ?', $userIds)
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
	 * @param type $groupTypeRole
	 * @param type $state
	 */
	public function activationModuleRequest(Module $module, $groupTypeRole, $state)
	{	
		if($this->container->get('bns.right_manager')->hasRight(strtoupper($module->getUniqueName()) . '_ACTIVATION')){
		
			switch ($groupTypeRole->getType()){
				case 'PUPIL':
					$rankUniqueName = $module->getDefaultPupilRank();
					break;
				case 'PARENT':
					$rankUniqueName = $module->getDefaultParentRank();
					break;
				default:
					$rankUniqueName = $module->getDefaultOtherRank();
			}

			$ruleWho = array(
				'domain_id'			=> $this->domainId,
				'group_parent_id'	=> $this->getGroup()->getId(),
				'group_type_id'		=> $groupTypeRole->getId()
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

			if (false === $state){
				$this->api->send('rule_delete', array('route' => array('id' => $response['id'])));
			}
			
			$this->clearGroupCache();
		}
	}
	
	/**
	 * Fait l'appel API pour attribuer ou non un rang à un groupe donné et pour des utilisateurs donnés
	 * 
	 * @param Rank $rank Rank que l'on souhaite activer/désactiver
	 * @param type $groupTypeRole
	 * @param boolean $state
	 */
	public function activationRankRequest($rankUniqueName, $groupTypeRole, $state)
	{	
		$ruleWho = array(
			'domain_id'			=> $this->domainId,
			'group_parent_id'	=> $this->getGroup()->getId(),
			'group_type_id'		=> $groupTypeRole->getId()
		);
		$values = array(
			'state' => $state,
			'rank_unique_name' => $rankUniqueName,
			'who_group' => $ruleWho,
			'rule_where' => array(
				'group_id' => $this->getGroup()->getId()
			),
		);
		$response = $this->api->send('rule_create', array('route' => array(), 'values' => $values));
		if (!$state) {
			$this->api->send('rule_delete', array('route' => array('id' => $response['id'])));
		}
		
		$this->clearGroupCache();
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
		$ranks = null;
		if (null != $groupTypeRole) {
			if (false === $groupTypeRole->getSimulateRole()) {
				throw new \InvalidArgumentException('Please provide a group type type with field `simulate_role` equals to true.');
			}
			
			$ranks = $this->getRanksForRoleInCurrentGroup($groupTypeRole);	
		}
		else {
			$ranks = $this->getRanksForGroupUserInGroup($this->getParent());
		}
		
		if (null != $groupTypeRole && $groupTypeRole->getType() == 'PUPIL') {
			$filter = ModulePeer::DEFAULT_PUPIL_RANK;
		}
		elseif (null != $groupTypeRole && $groupTypeRole->getType() == 'PARENT') {
			$filter = ModulePeer::DEFAULT_PARENT_RANK;
		}
		else {
			$filter = ModulePeer::DEFAULT_OTHER_RANK;
		}
		
		$activatedModules = ModuleQuery::create()
			->add($filter, $ranks, \Criteria::IN)
		->find();
		
		return $activatedModules;
	}
	
		
	/* Fonctions liées aux ressources */
	
	/*
	 * Renvoie le ratio d'utilisation des ressources, en %, sans virgule
	 */
	public function getResourceUsageRatio()
	{
		return round($this->getResourceUsedSize() / $this->getResourceAllowedSize(),2) * 100;	
	}
	/**
	 * Renvoie la place disponible
	 * @return integer
	 */
	public function getResourceAvailableSize()
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
	 * @param string $uniqueName
	 * @param mixed $defaultValue
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
		else{
			$parent = $this->getParent();
			$current->setGroup($parent);
			if($parent)
				return $current->getAttribute($uniqueName);
			return false;
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
			$current->setGroup($this->getParent());
			$current->setAttribute($uniqueName,$value);
		}
	}
	
	///////////////   FONCTIONS LIEES AUX PERMISSIONS   \\\\\\\\\\\\\\\\\\
	
	/**
	 * Récupère la liste des permissions pour les utilisateurs ayant le rôle $groupTypeRole dans le groupe courant
	 * 
	 * @param GroupType $groupTypeRole correspond au filtre pour le rôle des utilisateurs dont on souhaite connaître la liste des permissions
	 * @return array<String> liste de nom unique des permissions que possède un utilisateur ayant le rôle $groupTypeRole dans le groupe courant
	 */
	public function getPermissionsForRoleInCurrentGroup(GroupType $groupTypeRole)
	{
		$permissions = array();
		$groups = $this->getParents();
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
			
			foreach ($response['finals_permissions_if_belongs'] as $permission)
			{
				if (!in_array($permission['unique_name'], $permissions))
				{
					$permissions[] = $permission['unique_name'];
				}
			}
		}
		
		return $permissions;
	}
	
	///////////////   FONCTIONS LIEES AUX RANGS   \\\\\\\\\\\\\\\\\\
	
	/**
	 * Récupère la liste des rangs pour un rôle donnée ($groupTypeRole) dans le groupe courant ($this->group)
	 * @param GroupType $groupTypeRole un objet de type GroupType avec le champ simulate_role = true
	 * @return array<String> liste des unique_name de chaque rang que possède le type d'utilisateur ($groupTypeRole) dans le groupe courant
	 */
	public function getRanksForRoleInCurrentGroup(GroupType $groupTypeRole)
	{
		$ranks = array();
		$groups = $this->getParents();
		$groups[] = $this->getGroup();
		foreach ($groups as $groupParent)
		{
			$response = $this->api->send('group_get_permissions_for_role', array(
				'route' => array(
					'group_id'				=> $this->getGroup()->getId(),
					'role_id'				=> $groupTypeRole->getId(),
					'group_parent_role_id'	=> $groupParent->getId(),
				)
			),false);
			// Dans le cas où on recherche dans un sous groupe role qui n'existe pas encore, la réponse de la centrale sera égale à null
			if (null == $response)
			{
				continue;
			}
			
			$ranks = array_merge($ranks, $this->extractRanksFromServerResponse($response));
		}
		return $ranks;
	}
	
	/**
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
		foreach ($response['rules'] as $rule)
		{
			if (!isset($ranks[$rule['rank_unique_name']]))
			{
				$ranks[$rule['rank_unique_name']] = $rule['rank_unique_name'];
			}
			elseif (isset($ranks[$rule['rank_unique_name']]) && false == $rule['state'])
			{
				$ranks[$rule['rank_unique_name']] = null;
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
	 * @throws InvalidArgumentException
	 */
	public function inviteUserInGroup(User $user, User $author, GroupType $groupTypeRole = null)
	{
		if (null == $user || null == $author) {
			throw new InvalidArgumentException('parameter `user` && `author` must be != null');
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
	 * Met à jour le cache pour le groupe : ATTENTION, à utiliser avec parcimonie (groupe = classe ou école maximum)
	 */
	public function clearGroupCache()
	{
		if(in_array($this->getGroup()->getGroupType()->getType(),array('CLASSROOM','SCHOOL','TEAM','CITY','SCHOOL'))){
			$this->api->resetGroup($this->getGroup()->getId());
			$this->api->resetGroupUsers($this->getGroup()->getId());
		}
	}
}
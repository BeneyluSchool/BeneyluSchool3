<?php

namespace BNS\App\CoreBundle\Right;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Eymeric Taelman
 * 
 * ATTENTION : Scope Request
 * Classe permettant de connaître les droits sur un utilisateur connecté
 */
class BNSRightManager
{	
	protected $user_manager;
	protected $api;
	protected $request;
	protected $security_context;
	protected $group_manager;
	protected $classroom_manager;
	protected $school_manager;
	protected $team_manager;
	protected $current_group;
	protected $in_front;
	
	/**
	 * Modules affichés de manière "spéciale" : ni contexte, ni hors contexte
	 * Ils ne sont pas récupérés dans getModules
	 */
	protected static $dock_special_modules = array('PROFILE','NOTIFICATION');

	/**
	 * @param \BNS\App\CoreBundle\User\BNSUserManager			$user_manager
	 * @param \BNS\App\CoreBundle\API\BNSApi					$api
	 * @param \Symfony\Component\HttpFoundation\Request			$request
	 * @param \Symfony\Component\Security\Core\SecurityContext	$security_context
	 * @param \BNS\App\CoreBundle\Group\BNSGroupManager			$group_manager
	 * @param \BNS\App\CoreBundle\Classroom\BNSClassroomManager	$classroom_manager
	 * @param \BNS\App\CoreBundle\Team\BNSTeamManager			$team_manager
	 */
	public function __construct($user_manager, $api,$request, $security_context, $group_manager, $classroom_manager, $team_manager)
	{
		$this->api = $api;
		$this->request = $request;
		$this->user_manager = $user_manager;
		$this->security_context = $security_context;
		$this->group_manager = $group_manager;
		$this->classroom_manager = $classroom_manager;
		//$this->school_manager = $school_manager;
		$this->team_manager = $team_manager;
		$this->initModelUserManager(true);
	}
	
	/*
	 *     METHODES LIEES A LA SESSION & L'INITIALISATION
	 */
	
	
	/*
	 * L'utilisateur est il connecté ?
	 * @return boolean
	 */
	public function isAuthenticated()
	{
		return null != $this->security_context->getToken() && $this->security_context->isGranted('IS_AUTHENTICATED_FULLY');
	}
	
	/*
	 * Racourci d'accès à la session
	 * @return Session
	 */
	public function getSession()
	{
		return $this->request->getSession();
	}
	
	/*
	 * Retourne le User Session
	 * @return ProxyUser
	 */
	public function getUserSession()
	{
		return $this->security_context->getToken()->getUser();
	}
	
	/*
	 * Retourne l'Id du User en Session
	 * @return Int
	 */
	public function getUserSessionId()
	{
		return $this->security_context->getToken()->getUser()->getId();
	}
	
	/*
	 * Retourne l'utilisateur tel que modélisé en BDD
	 * @return User
	 */
	public function getModelUser()
	{
		return $this->getUserSession()->getUser();
	}
	
	/**
	 * 
	 * Initialisation du UserManager avec l'utilisateur en cours
	 * @param boolean $withRights Veux-t-on également initialiser les droits ?
	 * @return UserManager
	 */
	public function initModelUserManager($withRights = false)
	{
		if($this->isAuthenticated()){
			$user = $this->getModelUser();
			$userManager = $this->user_manager;
			$userManager->setUser($user);
			if($withRights)
				$userManager->setRights($this->getRights());
			return $userManager;
		}
	}
	
	public function getUserManager()
	{
		return $this->user_manager;
	}
	
	/*
	 * A utiliser pour interdire une action dans le controller
	 * @param bool $boolean 
	 */
	public function forbidIf($boolean)
	{
		 if ($boolean) {
			throw new AccessDeniedHttpException('Forbidden Action');
		 }
	}
	
	/*
	 * Initialisation des droits à la connexion
	 * Ne renvoie rien, set en session les droits
	 */ 
	public function initRights()
	{
		$um = $this->initModelUserManager(false);
		$this->rights = $um->getRights();
	}
	
	public function getUserType()
	{
		if(!$this->getSession()->has('user_type')){
			$rights = $this->getRights();
			$roles = $rights[$this->getCurrentGroupId()]['roles'];
			$roles = GroupTypeQuery::create()->findById($roles);
			$isChild = false;
			foreach($roles as $role){
				if($role->getType() == 'PUPIL'){
					$isChild = true;
				}
			}
			if($isChild){
				$this->getSession()->set('user_type','child');
			}else{
				$this->getSession()->set('user_type','adult');
			}
		}
		return $this->getSession()->get('user_type');
	}
	
	/**
	 * L'utilisateur en cours est-il un enfant
	 * @return boolean
	 */
	public function isChild()
	{
		return $this->getUserType() == 'child';
	}
	
	/**
	 * L'utilisateur en cours est-il un adulte ,
	 * @return boolean
	 */
	public function isAdult()
	{
		return !$this->isChild();
	}
	
	
	/*
	 *      METHODES LIEES AUX DROITS
	 */
	
	
	/* 
	 * Obtient les droits stockés en session, depuis le userManager
	 * @return array
	 */
	public function getRights()
	{ 
		if(!isset($this->rights)){
			$this->initRights();
		}
		return $this->rights;
	}
	
	public function getManagementRights()
	{
		$rights = $this->getRights();
		
		$mgt = array();
		foreach($rights[$this->getCurrentGroupId()]['permissions'] as $right){
			if(strpos($right,"_CREATE")){
				$mgt[] = $right;
			}
		}
		return $mgt;
	}
	
	public function getManageableGroupTypes()
	{
		$groupTypes = array();
		foreach($this->getManagementRights() as $right){
			$groupTypes[] = GroupTypeQuery::create()->joinWithI18n($this->getLocale())->findOneByType(strstr($right,'_', true));
		}
		return $groupTypes;
	}
	
	/**
	 * Attribue à l'utilisateur en cours les droits (en plus) de l'utilisateur en paramètre
	 * @param User $user
	 */
	public function getUserRights(User $user)
	{
		$um = $this->user_manager;
		$um->setUser($user);
		$userRights = $um->getRights();
		$um->setUser($this->getUserSession());
		$merge = array_merge($this->getRights(),$userRights);
		$googMerge = array();
		foreach($merge as $group){
			$googMerge[$group['id']] = $group;
		}
		$um->saveRights($googMerge);
	}
        
	/**
	 * On met en session le token d'authentification pour l'API mail
	 * @param type $token 
	 */
	public function setMailToken($token)
	{
		$this->getSession()->set('bns_mail_token',$token);
	}

	/**
	 * On récupère si il existe le token d'authentification de l'API mail
	 * @return null 
	 */
	public function getMailToken()
	{
		if($this->getSession()->has('bns_mail_token'))
		{
			return $this->getSession()->get('bns_mail_token');
		}
		return null;
	}
			
	/*
	 * Recharge les droits
	 */
	public function reloadRights()
	{
		$this->initRights();
	}
	
	/*
	 * Selon les droits, retourne la route à utiliser pour les utilisateurs se connectant
	 */
	public function getRedirectLoginRoute()
	{
		return $this->getRedirectRouteOfCurrentGroup(false);
	}
	
	public function getRedirectRouteOfCurrentGroup($for_admin = false)
	{
		switch ($this->getCurrentGroupType())
		{
			case 'CLASSROOM':
				$route = 'BNSAppClassroomBundle';
			break;
			case 'TEAM':
				$route = 'BNSAppTeamBundle';
			break;
			case 'ENVIRONMENT':
				if($this->hasRight('ADMIN_ACCESS'))
					return 'BNSAppAdminBundle_front';
				return 'BNSAppGroupBundle_back';
			break;
			default:
				return 'BNSAppGroupBundle_front';
			break;
		}
		return !$for_admin ? $route . '_front' : $route. '_back';
	}

	/*
	 * L'utilisateur a-t-il le droit passé en paramètre ?
	 * @param int $group_id : Id du groupe sur lequel on demande le droit : par défaut le groupe en cours
	 * @param int $permission_unique_name : Unique name de la permission
	 * @return bool
	 */
	public function hasRight($permission_unique_name,$group_id = null)
	{
		if ($group_id == null) {
			$group_id = $this->getCurrentGroupId();
		}
		
		return $this->user_manager->hasRight($permission_unique_name,$group_id);
	}
	
	/**
	 * @param array $permissionUniqueNames Les permissions à vérifier
	 * @param int $groupId L'ID du groupe
	 * 
	 * @return boolean Vrai si TOUTES les permissions sont présentes, faux si AU MOINS une des permissions est manquante
	 */
	public function hasRights(array $permissionUniqueNames, $groupId = null)
	{
		foreach ($permissionUniqueNames as $permissionUniqueName) {
			if (!$this->hasRight($permissionUniqueName, $groupId)) {
				return false;
			}
		}
		
		return true;
	}
    
	/*
	 * L'utilisateur a-t-il le droit passé en paramètre dans un des groupes?
	 * @param int $groupIds: array d'ID de groupes
	 * @param int $permission_unique_name : Unique name de la permission
	 * @return boolean vrai si l'utilisateur a le droit dans au moins un des groupes
	 */
	public function hasRightInSomeGroups($permission_unique_name,$groupIds)
	{
        
        foreach($groupIds as $groupId) {
            if($this->user_manager->hasRight($permission_unique_name,$groupId)) {
                return true;
            }
        }
        
		return false;
	}
        
	/*
	 * Renvoie un booléen selon si j'ai quelque part (n'importe quel groupe) le droit
	 * @param string $permission_unique_name : la permission en question
	 */
	public function hasRightSomeWhere($permission_unique_name)
	{
		return $this->user_manager->hasRightSomeWhere($permission_unique_name);
	}
	
	/**
	 * Vérifie si l'utilisateur courant possède des permissions un des groupes parmi lesquels il appartient
	 * 
	 * @param array $permissionUniqueNames Les permissions à vérifier
	 * 
	 * @return boolean Vrai si TOUTES les permissions sont présentes, faux si AU MOINS une des permissions est manquante
	 */
	public function hasRightsSomeWhere(array $permissionUniqueNames)
	{
		foreach ($permissionUniqueNames as $permissionUniqueName) 
		{
			if (!$this->hasRightSomeWhere($permissionUniqueName)) 
			{
				return false;
			}
		}
		
		return true;
	}
	
	/*
	 * Raccourci pour interdire une action si pas de droit cf hasRight
	 */
	public function forbidIfHasNotRight($permission_unique_name, $group_id = null)
	{
		$this->forbidIf(!$this->hasRight($permission_unique_name, $group_id));
	}
	
	/*
	 * Raccourci pour interdire une action si pas de droit cf hasRightSomewhere
	 */
	public function forbidIfHasNotRightSomeWhere($permission_unique_name)
	{
		$this->forbidIf(!$this->hasRightSomeWhere($permission_unique_name));
	}
	
	/*
	 * Raccourci vers la méthode hasRight pour le pattern _ACTIVATION
	 */
	public function canActivate($module_unique_name,$group_id = null)
	{
		$group_id = $this->getCurrentGroupId();
		return $this->hasRight(strtoupper($module_unique_name) . '_ACTIVATION');
	}
	
	/*
	* Renvoie les id des groupes où j'ai la permission
	* @param string $permission_unique_name : la permission en question
	* @return array : tableau d'ids des groupes
	*/
	public function getGroupIdsWherePermission($permission_unique_name)
	{ 
		return $this->user_manager->getGroupIdsWherePermission($permission_unique_name);
	}
	
	/*
	 * Renvoie les groupes où j'ai la permission
	 * @params String $permission_unique_name la permission en question
	 * @return GroupCollection : les groupes en question
	 */
	public function getGroupsWherePermission($permission_unique_name)
	{
		return $this->user_manager->getGroupsWherePermission($permission_unique_name);
	}
	
	/*
	 * Retourne les groupes auquel l'utilisateur appartient
	 * @return GroupCollection : les groupes en question
	 */
	public function getGroupsIBelong()
	{
		return $this->user_manager->getGroupsUserBelong();
	}
	
	/*
	 * Renvoie les groups du user en cours : il n'appartient pas forcément à ces groupes mails il a des droits dessus
	 */
	public function getGroups($includeCurrentGroupContext = false)
	{
	    $currentGroupContextId = null;
            if (false === $includeCurrentGroupContext)
            {
                $currentGroupContextId = $this->getCurrentGroupId();
            }
            
            $rights = $this->getRights();
            $groups_ids = array();
            foreach($rights as $group)
            {
                if ($currentGroupContextId == $group['id'])
                {
                    continue;
                }
                
                $groups_ids[] = $group['id'];
            }

            return GroupQuery::create()
                ->joinWith('GroupType')
                ->filterById($groups_ids)
            ->find();
	}
	
	/*
	 * Retourne les utilisateurs ayant la permission dans un groupe donné
	 * @param string $permission_unique_name : la permission en quesion
	 * @param int $group_id : le groupe en question
	 * @return UserCollection : les utilisateurs renvoyés par l'API
	 */
	public function getUsersThatHaveThePermissionInGroup($permission_unique_name, $group_id)
	{
		return $this->api->send('group_get_users_with_permission',
		array(
			'route' =>  array(
				'group_id' => $group_id,
				'permission_unique_name' => $permission_unique_name)
			)
		);
	}
	
	///////////////////////  CONTEXTE  \\\\\\\\\\\\\\\\\\\\\\\
	/*
	 * Le contexte est le groupe dans lequel je navigue
	 */
	
	
	/*
	 * Appelé à la connexion pour initialise le contexte de navigation
	 */
	public function initContext()
	{
		//De base : classe avec enseignant ou élève
		$rights = $this->getRights();
		foreach ($rights as $groupId => $group) {
			//Admin
			// Retrieve CLASSROOM GroupType
			$classroomGroupTypeId = GroupTypeQuery::create()->add(GroupTypePeer::TYPE, 'CLASSROOM')->findOne()->getId();
			//Si classe et [TODO] compte = élève ou enseignant ou parent
			if ($classroomGroupTypeId == $group['group_type_id']) {
				$this->setContext($groupId);
				break;
			}
			$this->setContext($groupId);	
		}
		return;
	}
	
	/*
	 * Setter du context : dans quel groupe je vais naviguer
	 * @params int $group_index : index du groupe dans les "rights"
	 */
	public function setContext($groupId)
	{
		$this->getSession()->set('bns_context', $groupId);
	}
	
	/*
	 * Getter du context : dans quel groupe je navigue
	 */
	public function getContext()
	{
		if (!$this->getSession()->has('bns_context')) {
			$this->initContext();
		}
		
		$rights = $this->getRights();
		
		return $rights[$this->getSession()->get('bns_context')];
	}
	
	public function switchContext(Group $group)
	{
		$this->reloadRights();
		$rights = $this->getRights();
		$this->forbidIf(!isset($rights[$group->getId()]));
		$this->setContext($group->getId());
	}
	
	/*
	 * Renvoie l'Id du groupe en context
	 */
	public function getCurrentGroupId()
	{
		return $this->getSession()->get('bns_context');
	}
	
	/**
	 * @return Group 
	 */
	public function getCurrentGroup()
	{
		if (!isset($this->current_group)) {
			$this->current_group = GroupQuery::create()
				->joinWith('GroupType')
			->findPk($this->getCurrentGroupId());
		}
		
		return $this->current_group;
	}
	
	/*
	 * Renvoie le manager du groupe en question
	 */
	public function getCurrentGroupManager()
	{	$this->group_manager->setGroup($this->getCurrentGroup());
		switch ($this->getCurrentGroupType()) {
			case 'CLASSROOM':
				$this->classroom_manager->setGroup($this->getCurrentGroup());
				return $this->classroom_manager;
			break;
			/*case 'SCHOOL':
				$this->school_manager->setGroup($this->getCurrentGroup());
				return $this->school_manager;
			break;*/
			case 'TEAM':
				$this->team_manager->setGroup($this->getCurrentGroup());
				return $this->team_manager;
			break;
			default:
				$this->group_manager->setGroup($this->getCurrentGroup());
				return $this->group_manager;
			break;
		}
	}
	
	public function getCurrentGroupType()
	{
		$context = $this->getContext();
		return $context['group_type'];
	}
	
	/**
	 * Retourne la liste des classes auxquels l'utilisateur $user appartient; Le groupe parent
	 * pour chaque classe sont également setté
	 * 
	 * @return array<Group> liste des groupes de type CLASSROOM parmi le tableau des droits
	 */
	public function getClassroomsUserBelong(User $user)
	{
		$rights = $this->user_manager->setUser($user)->getRights();
		$classroomGroupType = GroupTypeQuery::create()->findOneByType('CLASSROOM');
		$classroomIds = array();
		$schoolIds = array();
		foreach ($rights as $group) {
			if ($classroomGroupType->getId() == $group['group_type_id'])
			{
				$classroomIds[] = $group['id'];
				$schoolIds[] = $group['group_parent_id'];
			}
		}
		
		$classroomsAndSchools = GroupQuery::create()
			->add(GroupPeer::ID, array_merge($classroomIds, $schoolIds), \Criteria::IN)
		->find();
		
		$classrooms = array();
		
		foreach ($classroomsAndSchools as $group)
		{
			if ($classroomGroupType->getId() == $group->getGroupTypeId()) {
				foreach ($classroomsAndSchools as $school) {
					if ($rights[$group->getId()]['group_parent_id'] == $school->getId()) {
						$group->setParent($school);
					}
				}
				
				$classrooms[] = $group;
			}
		}
		
		return $classrooms;
	}
	
	public function isInClassroomGroup()
	{
		$context = $this->getContext();
		if($context['group_type'] == 'CLASSROOM') {
			return true;
		}
		
		return false;
	}
	
	public function isInSchoolGroup()
	{
		$context = $this->getContext();
		if ($context['group_type'] == 'SCHOOL') {
			return true;
		}
			
		return false;
	}
	
	public function isInTeamGroup()
	{
		$context = $this->getContext();
		if ($context['group_type'] == 'TEAM') {
			return true;
		}
			
		return false;
	}
	
	
	/*
	 *      METHODES LIEES AUX MODULES & LA NAVIGATION ENTRE MODULES
	 * 
	 */
	
	public function setInFront($value)
	{
		$this->in_front = $value;
	}
	
	/*
	 * L'utilisateur est il du coté "front"
	 * @return bool 
	 */
	public function isInFront()
	{
		if (!isset($this->in_front)) {
			$controller = $this->getCurrentController();
			$this->setInFront(strstr(strstr($controller,'Front'),'Controller',true) == 'front'); 
		}
		
		return $this->in_front;
	}
	
	/*
	 * L'utilisateur est il du coté "back"
	 * @return bool 
	 */
	public function isInBack()
	{
		return !$this->isInFront();
	}
	
	/*
	 * Renvoie le controler (chemin complet) dans lequel je navigue
	 */
	public function getCurrentController()
	{
		return $this->request->get('_controller');
	}
	
	/*
	 * Renvoie le Bundle dans lequel je navigue
	 */
	public function getCurrentBundle()
	{
		return str_replace('BNSApp','',strchr($this->getCurrentController(),':',true));
	}
	
	/*
	 * Renvoie les modules pour le dock, séparés en constant / context
	 * TODO : Surement optimisable
	 */
	public function getDockModules($currentModuleUniqueName, $isFront)
	{
	    $currentContextGroupRights = $this->getContext();
		if (true === $isFront)
		{
			$pattern = '_ACCESS';
		}
		else
		{
			$pattern = '_ACCESS_BACK';
		}

		$allGlobalModules = ModuleQuery::create()
			->joinWithI18n($this->getLocale())
			->filterByIsContextable(false)
			->filterByUniqueName(self::$dock_special_modules,  \Criteria::NOT_IN)
		->find();

		$commonModules = array();
		$currentModule = null;
		$moduleContextBackAccess = false;
		foreach ($allGlobalModules as $globalModule)
		{
			if ($this->hasRightSomeWhere($globalModule->getUniqueName().$pattern))
			{
				$commonModules[] = $globalModule;
				
				if ($currentModuleUniqueName == $globalModule->getUniqueName())
				{
					$currentModule = $globalModule;
					$moduleContextBackAccess = $this->hasRightSomeWhere($currentModuleUniqueName.'_ACCESS_BACK');
				}
			}
		}
            
		$modulesUniqueName = array();
		foreach ($currentContextGroupRights['permissions'] as $permission)
		{
			if(strstr($permission,'_') == $pattern)
			{
				$moduleUniqueName = strstr($permission,'_', true);
				if (!in_array($moduleUniqueName, $modulesUniqueName))
				{
					$modulesUniqueName[] = $moduleUniqueName;
				}
			}
		}
		$modules = ModuleQuery::create()
			->joinWithI18n($this->getLocale())
			->add(ModulePeer::UNIQUE_NAME, $modulesUniqueName, \Criteria::IN)
		->find();
            
		$contextModules = array();
		foreach ($modules as $module) {
			if (true === $module->getIsContextable()) {
				$contextModules[] = $module;
			}

			if (null == $currentModule && $currentModuleUniqueName == $module->getUniqueName()) {
				$currentModule = $module;
				$moduleContextBackAccess = $this->hasRight($currentModuleUniqueName.'_ACCESS_BACK');
			}
		}

		/*if (null == $currentModule) {
			//throw new NotFoundHttpException('Module unique name given is invalid: '.$currentModuleUniqueName.'!');
		}*/
            
		return array(
			'global'                    => $commonModules,
			'context'                   => $contextModules,
			'current_module'            => $currentModule,
			'moduleContextBackAccess'   => $moduleContextBackAccess,

		);
	}
	
	/*
	 * Retourne les modules activales pour un groupe donné
	 * @param int $group_id
	 */
	public function getActivableModules($groupId = null)
	{
		$group = null;
		if(null == $groupId)
		{
			$group = $this->getCurrentGroup();
		}
		else
		{    
			$group = $this->group_manager->findGroupById($groupId);
		}

		$groupRights = $this->getContext();
		$activableModules = array();
		if (is_array($groupRights['permissions']))
		{
			foreach ($groupRights['permissions'] as $permission)
			{
				if (substr(strstr($permission, '_'), 1) == 'ACTIVATION')
				{
					$activableModules[] = strstr($permission, '_', true);
				}
			}
		}

		return  ModuleQuery::create()
			->joinWithI18n($this->getLocale())
			->add(ModulePeer::UNIQUE_NAME, $activableModules, \Criteria::IN)
		->find();
	}
	
	public function getContextModules()
	{
		
	}
	
	/*
	 *      METHODES LIEES A LA CULTURE
	 */
	
	/**
	 * @param string $lang
	 */
	public function setLocale($lang)
	{
		$this->getUserSession()->setLang($lang);
		$this->getUserSession()->save();
	}
	
	/*
	 * Récupération de la locale depuis le BNSAccess
	 */
	public function getLocale()
	{
		return BNSAccess::getLocale();
	} 
}
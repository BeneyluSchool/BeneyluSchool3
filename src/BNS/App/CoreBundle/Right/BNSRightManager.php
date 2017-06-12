<?php

namespace BNS\App\CoreBundle\Right;

use BNS\App\CoreBundle\Exception\NoContextException;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\HomeworkBundle\Model\HomeworkGroup;
use BNS\App\HomeworkBundle\Model\HomeworkGroupQuery;
use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\NoteBookBundle\Model\NoteBookQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\InfoBundle\Model\AnnouncementUserQuery;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Eymeric Taelman
 *
 * ATTENTION : Scope Request
 * Classe permettant de connaître les droits sur un utilisateur connecté
 */
class BNSRightManager
{

    /**
     * @deprecated do not use inject needed service
     * @var ContainerInterface
     */
    private $container;

    protected $user_manager;
    protected $api;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    protected $security_context;
    protected $group_manager;
    protected $classroom_manager;
    protected $school_manager;
    protected $team_manager;
    protected $current_group;
    protected $in_front;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Modules affichés de manière "spéciale" : ni contexte, ni hors contexte
     * Ils ne sont pas récupérés dans getModules
     */
    protected static $dock_special_modules = array('PROFILE', 'NOTIFICATION', 'HELP', 'MAIN');

    /**
     * @param \BNS\App\CoreBundle\User\BNSUserManager			$user_manager
     * @param \BNS\App\CoreBundle\API\BNSApi					$api
     * @param \Symfony\Component\HttpFoundation\Request			$request
     * @param \Symfony\Component\Security\Core\SecurityContext	$security_context
     * @param \BNS\App\CoreBundle\Group\BNSGroupManager			$group_manager
     * @param \BNS\App\CoreBundle\Classroom\BNSClassroomManager	$classroom_manager
     * @param \BNS\App\CoreBundle\Team\BNSTeamManager			$team_manager
     */
    public function __construct($container, $user_manager, $api, RequestStack $requestStack, SessionInterface $session, $security_context, $group_manager,
        $classroom_manager, $team_manager, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->api = $api;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->user_manager = $user_manager;
        $this->security_context = $security_context;
        $this->group_manager = $group_manager;
        $this->classroom_manager = $classroom_manager;
        //$this->school_manager = $school_manager;
        $this->team_manager = $team_manager;
        $this->logger = $logger;
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
     * @return Session|null
     */
    public function getSession()
    {
        // get first session from the current request for fallback to old behavior
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getSession();
        }

        return $this->session;
    }

    /*
     * Retourne le User Session
     * @return ProxyUser
     *
     * @deprecated
     */
    public function getUserSession()
    {
        $token = $this->security_context->getToken();
        if ($token) {
            return $token->getUser();
        }

        return null;
    }

    /*
     * Retourne l'Id du User en Session
     * @return Int
     *
     * @deprecated
     */
    public function getUserSessionId()
    {
        $user = $this->getUserSession();
        if ($user) {
            return $user->getId();
        }

        return null;
    }

    /*
     * Retourne l'utilisateur tel que modélisé en BDD
     * @return User
     */

    public function getModelUser()
    {
        return $this->getUserSession();
    }

    /**
     *
     * Initialisation du UserManager avec l'utilisateur en cours
     * @param boolean $withRights Veux-t-on également initialiser les droits ?
     * @return UserManager
     */
    public function initModelUserManager($withRights = false)
    {
        if ($this->isAuthenticated()) {
            $user = $this->getUserSession();
            $userManager = $this->user_manager;
            $userManager->setUser($user);

            if ($withRights) {
                $userManager->setRights($this->getRights());
            }

            return $userManager;
        }

        return null;
    }

    /**
     * @return \BNS\App\CoreBundle\User\BNSUserManager
     */
    public function getUserManager()
    {
        if($this->isAuthenticated())
        {
            $this->user_manager->setUser($this->getUserSession());
        }
        return $this->user_manager;
    }

    /**
     * @return BNSGroupManager
     */
    public function getGroupManager()
    {
        return $this->group_manager;
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
        return $this->getUserManager()->getUserType();
    }

    /**
     * L'utilisateur en cours est-il un enfant
     * @return boolean
     */
    public function isChild()
    {
        return $this->getUserManager()->isChild();
    }

    /**
     * L'utilisateur en cours est-il un adulte ,
     * @return boolean
     */
    public function isAdult()
    {
        return $this->getUserManager()->isAdult();
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
        if (!isset($this->rights)) {
            $this->initRights();
        }
        return $this->rights;
    }

    /**
     * Gets all permissions across all groups
     *
     * @return array
     */
    public function getAllPermissions()
    {
        $permissions = [];
        foreach ($this->getRights() as $rightsByGroup) {
            $permissions = array_merge($permissions, $rightsByGroup['permissions']);
        }

        return array_unique($permissions);
    }

    public function getRightsInGroup($groupId, $groupType = null)
    {
        $rights = $this->getRights();
        if(isset($rights[$groupId])) {
            return $rights[$groupId];
        } elseif(null != $groupType && 'TEAM' == $groupType) {
            $response = $this->api->send('group_get_permissions_for_role', array(
				'route' => array(
					'group_id'				=> $groupId,
					'role_id'				=> $this->user_manager->getUser()->getHighRoleId(),
					'group_parent_role_id'	=> $this->getCurrentEnvironment()->getId(),
				)
			));

            $rights['permissions'] = array();
			if($response != null)
			{
				foreach ($response['finals_permissions_if_belongs'] as $permission)
				{
					if (!in_array($permission['unique_name'], $rights['permissions']))
					{
						$rights['permissions'][] = $permission['unique_name'];
					}
				}
			}
            return $rights;
        } else {
            return array();
        }
    }

    public function getManageableGroupTypes($isRole = null, $right = 'VIEW')
    {
        return $this->user_manager->getManageableGroupTypesInGroup($this->getCurrentGroup(),$isRole, $right);
    }

    public function getManageableGroupIds($right = 'VIEW')
    {
        return $this->user_manager->getManageableGroupIdsInGroup($this->getCurrentGroup(), $right);
    }

    /**
     * Méthode permettant de savoir qur quels groupes l'utilisateur a le droit d'agir
     */

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
        $myRights = $this->getRights();

        foreach ($userRights as $groupId => $group) {
            if (isset($myRights[$groupId])) {
                $myRights[$groupId]['roles'] = array_merge($myRights[$groupId]['roles'], $userRights[$groupId]['roles']);
                $myRights[$groupId]['permissions'] = array_merge($myRights[$groupId]['permissions'],
                    $userRights[$groupId]['permissions']);
            } else {
                $myRights[$groupId] = $group;
            }
        }

        $um->saveRights($myRights);
    }

    /**
     * On met en session le token d'authentification pour l'API mail
     * @param type $token
     */
    public function setMailToken($token)
    {
        $this->getSession()->set('bns_mail_token', $token);
    }

    /**
     * On récupère si il existe le token d'authentification de l'API mail
     * @return null
     */
    public function getMailToken()
    {
        if ($this->getSession()->has('bns_mail_token')) {
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

    /**
     * @param type $for_admin
     *
     * @return string
     */
    public function getRedirectRouteOfCurrentGroup($for_admin = false)
    {
        switch ($this->getCurrentGroupType()) {
            case 'CLASSROOM':
                $route = 'BNSAppClassroomBundle';
                break;

            case 'SCHOOL':
                if ($this->hasRight('SCHOOL_ACCESS')) {
                    $route = 'BNSAppSchoolBundle';
                }else{
                    return 'BNSAppGroupBundle_front';
                }
                break;
            case 'TEAM':
                $route = 'BNSAppTeamBundle';
                break;
            case 'ENVIRONMENT':
                if ($this->hasRight('ADMIN_ACCESS')) {
                    return 'BNSAppAdminBundle_front';
                }
                return 'BNSAppGroupBundle_front';

            case 'DISCONNECT': return 'disconnect_user';

            default: return 'BNSAppGroupBundle_front';
        }

        return !$for_admin ? $route.'_front' : $route.'_back';
    }

    /*
     * L'utilisateur a-t-il le droit passé en paramètre ?
     * @param int $group_id : Id du groupe sur lequel on demande le droit : par défaut le groupe en cours
     * @param int $permission_unique_name : Unique name de la permission
     * @return bool
     */

    public function hasRight($permission_unique_name, $group_id = null)
    {
        if ($group_id == null) {
            $group_id = $this->getCurrentGroupId();
        }

        return $this->getUserManager()->hasRight($permission_unique_name, $group_id);
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

    public function hasRightInSomeGroups($permission_unique_name, $groupIds)
    {
        foreach ($groupIds as $groupId) {
            if ($this->getUserManager()->hasRight($permission_unique_name, $groupId)) {
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
        return $this->getUserManager()->hasRightSomeWhere($permission_unique_name);
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
        foreach ($permissionUniqueNames as $permissionUniqueName) {
            if (!$this->hasRightSomeWhere($permissionUniqueName)) {
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

    public function canActivate($module_unique_name, $group_id = null)
    {
        $group_id = $this->getCurrentGroupId();
        return $this->hasRight(strtoupper($module_unique_name).'_ACTIVATION');
    }

    /*
     * Renvoie les id des groupes où j'ai la permission
     * @param string $permission_unique_name : la permission en question
     * @return array : tableau d'ids des groupes
     */

    public function getGroupIdsWherePermission($permission_unique_name)
    {
        return $this->getUserManager()->getGroupIdsWherePermission($permission_unique_name);
    }

    /**
     * Renvoie les groupes où j'ai la permission
     *
     * @param string $permission_unique_name la permission en question
     * @return array|\BNS\App\CoreBundle\Model\Group[]|\PropelObjectCollection
     */
    public function getGroupsWherePermission($permission_unique_name)
    {
        return $this->getUserManager()->getGroupsWherePermission($permission_unique_name);
    }

    /*
     * Retourne les groupes auquel l'utilisateur appartient
     * @return GroupCollection : les groupes en question
     */

    public function getGroupsIBelong()
    {
        return $this->getUserManager()->getGroupsUserBelong();
    }

    /**
     * Renvoie les groups du user en cours : il n'appartient pas forcément à ces groupes mails il a des droits dessus
     *
     * @param bool $includeCurrentGroupContext
     * @return \PropelObjectCollection|Group[]
     */
    public function getGroups($includeCurrentGroupContext = false)
    {
        $currentGroupContextId = null;
        if (false === $includeCurrentGroupContext) {
            $currentGroupContextId = $this->getCurrentGroupId();
        }

        $rights = $this->getRights();
        $groups_ids = array();
        foreach ($rights as $group) {
            if ($currentGroupContextId == $group['id']) {
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
     *
     * @param string $permission_unique_name : la permission en quesion
     * @param int	 $group_id : le groupe en question
     *
     * @return UserCollection : les utilisateurs renvoyés par l'API
     */

    public function getUsersThatHaveThePermissionInGroup($permission_unique_name, $group_id)
    {
        return $this->api->send('group_get_users_with_permission_new',
                array(
                'route' => array(
                    'group_id' => $group_id,
                    'permission_unique_name' => $permission_unique_name
                )
                )
        );
    }

    /**
     * get the list of user's id with permission $permissionUniqueName in group $groupId
     *
     * @param string $permissionUniqueName
     * @param int $groupId
     * @return array|int[]
     */
    public function getUserIdsWithPermissionInGroup($permissionUniqueName, $groupId)
    {
        return $this->api->send('group_get_user_ids_with_permission', [
                'route' => [
                    'group_id' => $groupId,
                    'permission_unique_name' => $permissionUniqueName
                ]
            ]
        );
    }

    /*
     * Appelé à la connexion pour initialise le contexte de navigation
     */
    public function initContext()
    {
        /** @var User $user */
        $user = $this->getUserSession();

        if ($user && $favoriteGroupId = $user->getFavoriteGroupId()) {
            $rights = $this->getRightsInGroup($favoriteGroupId);
            if ($rights && isset($rights['permissions']) && count($rights['permissions']) > 0) {
                $this->setContext($favoriteGroupId);

                return true;
            }
        }

        // De base : classe avec enseignant ou élève
        $initOrder = array('CLASSROOM', 'SCHOOL', 'CIRCONSCRIPTION', 'PARTNERSHIP', 'ENVIRONMENT', null);

        foreach ($initOrder as $groupType) {
            $groups = $this->getUserManager()->getGroupsUserBelong($groupType);
            if (!$groups->isEmpty()) {
                $this->setContext($groups->getFirst()->getId());

                return true;
            }
        }

        return false;
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

        // L'utilisateur n'a plus ou pas de groupe lié, on le déconnecte
        if (count($rights) == 0) {
            throw new NoContextException('No group');
        }

        if (!isset($rights[$this->getSession()->get('bns_context')])) {
            $this->initContext();
            $rights = $this->getRights();
        }

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

    /**
     * @return Group
     */
    public function setCurrentGroup(Group $group)
    {
        return $this->current_group = $group;
    }

    /*
     * Renvoie le manager du groupe en question
     */

    public function getCurrentGroupManager()
    {
        $this->group_manager->setGroup($this->getCurrentGroup());
        switch ($this->getCurrentGroupType()) {
            case 'CLASSROOM':
                $this->classroom_manager->setGroup($this->getCurrentGroup());
                return $this->classroom_manager;
                break;
            /* case 'SCHOOL':
              $this->school_manager->setGroup($this->getCurrentGroup());
              return $this->school_manager;
              break; */
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
     * @return Group
     * Renvoie l'envirronnement en cours
     * Pour rappel : l'application s'éxécute toujours dans un contexte ayant un envirronnement
     */
    public function getCurrentEnvironment()
    {
        if ($this->getCurrentGroup()->getGroupType()->getType() == "ENVIRONMENT") {
            return $this->getCurrentGroup();
        }
        $this->group_manager->setGroup($this->getCurrentGroup());
        return $this->group_manager->getEnvironment();
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
            if ($classroomGroupType->getId() == $group['group_type_id']) {
                $classroomIds[] = $group['id'];
                $groupParents = $this->group_manager->getParents($this->group_manager->setGroupById($group['id']));
                foreach ($groupParents as $parent) {
                    $schoolIds[] = $parent->getId();
                }
            }
        }

        $classroomsAndSchools = GroupQuery::create()
            ->add(GroupPeer::ID, array_merge($classroomIds, $schoolIds), \Criteria::IN)
            ->find();

        $classrooms = array();

        foreach ($classroomsAndSchools as $group) {
            if ($classroomGroupType->getId() == $group->getGroupTypeId()) {
                $parents = $this->group_manager->getParents($group);
                $parents_school = array();
                foreach ($classroomsAndSchools as $school) {
                    foreach ($parents as $parent) {
                        if ($parent->getId() == $school->getId()) {
                            $parents_school[] = $school;
                        }
                    }
                }
                $group->setParents($parents_school);
                $classrooms[] = $group;
            }
        }

        return $classrooms;
    }

    /**
     * @return boolean
     */
    public function isInClassroomGroup()
    {
        $context = $this->getContext();
        if ($context['group_type'] == 'CLASSROOM') {
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
            $this->setInFront(strstr(strstr($controller, 'Front'), 'Controller', true) == 'front');
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
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->get('_controller');
        }

        return null;
    }

    /*
     * Renvoie le Bundle dans lequel je navigue
     */

    public function getCurrentBundle()
    {
        return str_replace('BNSApp', '', strchr($this->getCurrentController(), ':', true));
    }

    /*
     * Renvoie les modules pour le dock, séparés en constant / context
     * TODO : Surement optimisable
     */

    public function getDockModules($currentModuleUniqueName, $isFront)
    {
        $currentContextGroupRights = $this->getContext();
        if (true === $isFront) {
            $pattern = '_ACCESS';
        } else {
            $pattern = '_ACCESS_BACK';
        }

        $allGlobalModules = ModuleQuery::create()
            ->filterByIsContextable(false)
            ->filterByUniqueName(self::$dock_special_modules, \Criteria::NOT_IN)
            ->filterByIsEnabled(true)
            ->find();

        $commonModules = array();
        $currentModule = null;
        $moduleContextBackAccess = false;

        foreach ($allGlobalModules as $globalModule) {
            if ($this->hasRightSomeWhere($globalModule->getUniqueName().$pattern)) {
                $commonModules[] = $globalModule;

                if ($currentModuleUniqueName == $globalModule->getUniqueName()) {
                    $currentModule = $globalModule;
                    $moduleContextBackAccess = $this->hasRightSomeWhere($currentModuleUniqueName.'_ACCESS_BACK');
                }
            }
        }

        $modulesUniqueName = array();
        foreach ($currentContextGroupRights['permissions'] as $permission) {
            if (preg_match('/(.*)' . $pattern . '$/', $permission, $matches)) {
                $moduleUniqueName = $matches[1];
                if (!in_array($moduleUniqueName, $modulesUniqueName)) {
                    $modulesUniqueName[] = $moduleUniqueName;
                }
            }
        }

        $modules = ModuleQuery::create('m')
            ->where('m.UniqueName IN ?', $modulesUniqueName)
            ->where('m.IsEnabled = ?', true)
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

        /* if (null == $currentModule) {
          //throw new NotFoundHttpException('Module unique name given is invalid: '.$currentModuleUniqueName.'!');
          } */

        return array(
            'global' => $commonModules,
            'context' => $contextModules,
            'current_module' => $currentModule,
            'moduleContextBackAccess' => $moduleContextBackAccess
        );
    }

    /*
     * Retourne les modules activales pour un groupe donné
     *
     * @param int $group_id
     */

    public function getActivableModules($groupId = null, $groupType = null)
    {
        return $this->getModulesByPermission('ACTIVATION', $groupId, $groupType);
    }

    /*
     * Retourne les modules activales pour un partenariat donné
     *
     * @param int $group_id
     */

    public function getActivablePartnershipModules($groupId = null)
    {
        return $this->getModulesByPermission('ACTIVATION_PARTNERSHIP', $groupId);
    }

    /**
     * @param string $permission
     * @param int $groupId
     *
     * @return \PropelObjectCollection|Module[]
     */
    private function getModulesByPermission($permission, $groupId = null, $groupType = null)
    {
        if (null == $groupId) {
            $group = $this->getCurrentGroup();
        } else {
            $group = $this->group_manager->findGroupById($groupId);
        }

        if(null != $groupType && $groupType == 'TEAM') {
            $groupRights = $this->getRightsInGroup($group->getId(), $groupType);
        } else {
            $groupRights = $this->getContext();
        }

        $activableModules = array();

        if (isset($groupRights['permissions']) && is_array($groupRights['permissions'])) {
            foreach ($groupRights['permissions'] as $perm) {
                if(strpos($perm, $permission) !== false && strpos($perm, $permission) == (strlen($perm) - strlen($permission)))
                {
                    $activableModules[] = substr($perm,0,strlen($perm) - strlen($permission) - 1);
                }
            }
        }

        return ModuleQuery::create('m')
                ->where('m.UniqueName IN ?', $activableModules)
                ->where('m.IsEnabled = ?', true)
                ->find();
    }

    /**
     * @param string $permission
     * @param int $groupId
     *
     * @return array<Module>
     */
    public function getModulesByPermissionPattern($pattern)
    {
        $currentContextGroupRights = $this->getContext();

        //Récupération des modules activés par ordre croissant sur le label
        $allModules = ModuleQuery::create()
            ->filterByIsEnabled(true)
            ->find();

        $commonModules = array();

        foreach ($allModules as $globalModule) {
            if ($this->hasRightSomeWhere($globalModule->getUniqueName().$pattern)) {
                $commonModules[] = $globalModule;
            }
        }

        $modulesUniqueName = array();
        foreach ($currentContextGroupRights['permissions'] as $permission) {
            if (strstr($permission, '_') == $pattern) {
                $moduleUniqueName = strstr($permission, '_', true);
                if (!in_array($moduleUniqueName, $modulesUniqueName)) {
                    $modulesUniqueName[] = $moduleUniqueName;
                }
            }
        }

        $activatedModules = ModuleQuery::create('m')
            ->where('m.UniqueName IN ?', $modulesUniqueName)
            ->where('m.IsEnabled = ?', true)
            ->find();

        return $activatedModules;
    }

    /*
     * Renvoie la liste des modules accessibles, quelque soit le contexte
     * return array<Module>
     */
    public function getModulesReachable()
    {
        $allModules = ModuleQuery::create()
            ->filterByIsEnabled(true)
            ->find();
        $commonModules = array();
        foreach ($allModules as $module) {
            if ($this->hasRightSomeWhere($module->getUniqueName() . '_ACCESS')) {
                $commonModules[] = $module;
            }
        }
        return $commonModules;
    }

    public function getModulesReachableUniqueNames()
    {
        $result = array();
        foreach($this->getModulesReachable() as $module)
        {
            $result[] = $module->getUniqueName();
        }
        return $result;
    }


    /**
     * @param int $groupId
     *
     * @return array<Module>
     */
    public function getContextModules($groupId = null)
    {
        return $this->getModulesByPermission('ACCESS', $groupId);
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
        $this->getSession()->set('_locale', $lang);
        $this->getUserSession()->save();
    }

    /**
     * @param string $lang
     */
    public function setTimezone($timezone)
    {
        $this->getUserSession()->setTimezone($timezone);
        $this->getSession()->set('_timezone', $timezone);
        $this->getUserSession()->save();
    }

    /*
     * Récupération de la locale depuis le BNSAccess
     */

    public function getLocale()
    {
        return $this->container->get('translator')->getLocale();
    }

    /**
     * Fonctions liées à CERISE Prim
     */
    public  function hasCerise($group = null, $current = false)
    {
        if ($group) {
            // check if the $group hasCerise
            $session = $this->getSession();

            // if we check against current group we use a different session cache key
            $sessionKey = 'has_cerise';
            if ($group && $current) {
                $sessionKey .= '_' . $group->getId();
            }

            if (!$session->has($sessionKey)) {
                $authorisedEnv = $this->container->getParameter('authorised.cerise.env');
                $value = false;
                $groupManager = $this->getGroupManager();
                // get current group of groupManager to prevent issue
                $oldGroup = $groupManager->hasGroup() ? $groupManager->getGroup() : null;

                if ($group && $current) {
                    $groups = [$group];
                } else {
                    $groups = $this->getGroupsIBelong();
                }

                foreach ($groups as $g) {
                    $groupManager->setGroup($g);
                    $env = $groupManager->getEnvironment();
                    if ($env && in_array($env->getId(), $authorisedEnv)) {
                        $uai = $groupManager->getAttribute('UAI');
                        $uaiList = @unserialize($groupManager->getAttribute('CERISE_LIST'));
                        if (is_array($uaiList) && in_array($uai, $uaiList)) {
                            $value = true;
                            break;
                        }
                    }
                }

                if ($oldGroup) {
                    // put back current group into groupManager to prevent issue
                    $groupManager->setGroup($oldGroup);
                }

                $session->set($sessionKey, $value);
            }

            return $session->get($sessionKey);
        }

        return $this->container->hasParameter('has_cerise') && $this->container->getParameter('has_cerise') == true;
    }


    public function getNbNotifInfo()
    {
        if(!$this->hasRightSomeWhere('INFO_ACCESS'))
        {
            return false;
        }
        $nbAnnouncements = AnnouncementQuery::create()->filterByActivated()->count();
        $nbReadAnnouncements = AnnouncementUserQuery::create()->filterByUserId($this->getUserSessionId())->useAnnouncementQuery()->filterByActivated()->endUse()->count();
        return $nbAnnouncements - $nbReadAnnouncements;
    }

    /**
     * Fonctions liées à MEDIA Landes
     */
    public  function hasMedialandes($school = null,$current = false)
    {
        if($school && $this->container->hasParameter('authorised.medialandes.env'))
        {
            if($this->getSession()->has('has_medialandes'))
            {
                return $this->getSession()->get('has_medialandes');
            }
            $authorisedEnv = $this->container->getParameter('authorised.medialandes.env');
            $value = false;
            $gm = $this->getCurrentGroupManager();
            foreach($this->getGroupsIBelong() as $g)
            {
                $gm->setGroup($g);

                if($current == false)
                {
                    $gm->setGroup($school);
                }

                $env = $gm->getEnvironment();

                if($env && in_array($env->getId(),$authorisedEnv))
                {
                    $uai = $gm->getAttribute('UAI');
                    $uaiList = unserialize($gm->getAttribute('MEDIALANDES_LIST'));
                    if(!is_array($uaiList))
                    {
                        $uaiList = array();
                    }
                    if(in_array($uai,$uaiList))
                    {
                        $value = true;
                    }
                }
            }
            $this->getSession()->set('has_medialandes',$value);
            return $this->getSession()->get('has_medialandes');
        }

        return $this->container->hasParameter('has_medialandes') && $this->container->getParameter('has_medialandes') === true;
    }

    public function trackAnalytics($action, $object)
    {
        if($this->container->hasParameter('analytics_enabled') && $this->container->getParameter('analytics_enabled') === true)
        {
            $this->container->get('bns.analytics.manager')->track($action, $object);
        }
    }

    /**
     * Sets module activation state for the given group, module and role.
     *
     * @param $groupId
     * @param $moduleUniqueName
     * @param GroupType $groupTypeRole
     * @param $requestedValue
     * @return Module
     */
    public function toggleModule($groupId, $moduleUniqueName, $groupTypeRole, $requestedValue)
    {
        $module = ModuleQuery::create()->findOneByUniqueName($moduleUniqueName);
        $this->group_manager->setGroupById($groupId);
        $this->setCurrentGroup($this->group_manager->findGroupById($groupId));

        $groupManager = $this->getCurrentGroupManager();
        $currentGroupType = $groupManager->getGroupeType($groupId);

        if ($currentGroupType->getType() == 'PARTNERSHIP') {
            // TODO: restore check by groupId
            $groupManager->activationModuleRequest($module, $groupTypeRole, $requestedValue,'PARTNERSHIP'/*, $groupId*/);
            $pm = $this->container->get('bns.partnership_manager');
            $partnershipMembers = $pm->getPartnershipMembers($groupId);
            foreach ($partnershipMembers as $member) {
                $pm->setGroup($member);
                $pm->clearGroupCache();
            }
        } else {
            $groupManager->activationModuleRequest(
                $module,
                $groupTypeRole,
                $requestedValue,
                null,
                $groupId
            );
            $groupManager->clearGroupCache();
        }

        return $module;
    }

    public function canReadObject($objectType, $objectId)
    {
        // TODO split this check to dedicated tagged services
        switch ($objectType) {
            case 'AgendaEvent':
            case '\\BNS\\App\\CoreBundle\\Model\\AgendaEvent':
                $group = GroupQuery::create()
                    ->useAgendaQuery()
                        ->useAgendaEventQuery()
                            ->filterById($objectId)
                        ->endUse()
                    ->endUse()
                    ->findOne();

                return $group && $this->hasRight('CALENDAR_ACCESS', $group->getId());

            case 'BlogArticle':
            case '\\BNS\\App\\CoreBundle\\Model\\BlogArticle':
                /** @var Blog $blog */
                $blog = BlogQuery::create()
                        ->useBlogArticleBlogQuery()
                            ->useBlogRelQuery()
                                ->filterById($objectId)
                            ->endUse()
                        ->endUse()
                    ->findOne();

                return $blog && $this->hasRight('BLOG_ACCESS', $blog->getGroupId());

            case 'Homework':
            case '\\BNS\\App\\HomeworkBundle\\Model\\Homework':
                /** @var HomeworkGroup $group */
                $homeworkGroup = HomeworkGroupQuery::create()
                    ->useHomeworkQuery()
                        ->filterById($objectId)
                    ->endUse()
                    ->findOne();

                return $homeworkGroup && $this->hasRight('HOMEWORK_ACCESS', $homeworkGroup->getGroupId());

            case 'LiaisonBook':
            case '\\BNS\\App\\CoreBundle\\Model\\LiaisonBook':
                $liaison = LiaisonBookQuery::create()
                    ->findOneById($objectId);

                return $liaison && $this->hasRight('LIAISONBOOK_ACCESS', $liaison->getGroupId());

            case 'MessagingMessage':
            case '\\BNS\\App\\MessagingBundle\\Model\\MessagingMessage':
                $message = MessagingMessageQuery::create()
                    ->findPk($objectId);

                return $message && $this->container->get('bns.message_manager')->canRead($message);

            case 'MiniSitePageNews':
            case '\\BNS\\App\\MiniSiteBundle\\Model\\MiniSitePageNews':
                $page = MiniSitePageQuery::create()
                    ->useMiniSitePageNewsQuery()
                        ->filterById($objectId)
                    ->endUse()
                    ->findOne();

                return $this->canReadMiniSitePage($page);

            case 'MiniSitePageText':
            case '\\BNS\\App\\MiniSiteBundle\\Model\\MiniSitePageText':
                $page = MiniSitePageQuery::create()
                    ->useMiniSitePageTextQuery()
                        ->filterByPageId($objectId)
                    ->endUse()
                    ->findOne();

                return $this->canReadMiniSitePage($page);

            case 'NoteBook':
            case '\\BNS\\App\\NoteBookBundle\\Model\\NoteBook':
                $notebook = NoteBookQuery::create()->findPk($objectId);

                return $notebook && $this->hasRight('NOTEBOOK_ACCESS', $notebook->getGroupId());

            case 'ForumMessage':
            case '\\BNS\\App\\CoreBundle\\Model\\ForumMessage':

                $group = GroupQuery::create()
                    ->useForumQuery()
                        ->useForumSubjectQuery()
                            ->useForumMessageQuery()
                                ->filterById($objectId)
                            ->endUse()
                        ->endUse()
                    ->endUse()
                    ->findOne();

                return $group && $this->hasRight('FORUM_ACCESS', $group->getId());

            case 'Profile':
            case '\\BNS\\App\\CoreBundle\\Model\\Profile':
            break;

        }

        return false;
    }

    protected function canReadMiniSitePage($page)
    {
        if (!$page) {
            return false;
        }
        if ($page->isActivated()) {
            if ($page->isPublic()) {
                return true;
            } else {
                return $this->hasRight('MINISITE_ACCESS', $page->getMiniSite()->getGroupId());
            }
        }

        return $this->hasRight('MINISITE_ACCESS_BACK', $page->getMiniSite()->getGroupId());
    }

    //Function to change context to see a blog article (back and front) if you have the right in a group you belong
    public function changeContextToSeeBlogArticle(Request $request, BlogArticle $article, $route, $param)
    {
        // build a sub request to handle context switch
        $target = $article->getBlogs()->getFirst()->getGroup();

        $this->changeContextTo($request, $target);

        // try again
        return new RedirectResponse($this->container->get('router')->generate($route, $param));
    }

    /**
     * change context in a sub request make sure to redirect before doing anything after that
     *
     * @param Request $request
     * @param Group $group
     * @throws \Exception
     * @throws \Throwable
     */
    public function changeContextTo(Request $request, Group $group)
    {
        $subRequest = new Request();
        $subRequest->attributes->set('_controller', 'BNSAppMainBundle:Context:switchContext');
        $subRequest->attributes->set('slug', $group->getSlug());
        $subRequest->setSession($request->getSession());
        $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}

<?php

namespace BNS\App\CoreBundle\User;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\PupilParentLink;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkPeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Utils\StringUtil;
use \BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use \BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use \BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;

use Sabre\VObject\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Util\SecureRandomInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Eymeric Taelman
 */
class BNSUserManager
{
    /**
     * @var $container ContainerInterface
	 */
	protected $container;
	protected $security_context;
    /** @var \BNS\App\CoreBundle\Api\BNSApi  */
	protected $api;
	protected $domain_id;

	/**
	 * @var User
	 */
	protected $user;
	protected $rights;
	protected $roleManager;
	protected $tmpDir;
	protected $bns_mailer;
    protected $manageableGroupIds;

    /** @var  SecureRandomInterface */
    protected $secureRandomGenerator;

    protected $adminAllowed;

    protected $certifierManager;

    /**
     * @var \BNS\App\PaasBundle\Manager\LicenceManager
     */
    protected $licenceManager;

    /**
     * @var boolean
     */
    protected $unlimitedAllowed;
    /*
     * @var boolean
     */
    protected $unlimitedResources = null;

    /**
     * @param ContainerInterface $container
     * @param BNSFileSystemManager $fileSystemManager
     * @param SecureRandomInterface $secureRandom
     * @param $domainId
     * @param $tmpDir
     */
    public function __construct(ContainerInterface $container, BNSFileSystemManager $fileSystemManager, SecureRandomInterface $secureRandom, $domainId, $tmpDir, $adminAllowed, $unlimitedAllowed = false)
    {
        $this->container = $container;
        $this->filesystemManager = $fileSystemManager;
        $this->secureRandomGenerator = $secureRandom;
        $this->domain_id = $domainId;
        $this->tmpDir = $tmpDir;
        $this->adminAllowed = $adminAllowed;
        // todo inject me
        $this->api = $container->get('bns.api');
        $this->roleManager = $container->get('bns.role_manager');
        $this->bns_mailer = $container->get('bns.mailer');
        $this->certifierManager = $container->get('bns.admin_certifier.manager');
        $this->licenceManager = $container->get('bns_app_paas.manager.licence_manager');

        $this->unlimitedAllowed = $unlimitedAllowed;
    }

	/*
	 * Fonctions utilisées par la classe elle même
	 */

	/**
	 * @param User $user
	 * @return BNSUserManager
	 */
	public function setUser(User $user)
	{
		if (null != $this->user && $this->user->getId() != $user->getId()) {
			$this->rights = null;
			$this->unlimitedResources = null;
			unset($this->groups);
            unset($this->groupsByType);
		}
		$this->user = $user;
		return $this;
	}

    /**
     * @param int $userId
     * @return BNSUserManager
     */
    public function setUserById($userId)
    {
        return $this->setUser($this->findUserById($userId));
    }

    /**
     * @return User|boolean
     */
    public function getUser()
    {
        if (isset($this->user)) {
            return $this->user;
        }

        return false;
    }



	/*
	 * Fonctions liées aux droits
	 */

	/**
	 * @param array $rights
	 */
	public function setRights($rights)
	{
		$this->rights = $rights;
	}

	// Renvoie un tableau des droits de l'utilisateurs, récupérés sur la centrale d'authentification
	public function getRights()
	{
        if ($this->container->has('debug.stopwatch')) {
            $stopwatch = $this->container->get('debug.stopwatch');
        } else {
            $stopwatch = new Stopwatch();
        }
        $stopwatch->start('buildRights');

        if ((!isset($this->rights) || null == $this->rights) && $this->getUser() != null) {
            $redisDatas = $this->api->getRedisConnection()->hget('user_' . $this->getUser()->getUsername(), 'rights');
            if (!$redisDatas) {
                $stopwatch->start('get_rights_from_auth');
				$rights = $this->getFullRightsAndGroups();
                $stopwatch->stop('get_rights_from_auth');
				$sortedRights = array();
				//manipulation du tableau de droits

                // preload groups / Licence
                $groupIds = [];
                foreach ($rights as $group) {
                    $groupIds[] = (int)$group['group']['id'];
                }
                $groupCaches = GroupQuery::create()
                    ->filterByArchived(false)
                    ->joinWith('GroupType')
                    ->findPks($groupIds)
                    ->getArrayCopy('Id')
                ;
                $this->licenceManager->warmCache($groupIds);

                foreach ($rights as $group) {
                    //On ne prend pas en compte les comptes archivés
                    if (!isset($groupCaches[(int)$group['group']['id']])) {
                        continue;
                    }
                    $currentGroupId = $group['group']['id'];
                    /** @var Group $groupObject */
                    $groupObject = $groupCaches[(int)$group['group']['id']];

                    // TODO remove unused data
                    $currentGroupInfos = array(
                        'id'				=> $currentGroupId,
                        'group_name'		=> $group['group']['label'],
                        'group_type'		=> $groupObject->getType(),
                        'group_type_id'		=> $group['group']['group_type_id'],
                        'domain_id'			=> $group['group']['domain_id'],
                        'roles'				=> array()

                    );

                    if (isset($group['permissions'])) {
                        $currentGroupInfos['permissions'] = $group['permissions'];
                    } else {
                        $currentGroupInfos['permissions'] = array();
                        foreach ($group['finals_permissions'] as $permissionInfo) {
                            $currentGroupInfos['permissions'][] = $permissionInfo['unique_name'];
                        }
                    }

                    $currentGroupInfos['assistant'] = false;
                    foreach ($group['roles'] as $role) {
                        $currentGroupInfos['roles'][] = $role['id'];
                        if ('ASSISTANT' === $role['type']) {
                            $currentGroupInfos['assistant'] = true;
                        }
                    }
                    //Nous n'ajoutons le tableau de droits que si il y a des permissions
                    if (count($currentGroupInfos['permissions']) > 0) {
                        $sortedRights[$currentGroupId] = $currentGroupInfos;
                    } else {
                        continue;
                    }

                    if (in_array($groupObject->getType(), ['SCHOOL', 'CLASSROOM', 'TEAM']) && !$this->licenceManager->getLicence($groupObject)) {
                        // no licence no right
                        unset($sortedRights[$currentGroupId]);
                        continue;
                    }

                    //Ouverture des droits dans les installations nécessitant une validation des classes par les écoles, NE CONCERNE QUE LE .NET !
                    if ($this->container->hasParameter('check_group_validated') && $this->container->getParameter('check_group_validated')) {
                        $gm = $this->container->get('bns.group_manager');
                        if (GroupPeer::VALIDATION_STATUS_REFUSED === $groupObject->getValidationStatus()
                            && 'CLASSROOM' === $groupObject->getType()
                            && $gm->setGroup($groupObject)->isOnPublicVersion()
                        ) {
                            //On coupe l'accès aux classes refusées
                            //Si le groupe est refusé nous n'attribuons plus les droits
                            unset($sortedRights[$currentGroupId]);
                        }
                    }

                    //Ouverture des droits dans les installation nécessitant des activations d'écoles / classes
                    if ($this->container->hasParameter('check_group_enabled') && $this->container->getParameter('check_group_enabled')) {
                        //Vérification si on a le droit
                        if (in_array($groupObject->getType(), array("CLASSROOM", "SCHOOL"))) {
                            $delete = false;
                            $gm = $this->container->get('bns.group_manager');
                            $gm->setGroup($groupObject);
                            switch ($groupObject->getType()) {
                                case "CLASSROOM":
                                    $parent = $gm->getParent();
                                    $delete = !$parent || !$parent->getEnabled();
                                    break;
                                case "SCHOOL":
                                    $delete = !$groupObject->getEnabled();
                                    break;
                            }
                            if ($delete == true) {
                                unset($sortedRights[$currentGroupId]);
                            }
                        }
                    }

                }

                $sortedRights = $this->filterAdminRights($sortedRights);

                $sortedRights = $this->filterByInstalledApplications($sortedRights);

                $autoJoins = array();
                $assistantGroupIds = array();
                foreach ($sortedRights as $group) {
                    // Detect if need assistant rights
                    if (in_array($group['group_type'], array('SCHOOL', 'CLASSROOM')) && $group['assistant']) {
                        $assistantGroupIds[] = $group['id'];
                    }

                    // Detect Auto join permissions
                    foreach ($group['permissions'] as $permission) {
                        if (0 === strpos($permission, 'AUTO_JOIN_')) {
                            $autoJoins[] = array(
                                'group_id' => $group['id'],
                                'permission' => $permission,
                            );
                        }
                    }
                }
                $this->rights = $sortedRights;
                if (count($assistantGroupIds) > 0) {
                    $this->addAssistantPermissions($assistantGroupIds);
                }
                // Add assistant dedicated rights
                $linkedPupils = UserQuery::create()
                    ->usePupilAssistantLinkRelatedByPupilIdQuery()
                        ->filterByAssistantId($this->getUser()->getId())
                    ->endUse()
                    ->find()
                ;

                if (count($linkedPupils) > 0) {
                    $this->addDedicatedAssistantPermissions($linkedPupils);
                }


                if (count($autoJoins) > 0) {
                    if ($this->autoJoins($autoJoins)) {
                        // Need reset
                        $this->rights = null;
                        $sortedRights = $this->getRights();
                        $this->rights = $sortedRights;
                    }
                }

                $this->saveRights($this->rights);
            } else {
                $this->rights = json_decode($redisDatas, true);
            }
        }

        $stopwatch->stop('buildRights');

		return $this->rights;
	}

    protected function filterAdminRights($sortedRights)
    {
        $result = [];
        foreach ($sortedRights as $groupId => $sortedRight) {
            $permissions = array();
            foreach ($sortedRight['permissions'] as $permission) {
                if (preg_match('/ADMIN_/i', $permission)) {
                    if ('ADMIN_PRETENDED' === $permission) {
                        // leave this permission wich only identify admin/support member but give no right
                        $permissions[] = $permission;
                    } elseif ($this->adminAllowed) {
                        if ($this->certifierManager->isCertified() || 'ADMIN_UPDATE_CREDENTIAL' === $permission) {
                            $permissions[] = $permission;
                        }
                    }
                } else {
                    $permissions[] = $permission;
                }
            }
            if (count($permissions) > 0) {
                $sortedRight['permissions'] = $permissions;
                $result[$groupId] = $sortedRight;
            }

        }

        return $result;
    }

    protected function filterByInstalledApplications($sortedRights)
    {
        $applicationManager = $this->container->get('bns_core.application_manager');
        if (!$applicationManager->isEnabled() || $applicationManager->isAutoInstall()) {
            return $sortedRights;
        }
        $result = array();

        foreach ($sortedRights as $groupId => $sortedRight) {
            $group = GroupQuery::create()
                ->joinWith('GroupType')
                ->findPk($groupId)
            ;
            // Only filter permissions for school or classroom
            if (in_array($group->getType(), array('SCHOOL', 'CLASSROOM'))) {
                $allowedPermissions = $applicationManager->getAllowedPermissions($group, $this->user ? $this->user->getLang() : null);
                $permissions = array();
                foreach ($sortedRight['permissions'] as $permission) {
                    if (in_array($permission, $allowedPermissions)) {
                        $permissions[] = $permission;
                    }
                }
                if (count($permissions) > 0) {
                    $sortedRight['permissions'] = $permissions;
                    $result[$groupId] = $sortedRight;
                }
            } else {
                $result[$groupId] = $sortedRight;
            }
        }

        return $result;
    }

    protected function addDedicatedAssistantPermissions($pupils)
    {
        // TODO : inject service
        $assistantRightManager = $this->container->get('bns_core.assistant_right_manager');

        $assistant = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->filterByType('ASSISTANT')
            ->findOne();

        $currentRights = $this->rights;
        $user = $this->getUser();

        foreach ($pupils as $pupil) {
            $rights = $this->setUser($pupil)->getRights();

            foreach ($rights as $groupId => $groupRights) {
                $permissions = $assistantRightManager->filterPermissions($groupRights['permissions']);

                if (0 === count($permissions)) {
                    continue;
                }
                if (isset($currentRights[$groupId])) {
                    $currentRights[$groupId]['permissions'] = array_merge($currentRights[$groupId]['permissions'], $permissions);
                } else {
                    $currentRights[$groupId] = $groupRights;
                    $currentRights[$groupId]['permissions'] = $permissions;
                    //role des parents pour ne pas pouvoir voir leur profil et etre consideré comme adulte
                    $currentRights[$groupId]['roles'] = [ $assistant->getId() ];
                }
            }
        }

        $this->setUser($user);
        $this->rights = $currentRights;

        return $this->rights;
    }

    protected function autoJoins($autoJoins)
    {
        $joined = false;
        $groupManager = $this->container->get('bns.group_manager');
        foreach ($autoJoins as $autoJoin) {

            if (preg_match('/^AUTO_JOIN_(?P<type>.*)_AS_(?<role>.*)$/', $autoJoin['permission'], $matches)) {
                $role = GroupTypeQuery::create()->filterByRole()->filterByType($matches['role'])->findOne();

                $type = $matches['type'];
                $group = $groupManager->findGroupById($autoJoin['group_id']);
                if (!$group || !$role || !$type || $group->isArchived()) {
                    $this->container->get('logger')->error(sprintf('UserManager Auto Join invalid group (%s) or role or type %s', $autoJoin['group_id'], $autoJoin['permission']));
                    continue;
                }

                $subGroups = $groupManager->getSubgroupsByGroupType($type, false);
                $subGroupIds = GroupQuery::create()
                    ->filterById(array_map(function($item){
                        return $item['id'];
                    }, $subGroups))
                    ->filterByArchived(false)
                    ->select('Id')
                    ->find()
                ;
                foreach ($subGroupIds as $subGroupId) {
                    if (!$this->hasRoleInGroup($subGroupId, $role->getType())) {
                        $this->roleManager->setGroupTypeRole($role)->assignRole($this->getUser(), $subGroupId);
                        $this->container->get('logger')->warning(sprintf('UserManager Auto Join user %s with role %s in group %s', $this->getUser()->getUsername(), $role, $subGroupId));
                        $joined = true;
                    }
                }
            }
        }

        return $joined;
    }

    protected function addAssistantPermissions($assistantGroupIds)
    {
        // TODO : inject service
        $assistantRightManager = $this->container->get('bns_core.assistant_right_manager');
        $groupManager = $this->container->get('bns.group_manager');

        $teacherRole = GroupTypeQuery::create()->filterByRole()->filterByType('TEACHER', \Criteria::EQUAL)->findOne();

        foreach ($this->rights as $key => $groupData) {
            if (in_array($groupData['id'], $assistantGroupIds)) {
                $group = GroupQuery::create()->findPk($groupData['id']);
                if ($group) {
                    $permissions = $groupManager->getPermissionsForRole($group, $teacherRole);
                    $permissions = $assistantRightManager->filterPermissions($permissions);

                    foreach ($permissions as $permission) {
                        if (!in_array($permission, $groupData['permissions'])) {
                            $groupData['permissions'][] = $permission;
                        }
                    }
                    $this->rights[$key] = $groupData;
                }
            }
        }
    }

    public function getRightsAndModulesNames()
    {
        $permissions = PermissionQuery::create()
            ->joinWith("Module")
            ->find()
            ->getArrayCopy("UniqueName");

        $rights = $this->getRights();

        foreach ($rights as $key => $right) {
            $groupTypeIds = $rights[$key]['roles'];
            $rights[$key]['roles_id'] = $rights[$key]['roles'];
            $rights[$key]['roles'] = [];
            foreach ($groupTypeIds as $groupTypeId) {
                $groupTypeName = GroupTypeQuery::create()
                    ->filterById($groupTypeId)
                    ->select('type')
                    ->findOne();

                $rights[$key]['roles'][] = $groupTypeName;
            }
            $rights[$key]['modules'] = [];
            $rights[$key]['other_permissions'] = [];
            foreach ($rights[$key]['permissions'] as $permissionName) {

                if (isset($permissions[$permissionName])) {
                    $rights[$key]['modules'][$permissions[$permissionName]->getModule()->getUniqueName()][] = $permissionName;
                } else {
                    $rights[$key]['other_permissions'][] = $permissionName;
                }

            }
        }

        return $rights;
    }

    public function getUserMerges() {
        $user = $this->getUser();

        try {
            $data = $this->api->send('user_merges',
                array(
                    'route' => array(
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    )
                ));

            if (is_array($data)) {
                return $data;
            }
        } catch (\Exception $e) { }

        return false;
    }

    public function getUserMergedIn() {
        $user = $this->getUser();

        try {
            $data = $this->api->send('user_merged_in',
                array(
                    'route' =>  array(
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    )
                ));

            if (is_array($data)) {
                return $data;
            }
        } catch (\Exception $e) { }

        return false;
    }

	/**
	 *
	 * @param type $sortedRights Droits à enregistrer
	 */
	public function saveRights($sortedRights)
	{
		$this->api->getRedisConnection()->hset('user_' . $this->getUser()->getUsername(),'rights',json_encode($sortedRights));
	}

    /**
     * Permet de réinitialiser les droits, utile lors d'un reloadRights() (BNSRightManager)
     */
    public function resetRights()
    {
        $this->rights = null;
        $this->api->getRedisConnection()->del('user_' . $this->getUser()->getUsername());
    }

	/*
	 * Renvoie tous les droits / groupes de l'utilisateur à partir de la centrale et donc de l'API
	 * @return array
	 */
	public function getFullRightsAndGroups()
	{
       return $this->api->send('user_rights_new', [
           'route' => [
               'id' => $this->getUser()->getId(),
               'username' => $this->getUser()->getUsername(),
           ]
       ]);
    }
	/*
	 * L'utilisateur a-t-il le droit passé en paramètre ?
	 * @param $group_id : Id du groupe sur lequel on demande le droit : par défaut le groupe en cours
	 * @param $permission_unique_name  : Unique name de la permission
	 * @return Boolean
	 */
	public function hasRight($permission_unique_name = null, $group_id)
	{
		$rights = $this->rights ? : $this->getRights();
        if ($permission_unique_name == null) {
            return isset($rights[$group_id]['permissions']);
        }elseif (isset($rights[$group_id]['permissions'])) {
			return in_array($permission_unique_name,$rights[$group_id]['permissions']);
		}
		return false;
	}

	/**
	 * @param string $permissionUniqueNamePattern
	 * @return boolean
	 */
	public function hasRightPatternSomeWhere($permissionUniqueNamePattern)
	{
		$rights = $this->getRights();
		foreach ($rights as $right) {
			foreach ($right['permissions'] as $permission) {
				if (preg_match('#' . $permissionUniqueNamePattern . '#', $permission)) {
					return true;
				}
			}
		}
		return false;
	}

	/*
	 * Renvoie un booléen selon si j'ai quelque part (n'importe quel groupe) le droit
	 * @params String $permission_unique_name la permission en question
	 */
	public function hasRightSomeWhere($permission_unique_name)
	{
		$rights = $this->getRights();
		foreach ($rights as $group_id => $group) {
			if ($this->hasRight($permission_unique_name, $group_id)) {
				return true;
			}
		}
		return false;
	}

    /**
     * Renvoie un booléèn si l'utilisateur est autorisé (cad fait parti d'une école autorisée MTP)
     *
     * @deprecated
     * @see hasEnabledSchool()
     */
    public function isAuthorised()
    {
        return $this->hasEnabledSchool();
    }

    public function hasEnabledSchool()
    {
        $schoolType = GroupTypeQuery::create()->findOneByType('SCHOOL');
        if ($this->container->hasParameter('check_group_enabled') && $this->container->getParameter('check_group_enabled')) {
            $allGroupIds = array_keys($this->getFullRightsAndGroups());
        } else {
            $allGroupIds = array_keys($this->getGroupsAndRolesUserBelongs());
        }

        return GroupQuery::create()
                ->filterByEnabled(true)
                ->filterByGroupTypeId($schoolType->getId())
                ->filterById($allGroupIds, \Criteria::IN)
                ->count() > 0;
    }

    /**
	 * Fonction CRUD sur l'utilisateur
	 */

	/**
	 * @param array   $values
	 * @param boolean $autoSendMail
	 * @param boolean $createAuth
	 *
	 * @return User
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createUser($values, $autoSendMail = false, $createAuth = true)
	{
		// Vérification que nous avons assez d'infos : prénom, nom, langue
		if (!isset($values['first_name']) || !isset($values['last_name']) || !isset($values['lang'])) {
			throw new \InvalidArgumentException('Not enough datas to create user !');
		}

		// Inject domain id from parameters
		$values['domain_id'] = $this->domain_id;

		// Proper name formatting
		$values['first_name'] = mb_convert_case($values['first_name'], MB_CASE_TITLE, 'UTF-8');
		$values['last_name'] = mb_convert_case($values['last_name'], MB_CASE_TITLE, 'UTF-8');

		if ($createAuth) {
			$response = $this->api->send('user_create', array('values' => $values));

			// Username et user_id sont gérés par la centrale
			$values['user_id']  = $response['id'];
			$values['username'] = $response['username'];

			// Finally
			$newUser = UserPeer::createUser($values);
			$newUser->setPassword($response['plain_password']);
		}
		else {
			// Finally
			$userInfo = $this->getUserFromCentral($values['username']);
			$values['user_id'] = $userInfo['id'];
			$newUser = UserPeer::createUser($values);
		}


		if ($autoSendMail && $createAuth) {
			$this->sendWelcomeEmail($newUser, $response['plain_password']);
		}

        if(BNSAccess::isConnectedUser())
        {
            $this->container->get('bns.right_manager')->trackAnalytics('REGISTERED_USER',$newUser);
        };

		return $newUser;
	}

    public function sendWelcomeEmail(User $user, $plainPassword = null)
    {
        $base = array(
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getlastName(),
            'login' => $user->getLogin(),
            'password' => $plainPassword,
        );

        if (!$user->getEmailValidated()) {
            if ($plainPassword) {
                $emailName = 'WELCOME_AND_CHECK_EMAIL';
            } else {
                $emailName = 'WELCOME_AND_CHECK_EMAIL_NOPASS';
            }
            $emailToken = $this->generateEmailConfirmationToken($user);
            $base['confirm_link'] = $this->container->get('router')->generate('main_emailConfirmation_emailCheck',array('token' => $emailToken),true);
        } else {
            $emailName = 'WELCOME';
        }

        $this->bns_mailer->send(
            $emailName,
            $base,
            $user->getEmail(),
            $user->getLang()
        );
    }

    public function sendLoginEmail(User $user)
    {
        $base = array(
            'first_name' => $user->getFirstName(),
            'login' => $user->getLogin()
        );
        $emailName = 'WELCOME_LOGIN';

        $this->bns_mailer->send(
            $emailName,
            $base,
            $user->getEmail(),
            $user->getLang()
        );
    }


    /**
     * Ajout en masse d'utilisateurs
     */
    public function createUsers($askedUsers, $autoSendMail = false, $createAuth = true)
    {
        $response = $this->api->send('users_create',array('values' => array('users' => $askedUsers)));
        $count = 0;
        $lastUserId = null;

        foreach($response as $user)
        {
            $askedUser = $askedUsers[$count];
            $user['gender'] = $askedUser['gender'];
            $user['user_id']  = $user['id'];
            if(isset($askedUser['aaf_id']))
            {
                $user['aaf_id'] = $askedUser['aaf_id'];
            }
            if(isset($askedUser['aaf_academy']))
            {
                $user['aaf_academy'] = $askedUser['aaf_academy'];
            }
            if(isset($askedUser['aaf_level']))
            {
                $user['aaf_level'] = $askedUser['aaf_level'];
            }
            if(isset($askedUser['aaf_cycle']))
            {
                $user['aaf_cycle'] = $askedUser['aaf_cycle'];
            }
            if(isset($askedUser['import_id']))
            {
                $user['import_id'] = $askedUser['import_id'];
            }
            if(isset($askedUser['high_role_id']))
            {
                $user['high_role_id'] = $askedUser['high_role_id'];
            }
            if (isset($askedUser['phone'])) {
                $user['phone'] = $askedUser['phone'];
            }
            if (isset($askedUser['ine'])) {
                $user['ine'] = $askedUser['ine'];
            }

            $newUser = UserPeer::createUser($user);
            $newUser->setPassword($user['plain_password']);
            if (($autoSendMail && $createAuth) || (isset($askedUser['autosend_email']) && $askedUser['autosend_email'])) {
                $this->sendWelcomeEmail($newUser, $response['plain_password']);
            }
            if(isset($askedUser['is_parent']) && $askedUser['is_parent'] == true)
            {
                //On linke alors avec le dernier utilisateur importé
                $this->addParent($lastUserId, $newUser);
            }

            if(isset($askedUser['parent_ids']) && is_array($askedUser['parent_ids']))
            {
                //On linke alors avec les ids donnés
                foreach($askedUser['parent_ids'] as $parentId)
                {

                    $this->addParent($newUser, $parentId);
                    //On affecte dans les classes de l'élève en question
                    $this->setUserById($newUser->getId());
                    $this->roleManager->setGroupTypeRoleFromType('PARENT');
                    $this->resetRights();
                    foreach($this->getGroupsUserBelong() as $group)
                    {
                        if($group->getGroupType()->getType() == 'CLASSROOM')
                        {
                            $this->roleManager->assignRole(UserQuery::create()->findOneById($parentId), $group->getId());
                        }
                    }
                }
            }



            $count++;
            $lastUserId = $user['user_id'];
            $newUser = null;
        }
        $response = null;
        $lastUserId = null;
        $newUser = null;
        unset($response,$lastUserId,$response);
    }

    public function createAffectations($affectations)
    {
        $this->api->send('users_create_affectations',
            array(
                'route' =>  array(),
                'values' => array('affectations' => $affectations)
            )
        );

        // get username of the affected users, and clear their cache
        $userIds = array_map(function ($affectation) {
            return $affectation['userId'] ?? 0;
        }, $affectations);
        $usernames = UserQuery::create()
            ->filterById($userIds)
            ->select(['Login'])
            ->find()
            ->toArray();
        $this->api->getRedisConnection()->pipeline(function ($pipe) use ($usernames) {
            foreach ($usernames as $username) {
                $pipe->del('user_' . $username);
            }
        });
    }

	/**
	 * Permet de mettre à jour la base de données de la centrale avec l'utilisateur $user donné en paramètre
	 * /!\ Attention, cette méthode ne met pas à jour en local mais se base sur les valeurs actuelles des attributs de l'objet $user
	 * et les envoient à la centrale
	 *
	 * @param User $user l'utilisateur dont on veut mettre à jour côté central
     * @param boolean $forceNewLogin generate a new login for the user
     * @param array $otherValues Additional values to set on the user
	 * @see http://redmine.pixel-cookers.com/projects/bns-3-dev/wiki/Centrale-user-api#Mise-à-jour-dun-utilisateur
	 */
	public function updateUser(User $user, $oldLogin = null, $forceNewLogin = false, $otherValues = [])
	{
		if (null == $user || null == $user->getId()) {
			throw new InvalidArgumentException('You provide invalide user (potential issue origin: no id, equals to null');
		}
        $user->setLogin(str_replace(' ','',$user->getLogin()));
		$user->save();

        //Media Folder root's update
        $folder = $user->getMediaFolder();
        if ($folder) {
            $folder->setLabel($user->getFirstName() . ' ' . $user->getLastName());
            $folder->save();
        }

		$response = $this->api->send('user_update', array(
			'route' 	=> array(
				'username' => $oldLogin != null ? $oldLogin : $user->getLogin()
			),
			'values'	=> array_merge(array(
				'id'			=> $user->getId(),
				'username'		=> $forceNewLogin ? 'temporary' : $user->getLogin(),
				'email'			=> $user->getEmail(),
				'first_name'	=> $user->getFirstName(),
				'last_name'		=> $user->getLastName(),
				'domain_id'		=> $this->domain_id,
				'lang'			=> $user->getLang(),
                'gender'		=> $user->getGender()
			), $otherValues),
		));

        if ($forceNewLogin && $response && isset($response['username'])) {
            $user->setLogin($response['username']);
            $user->save();
        }
	}

    /**
     * Use to update user login and/or certify status
     * @param User $user
     * @param $oldLogin
     * @param null $certify
     * @throws \Exception
     */
    public function updateUserLogin(User $user, $oldLogin, $certify = null)
    {
        if (!preg_match('/^[a-zA-Z0-9]*$/', $user->getLogin())) {
            throw new \Exception('Invlid user login');
        }

        $data = [
            'username' => $user->getLogin(),
            'domain_id' => $this->domain_id,
        ];
        if (null !== $certify) {
            $data['certify'] = (boolean) $certify;
        }

        $this->api->send('user_update_login', [
            'route' => [
                'id' => $user->getId()
            ],
            'values' => $data,
        ]);

        $this->api->resetUser($oldLogin);
        $this->api->resetUser($user->getLogin());
    }

    /**
     * Updates the given users, merged with optional additional data. Returns an array with these keys:
     *  - `success`: array of users that have been updated successfully (indexed by user id)
     *  - `error`: array of error messages (indexed by user id)
     *
     * @param User[] $users The array of users to update
     * @param array $otherValues Array of optional additional data for users, indexed by user id
     * @param bool $strict Whether to perform email checks when updating
     * @return array
     */
    public function updateUsers($users, $otherValues = [], $strict = true)
    {
        $data = [];
        foreach ($users as $user) {
            if (!$user instanceof User) {
                throw new \InvalidArgumentException(sprintf('Expected an instance of User, got %s', is_object($user) ? get_class($user) : gettype($user)));
            }
            $userData = $user->toArray(\BasePeer::TYPE_FIELDNAME);
            // keep only data that the auth can handle
            $userData = array_intersect_key($userData, array_flip([
                'id',
                'login',
                'first_name',
                'last_name',
                'gender',
                'email',
                'aaf_id',
                'aaf_academy',
                'aaf_level',
                'aaf_cycle',
            ]));
            if (isset($otherValues[$user->getId()])) {
                $userData = array_merge($userData, $otherValues[$user->getId()]);
            }
            $data[] = $userData;
        }

        $responseUsers = $this->api->send('users_update', [
            'values' => [
                'strict' => $strict,
                'form' => [
                    'users' => $data,
                ],
            ],
        ]);

        $result = [
            'success' => [],
            'error' => [],
        ];

        foreach ($users as $user) {
            if (!isset($responseUsers[$user->getId()])) {
                continue; // user somehow does not exist
            }

            $responseUser = $responseUsers[$user->getId()];
            if (isset($responseUser['errors'])) {
                $result['error'][$user->getId()] = $responseUser;
            } else {
                $user->save();
                $result['success'][$user->getId()] = $user;
            }
        }

        return $result;
    }

	/*
	* Ajoute un rôle à un utilisateur dans un groupe
	* Pour les rôles de "base" on renseigne le unique_name (plus simple pour 95 % des cas)
	* Pour les rôles spécifiques, on renseigne l'Id du rôle
	*/

	public function addRole($role_unique_name = null, $role_id = null, $group_id)
	{
		if ($role_unique_name != null && $role_id != null) {
			throw new Exception("Role canot be defined, please provide role_unique_name OR role_id");
		}

		if ($role_unique_name != null) {
			$role = $this->roleManager->getRole($role_unique_name);
			$role_id = $role['id'];
		}
		//TODO : Call API pour l'ajout

	}

	public function getRolesByGroup($groupFirst = false)
	{
		$groups = $this->getFullRightsAndGroups();
		$return = array();
		foreach ( $groups as $group ) {
            if($groupFirst)
            {
                foreach ( $group['roles'] as $role ) {
                    $return[$group['group']['id']][] = $role['type'];
                }
            }else{
                foreach ( $group['roles'] as $role ) {
                    $return[$role['type']][] = $group['group']['id'];
                }
            }
		}
		return $return;
	}

    /**
     * @param string $role : le TYPE du rôle donnée
     * @return Group Collection
     */
    public function getGroupsWhereRole($role)
    {
        $rolesByGroup = $this->getSimpleGroupsAndRolesUserBelongs();
        $found = array();
        foreach($rolesByGroup as $key => $value)
        {
            if(array_search($role,$value) !== false)
            {
                $found[] = $key;
            }
        }
        return GroupQuery::create()->findById($found);
    }

    public function hasRoleSomeWhere($role)
    {
        return $this->getGroupsWhereRole($role) != null;
    }

    public function hasRoleInGroup($groupId, $roleUniqueName)
    {
        $roles = $this->getSimpleGroupsAndRolesUserBelongs();
        return isset($roles[$groupId]) && array_search($roleUniqueName, $roles[$groupId]) !== false;
    }

    public function usernameFactory($firstName, $lastName)
    {
        // Pattern : [fist_name][l]ast_name, example for "Sylvain Lorinet" : "sylvainl"
        $base = strtolower(self::filterName($firstName) . substr(self::filterName($lastName), 0, 1));
        // Pattern : [fist_name][l] + random number from 0000 to 9999. Example for "sylvainl" : "sylvainl6478"
        $username = $base . self::getNumbersPattern();

        // Vérification de l'unicité.
        $query = UserQuery::create()
            ->filterByLogin($username, \Criteria::EQUAL)
        ;

        while ($query->count() > 0 && $this->getUserFromCentralSafe($username, false)) {
            $username = $base . self::getNumbersPattern();
            $query = UserQuery::create()
                ->filterByUsername($username, \Criteria::EQUAL)
            ;
        }

        return $username;
    }



	/**
	 * @see http://redmine.pixel-cookers.com/projects/bns-3-dev/wiki/Centrale-user-api#Mise-à-jour-du-mot-de-passe-dun-utilisateur
	 *
	 * @param string $password
	 *
	 * @return
	 */
	public function setPassword($password)
	{
		return $this->api->send('user_update_password', array(
			'route'		=> array(
				'username' => $this->getUser()->getUsername()
			),
			'values'	=> array(
				'password'	=> $password
			)
		));
	}

	/**
	 * @param $userId
	 * @return User
	 */
	public function findUserById($userId)
	{
		$user = UserQuery::create()
			->add(UserPeer::ID, $userId)
		->findOne();

		if (null == $user) {
			throw new HttpException(500, 'No user exists for id given: '.$userId);
		}
		return $user;
	}

	/**
	 * @param string $login
	 *
	 * @return User
	 *
	 * @throws HttpException
	 */
	public function findUserByLogin($login, $tolerateNullValue = false)
	{
		$user = UserQuery::create()
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add(UserPeer::LOGIN, $login)
		->findOne();

		if (null == $user) {
			$response = $this->api->send('user_read', array(
				'check' => true,
				'route' => array(
					'username' => $login
				)
			));
			if (404 != $response && 0 < count($response)) {
				$user = new User();
				$user->setId($response['id']);
				$user->setFirstName($response['first_name']);
				$user->setLastName($response['last_name']);
				$user->setLogin($response['username']);
			}
		}
		if (null === $user && false === $tolerateNullValue) {
			throw new HttpException(500, 'No user exists for login given: '.$login);
		}

		return $user;
	}

    public function getLoginExists($login)
    {
        $response = $this->api->send('user_exists', array(
                'route' => array(
                        'username' => $login
                ), true //On force le call
        ));
        if ($response && isset($response['exists']) && 1 == $response['exists']) {
            return true;
        }

        return false;
    }

	/**
	 * @param string $slug
	 *
	 * @return User
	 *
	 * @throws HttpException
	 */
	public function findUserBySlug($slug)
	{
		$user = UserQuery::create()
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add(UserPeer::SLUG, $slug)
		->findOne();

		if (null == $user) {
			throw new HttpException(500, 'No user exists for user\'s slug given: '.$slug);
		}

		return $user;
	}

	/**
	 * @param array $userIds
	 *
	 * @return array<User>
	 *
	 * @throws HttpException
	 */
	public function retrieveUsersById(array $userIds)
	{
		if (count($userIds) == 0) {
			return array();
		}

		$users = UserQuery::create()
			->add(UserPeer::ID, $userIds, \Criteria::IN)
		->find();

		if (0 == count($users)) {
			throw new HttpException(500, 'No user exists for ids given');
		}

		return $users;
	}

	/**
	 *
	 * @param array $params Paramètres de la recherche parmi
	 *  - first_name
	 *  - last_name
	 *  - username
	 *  - id
	 *  - email
	 *  - groups_ids
	 * @param boolean $returnObject Renvoyer une collection d'objets Propel
	 */
	public function searchUserInAuth(array $params, $returnObject)
	{
		$response = $this->api->send('user_search', array(
			'values' => $params
		));

		if ( false == $returnObject ) {
			return $response;
		} else {
			$usersIds = array();
			foreach( $response as $user ) {
				$usersIds[] = $user['id'];
			}
			return UserQuery::create()->findById($usersIds);
		}
	}



	/*
	 * Renvoie les id des groupes où j'ai la permission. Si aucune permission donnée, renvoie les id de tous les groupes
	 * où j'ai un droit quelconque.
	 *
	 * @param String $permission_unique_name la permission en question
	 * @return array
	 */
	public function getGroupIdsWherePermission($permission_unique_name = null)
	{
		$rights = $this->getRights();
		$allowed_group_ids = array();
		foreach ($rights as $group_id => $group) {
			if ($this->hasRight($permission_unique_name,$group_id)) {
				$allowed_group_ids[] = $group_id;
			}
		}

		return $allowed_group_ids;
	}

	/**
	 * Renvoie les groupes où j'ai la permission. Si aucune permission donnée, renvoie tous les groupes où j'ai un droit
	 * quelconque.
	 *
	 * @param string $permission_unique_name la permission en question
	 * @return array|Group[]
	 */
	public function getGroupsWherePermission($permission_unique_name = null)
	{
		return GroupQuery::create()
			->joinWith('GroupType')
			->add(GroupPeer::ID, $this->getGroupIdsWherePermission($permission_unique_name), \Criteria::IN)
		->find();
	}

	/**
	 * Renvoie les groupes où j'ai les permissions
	 * @param array $permission_unique_names tableau des permissions
	 * @return array|Group[]
	 */
	public function getGroupsWherePermissions($permission_unique_names)
	{
		$groups = array();
		foreach($permission_unique_names as $permission_unique_name){
			foreach($this->getGroupsWherePermission($permission_unique_name) as $group){
				$groups[$group->getId()] = $group;
			}
		}
		return $groups;
	}

	/*
	 * Renvoie les droits du groupe donné en paramètres
	 */
	public function getGroupRightsById($group_id)
	{
		$rights = $this->getRights();
		return $rights[$group_id];
	}

	/**
	 * Renvoie les groupes auxquels l'utilisateur appartient
	 *
	 * @return \PropelObjectCollection|Group[]|array<Group>
	 */
	public function getGroupsUserBelong($groupTypeUniqueName = null)
	{
        if($groupTypeUniqueName == null)
        {

                $this->groups = GroupQuery::create()
                    ->joinWith('GroupType')
                    ->findPks($this->getGroupsIdsUserBelong());

            return $this->groups;
        }else{
            if (!isset($this->groupsByType[$groupTypeUniqueName])) {
                $this->groupsByType[$groupTypeUniqueName] = GroupQuery::create()
                    ->useGroupTypeQuery()
                        ->filterByType($groupTypeUniqueName)
                    ->endUse()
                    ->findPks($this->getGroupsIdsUserBelong());
            }
            return $this->groupsByType[$groupTypeUniqueName];
        }
	}

	/**
	 * Return only CLASSROOM groups where user is belong
	 *
	 * @return Group|Group[]
	 */
	public function getClassroomUserBelong($returnFirst = false)
	{
        $groups = $this->getGroupsUserBelong();
		$classRooms = array();

		foreach ($groups as $group) {
			if ($group->getGroupType()->getType() == 'CLASSROOM') {
                if($returnFirst == true)
                {
                    return $group;
                }else{
                    $classRooms[] = $group;
                }
			}
		}
        if($returnFirst == true)
        {
            return null;
        }
		return $classRooms;
	}

    /*
     * Retourne les rôles d'un utilisateur à partir de son login et mdp
     * @params User $$currentUser
     * @params String $login
     * @params String $password
     *
     * @return array<type>
     */
    public function canMergeUser($currentUser, $login, $password)
    {
        if(null == $login || null == $password) {
            return null;
        }

        $user = UserQuery::create()
            ->filterByLogin($login, \Criteria::EQUAL)
            ->findOne();

        if(null == $user || $user->getId() == $currentUser->getId()) {
            return null;
        }

        //Vérification si l'utilisateur est adulte
        $this->setUser($user);

        if($this->isChild()) {
            return null;
        }

        //Récupération des infos de l'utilisateur de la centrale
        $response = $this->api->send('user_authentication', array(
			'values'	=> array(
				'username' => $login,
                'password' => Crypt::encrypt($password)
			)
		));

        if(! isset($response['authentication']) || ! $response['authentication']) {
            return null;
        }

        $groupsAndRolesAndRights = $this->getFullRightsAndGroups();
        $groupsAndRoles = array();
        $index = 0;

        foreach($groupsAndRolesAndRights as $group) {
            $groupsAndRoles[$index]['group'] = $group['group'];
            $groupsAndRoles[$index]['roles'] = $group['roles'];
            $index++;
        }

        //Retourne rôles dans chaque groupe auquel l'utilisateur appartient
        return $groupsAndRoles;
    }

    /*
     * Effectue la fusion de $usernameTarget sur $usernameAsker
     * @params String $usernameAsker
     * @params String $usernameTarget
     * @params array<type> $targetUserRoles
     *
     * @return Boolean
     */
    public function mergeUsers($usernameAsker, $usernameTarget)
    {
        if(null == $usernameAsker || null == $usernameTarget) {
            return false;
        }

        $userAsker = UserQuery::create()
            ->filterByLogin($usernameAsker, \Criteria::EQUAL)
            ->findOne();
        $userTarget = UserQuery::create()
            ->filterByLogin($usernameTarget, \Criteria::EQUAL)
            ->findOne();

        if(null == $userAsker || null == $userTarget) {
            return false;
        }

        // Cas fusion parent -> enseignant
        // Mise à jour du high_role_id pour avoir le bon avatar par défaut
        // Mise à jour de l'adresse mail de l'utilisateur source si null avec celle du cible

        if(null == $userAsker->getEmail()) {
            $userAsker->setEmail($userTarget->getEmail());
        }

        //Fusion côté centrale, récupération des droits de l'utilisateur cible
        $responses = $this->api->send('user_merge', array(
            'values'    => array(
                'username_asker' => $usernameAsker,
                'username_target' => $usernameTarget
            )
        ));

        $this->api->resetUser($userAsker->getLogin());

        //Récupération des fichiers de la médiathèque de l'utilisateur cible
        //Ressources de l'utilisateur cible
        /** @var Media[] $mediasTargetUser */
        $mediasTargetUser = MediaQuery::create()
            ->filterByUserId($userTarget->getId())
            ->filterByMediaFolderType('USER')
            ->find()
        ;

        if (count($mediasTargetUser)) {

            $con = \Propel::getConnection(MediaPeer::DATABASE_NAME);
            $con->beginTransaction();

            try {
                //Création d'un dossier avec le nom complet de l'utilisateur cible s'il dispose de ressources
                $mergeFolder = new MediaFolderUser();
                $mergeFolder->setLabel($userTarget->getFullName());
                $mergeFolder->insertAsLastChildOf($userAsker->getMediaFolderRoot());
                $mergeFolder->save($con);

                foreach ($mediasTargetUser as $media) {
                    $media->setUserId($userAsker->getId());
                    $media->setMediaFolderId($mergeFolder->getId());
                    $media->save($con);

                    //Copy physique des fichers dans le répértoire de l'utilisateur source
                    //A optimiser, trouver une solution pour déplacer les fichier
                    $fs = $this->filesystemManager->getFileSystem();
                    $userTargetFilePath = '/' . $media->getCreatedAt('Y_m_d') . '/'
                        . $userTarget->getId() . '/'
                        . $media->getId() . '/'
                        . $media->getFilename();

                    //Vérification si le fichier existe phisique pour éviter les HTTP 500
                    if ($fs->has($userTargetFilePath)) {
                        $this->filesystemManager->getAdapter()->rename($userTargetFilePath, '/' . $media->getFilePath());
                        // TODO: clean cache, suppr miniatures
                    }
                }

                $con->commit();
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }

        //Récupération des fils du rattachés à l'utilisateur cible
        $userTargetChildrens = $this->getUserChildren($userTarget);

        foreach($userTargetChildrens as $children) {
            $this->addParent($children, $userAsker);
        }

        //Récupération de la messagerie
        $messagingMessages = MessagingMessageQuery::create()
            ->findByAuthorId($userTarget->getId());

        foreach($messagingMessages as $message) {
            $message->setAuthorId($userAsker->getId());
            $message->save();
        }

        $messagingConversations = MessagingConversationQuery::create()
            ->findByUserId($userTarget->getId());

        foreach($messagingConversations as $conversation) {
            $conversation->setUserId($userAsker->getId());
            $conversation->save();
        }

        $messagingWithConversations = MessagingConversationQuery::create()
            ->findByUserWithId($userTarget->getId());
        foreach($messagingWithConversations as $conversationWith) {
            $conversationWith->setUserWithId($userAsker->getId());
            $conversationWith->save();
        }

        //Suppression de l'utilisateur cible
        $this->changeStatus($userTarget, false);

        return true;
    }

	/**
	 * Renvoie les ids des groupes auxquels l'utilisateur appartient
	 *
	 * @return array<Integer>
	 */
	public function getGroupsIdsUserBelong()
	{
		$rights = $this->getRights();
		$groupIds = array();

		foreach ($rights as $groupId => $rights) {
			$groupIds[] = $groupId;
		}

		return $groupIds;
	}

    public function getSimpleGroupsAndRolesUserBelongs($returnGroups = false, $groupTypeIds = false)
    {
        $response = $this->api->send('user_roles', array(
            'route' => array(
                'username' => $this->getUser()->getLogin()
            )
        ));
        if($returnGroups)
        {
            $groups = array();
            if(is_array($response))
            {
                foreach($response as $key => $value)
                {
                    $groups[] = $key;
                }
            }
            $query = GroupQuery::create()->filterByArchived(false);
            if($groupTypeIds)
            {
                $query->filterByGroupTypeId($groupTypeIds);
            }
            return $query->findById($groups);
        }else{
            return $response;
        }
    }

    /**
     * Renvoie les tous les roles qu'a un utilisateur, quelques soient ses droits, sous la forme
     * array [groupId] => array ('group' => objet Group, 'roles' => array(objects GroupTypes))
     * @return array
     */
    public function getGroupsAndRolesUserBelongs()
    {
        $response = $this->api->send('user_roles', array(
            'route' => array(
                    'username' => $this->getUser()->getLogin()
            ), false
        ));
        if (!$response) {
            return [];
        }
        //Renvoie un tableau du type : [GroupId] => array(ROLE_1,ROLE_2)
        //on balaie tout le tableau pour récupérer les groupTypes Potentiels
        $groupIds = array();
        $roleTypeSorted = array();
        $return  = array();
        $userRoleTypes = array();
        foreach($response as $groupId => $roleTypes)
        {
            $groupIds[] = $groupId;
            foreach($roleTypes as $roleType)
            {
                $userRoleTypes[] = $roleType;
            }
        }
        $roleTypeObjects = GroupTypeQuery::create()->filterByType($userRoleTypes)->find();
        foreach($roleTypeObjects as $roleTypeObject)
        {
            $roleTypeSorted[$roleTypeObject->getType()] = $roleTypeObject;
        }
        $groups = GroupQuery::create()->findById($groupIds);
        foreach($groups as $group)
        {
            $return[$group->getId()]['group'] = $group;
            foreach($response[$group->getId()] as $finalRoleType)
            {
                $return[$group->getId()]['roles'][] = $roleTypeSorted[$finalRoleType];
            }
        }
        return $return;
    }

    public function userAlreadeyBelongToGroup(User $user, Group $group)
    {
        $this->setUser($user);
        $groupsUserBelong = $this->getGroupsUserBelong();
        foreach ($groupsUserBelong as $groupUserBelong)
        {
            if($groupUserBelong->getId() == $group->getId()) return true;
        }
        return false;
    }

    public function userIdAlreadeyBelongToGroupId($userId, $groupId)
    {
        $user = $this->findUserById($userId);
        $this->setUser($user);
        $groupsUserBelong = $this->getGroupsUserBelong();
        foreach ($groupsUserBelong as $groupUserBelong)
        {
            if($groupUserBelong->getId() == $groupId) return true;
        }
        return false;
    }

    /**
     * Retourne les droits de gestion dans le groupe passé en paramètre
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @return array
     */
    public function getManageableRightsInGroup(Group $group, $right = "VIEW")
    {
        $rights = $this->getRights();
        $mgt = array();
        foreach ($rights[$group->getId()]['permissions'] as $groupRight) {
            if (strpos($groupRight, "_" . $right)) {
                $mgt[] = $groupRight;
            }
        }
        return $mgt;
    }

    /**
     * Retourne les types de groupes visionnables dans le contexte passé en paramètre
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @return type
     */
    public function getManageableGroupTypesInGroup(Group $group, $isRole = null, $right = "VIEW")
    {
        $groupTypes = array();
        foreach ($this->getManageableRightsInGroup($group, $right) as $groupRight) {
            $type = GroupTypeQuery::create()->findOneByType(str_replace('_' . $right,'',$groupRight));
            if($type){
                if ($isRole !== null) {
                    if ($type->getSimulateRole() == $isRole) $groupTypes[$type->getType()] = $type;
                }else {
                    $groupTypes[$type->getType()] = $type;
                }
            }
        }
        return $groupTypes;
    }

    /**
     * Renvoie les Ids des groupes visibles
     * @return type
     */
    public function getManageableGroupIdsInGroup(Group $group, $right = "VIEW"){
      //  $redis = $this->container->get('snc_redis.default');
      //  $redisKey = 'ManageableGroupIds:' . $right . ':' . $group->getId() . ':' . $this->getUser()->getLogin();
      //  $cache = $redis->get($redisKey);
      // if(!$cache){
        if(!isset($this->manageableGroupIds[$group->getId()][$right]))
        {
            $res = array();
            $manageableGroupTypes = $this->getManageableGroupTypesInGroup($group,false,$right);
            if(is_array($manageableGroupTypes))
            {
                $filter = array();
                foreach($manageableGroupTypes as $groupType)
                {
                    $filter[] =  $groupType->getType();
                }
                $gm = $this->container->get('bns.group_manager');
                $allSubGroups = $gm->getAllSubgroups($group->getId(),count($filter) > 0 ? $filter : null );
                foreach($allSubGroups as $subGroup)
                {
                    $res[] = $subGroup->getId();
                }
                if(in_array($group->getGroupType()->getType(),$filter))
                {
                    $res[] = $group->getId();
                }
            }

            $this->manageableGroupIds[$group->getId()][$right] = $res;
        }
        return $this->manageableGroupIds[$group->getId()][$right];
        //}
		//return unserialize($cache);
	}

    /**
     * Méthode appelée récursivement pour calculer les groupes visibles
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @param array $gtvIds
     * @param array $results
     * @return array
     */
    protected function addManageableSubgroups(Group $group,$gtmIds,$results, $right = 'VIEW'){
        $gm = $this->container->get('bns.group_manager')->setGroup($group);
        foreach($gm->getSubgroups() as $subGroup){
            if(in_array($subGroup->getGroupTypeId(),$gtmIds)){
                $results[] = $subGroup->getId();
            }
            $results = $this->addManageableSubgroups($subGroup, $gtmIds, $results, $right);
        }
        return $results;
    }

    /**
     * L'utilisateur demander peut il agir dans le contexte $group l'utilisateur demandé
     * @param \BNS\App\CoreBundle\Model\User $asker
     * @param \BNS\App\CoreBundle\Model\User $asked
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @return boolean
     */
    public function canManageUserInGroup(User $asker, User $asked, Group $group, $right = 'VIEW')
    {
        $this->setUser($asked);
        //$roleRights = $this->getRolesByGroup();
        $affectations = $this->getSimpleGroupsAndRolesUserBelongs();
        //var_dump($roleRights,$this->getSimpleGroupsAndRolesUserBelongs());
        //die();
        $this->setUser($asker);
        /*
         * Attention : VIEW par défaut, on cherche les groupes dans lesquels si j'ai les droits d'actions sur les utilisateurs j'ai accès aux actions
         * Pour connaître les groupes dans lesquels j'ai le droit d'action, choix arbitraire du VIEW
         */
        $availableGroupsIds = $this->getManageableGroupIdsInGroup($group,'VIEW');

        $availableGroupTypes = $this->getManageableGroupTypesInGroup($group, true, $right);

        $availableGroupsTypeTypes = array();

        foreach($availableGroupTypes as $groupType)
        {
            $availableGroupsTypeTypes[] = $groupType->getType();
        }

        foreach($affectations as $groupId => $roles)
        {
            if(in_array($groupId, $availableGroupsIds))
            {
                foreach($roles as $role)
                {
                    if(in_array($role,$availableGroupsTypeTypes))
                    {
                        return true;
                    }
                }
            }
        }

        return false;

        /*foreach($this->getManageableGroupTypesInGroup($group, true, $right) as $groupType)
        {
            if(isset( $roleRights[$groupType->getType()] ))
            {
                foreach( $roleRights[$groupType->getType()] as $userGroup)
                {
                    if( in_array ( $userGroup , $availableGroupsIds ) )
                    {
                        return true;
                    }
                }
            }
        }
        return false;*/
    }

    /**
     * L'utilisateur $user peut il agir sur le group $group ?
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @return boolean
     */
    public function canManageGroupInGroup(Group $asked, Group $group, $right)
    {
        return in_array($asked->getId(),$this->getManageableGroupIdsInGroup($group, $right));
    }

	//////////////    FONCTIONS LIEES AUX UTILISATEURS PARENT     \\\\\\\\\\\\\\\\\
	public function getUserParent(User $user = null)
	{
        if (null == $user) {
            if (null == $this->user) {
                throw new \RuntimeException('You must provide a user before using this method !');
            }
            $user = $this->user;
        }

		$pupilParentLinks = PupilParentLinkQuery::create('p')
			->innerJoinUserRelatedByUserParentId('parent_user')->with('parent_user')
			->innerJoinUserRelatedByUserPupilId('pupil_user')->with('pupil_user')
			->where('p.UserPupilId = ?', $user->getId())
		->find();

		$parents = array();
		foreach ($pupilParentLinks as $parent) {
			$parents[] = $parent->getUserRelatedByUserParentId();
		}

		return $parents;
	}

    public function deleteParentLinks()
    {
        $rom = $this->roleManager;
        $rom->setGroupTypeRoleFromType('PARENT');
        $roleParent = GroupTypeQuery::create()->findOneByType('PARENT');
        //Pour chaque parent : Récupération des groupes dans  puis désafectation puis suppression du lien
        $groupsWherePupil = $this->getGroupsWhereRole('PUPIL');
        $groupsToUnassign = array();
        if($groupsWherePupil)
        {
            foreach($groupsWherePupil as $group)
            {
                if($group->getGroupType()->getType() == 'CLASSROOM')
                {
                    $groupsToUnassign[] = $group;
                }
            }
        }

        foreach(PupilParentLinkQuery::create()->findByUserPupilId($this->getUser()->getId()) as $link)
        {
            foreach($groupsToUnassign as $groupToUnassign)
            {
                $rom->unassignRole($link->getUserParentId(), $groupToUnassign->getId());
            }
            $this->removeAuthParent($this->getUser()->getId(), $link->getUserParentId());
            $link->delete();
        }
    }

    public function addParentLink($parent)
    {
        //On créé le lien puis l'affectation dans les classes
        $this->addParent($this->getUser()->getId(), $parent->getId());
        $rom = $this->roleManager;
        $rom->setGroupTypeRoleFromType('PARENT');
        $groupsWherePupil = $this->getGroupsWhereRole('PUPIL');

        $groupsToAssign = array();
        if($groupsWherePupil)
        {
            foreach($groupsWherePupil as $group)
            {
                if($group->getGroupType()->getType() == 'CLASSROOM')
                {
                    $rom->assignRole($parent, $group->getId());
                }
            }
        }
    }

    public function getNumberOfUserParent(User $user)
    {
        return sizeof($this->getUserParent($user));
    }

    public function hasChild($user = null)
    {
        return count($this->getUserChildren($user)) > 0;
    }

	/**
     * @deprecated use native user method @see User::getChildren() or @see User::getActiveChildren()
	 * @param User $user
	 *
	 * @return array|User[]
	 */
	public function getUserChildren(User $user = null)
	{
		if (null == $user) {
			if (null == $this->user) {
				throw new \RuntimeException('You must provide a user before using this method !');
			}
			$user = $this->user;
		}

                $pupilChildrenLinks = PupilParentLinkQuery::create('p')
                        ->innerJoinUserRelatedByUserPupilId('pupil_user')->with('pupil_user')
			->innerJoinUserRelatedByUserParentId('parent_user')->with('parent_user')
			->where('p.UserParentId = ?', $user->getId())
		->find();

                $children = array();
		foreach ($pupilChildrenLinks as $child) {
			$children[] = $child->getUserRelatedByUserPupilId();
		}

		return $children;
	}

        /**
	 * @param \BNS\App\CoreBundle\Model\User $pupil
	 * @param \BNS\App\CoreBundle\Model\User $parent
	 * @deprecated
	 * @see addParent
	 */
	public function linkPupilWithParent(User $pupil, User $parent)
	{
		PupilParentLinkPeer::createPupilParentLink($pupil, $parent);
	}

    /**
	 * @param \BNS\App\CoreBundle\Model\User $pupil
	 * @param \BNS\App\CoreBundle\Model\User $parent
	 * @deprecated
	 * @see removeParent
	 */
	public function unlinkPupilFromParent(User $pupil, User $parent)
	{
        if($this->pupilParentLinkAlreadyExists($pupil, $parent))
        {
            PupilParentLinkPeer::removePupilParentLink($pupil, $parent);
        }

        if(count($parent->getChildren()) == 0)
        {
            $this->deleteUser($parent);
        }

    }

    /**
     * Vérifie l'existance d'une relation parent enfant
     *
     * @deprecated
     * @see hasParent
     * @param User $pupil
     * @param User $parent
     * @return bool
     */
    public function pupilParentLinkAlreadyExists(User $pupil, User $parent)
    {
        $pupilParents = $this->getUserParent($pupil);

        foreach ($pupilParents as $pupilParent)
        {
            if($pupilParent->getId() == $parent->getId()) return true;
        }

        return false;
    }

    /**
     * Adds a parent to the given user.
     *
     * @param int|User $childId
     * @param int|User $parentId
     */
    public function addParent($childId, $parentId)
    {
        if ($childId instanceof User) {
            $childId = $childId->getId();
        }
        if ($parentId instanceof User) {
            $parentId = $parentId->getId();
        }
        // auth
        $this->addAuthParent($childId, $parentId);
        // app
        $link = PupilParentLinkQuery::create()
            ->filterByUserPupilId($childId)
            ->filterByUserParentId($parentId)
            ->findOneOrCreate()
        ;
        if ($link->isNew()) {
            $link->save();
        }
    }

    /**
     * Removes a parent of the given user.
     *
     * @param int|User $childId
     * @param int|User $parentId
     */
    public function removeParent($childId, $parentId)
    {
        if ($childId instanceof User) {
            $childId = $childId->getId();
        }
        if ($parentId instanceof User) {
            $parentId = $parentId->getId();
        }

        // auth
        $this->removeAuthParent($childId, $parentId);
        // app
        $link = PupilParentLinkQuery::create()
            ->filterByUserPupilId($childId)
            ->filterByUserParentId($parentId)
            ->findOne()
        ;
        if ($link) {
            $link->delete();
        }
    }

    /**
     * Removes all parents of the given user.
     *
     * @param int|User $childId
     */
    public function removeAllParents($childId)
    {
        if ($childId instanceof User) {
            $childId = $childId->getId();
        }
        /** @var PupilParentLink[] $links */
        $links = PupilParentLinkQuery::create()
            ->filterByUserPupilId($childId)
            ->find()
        ;
        foreach ($links as $link) {
            $this->removeAuthParent($childId, $link->getUserParentId());
            $link->delete();
        }
    }

    /**
     * Checks if user has given parent
     *
     * @param int|User $childId
     * @param int|User $parentId
     * @return bool
     */
    public function hasParent($childId, $parentId)
    {
        if ($childId instanceof User) {
            $childId = $childId->getId();
        }
        if ($parentId instanceof User) {
            $parentId = $parentId->getId();
        }

        return !!PupilParentLinkQuery::create()
            ->filterByUserPupilId($childId)
            ->filterByUserParentId($parentId)
            ->count()
        ;
    }

    protected function addAuthParent($childId, $parentId)
    {
        $this->api->send('post_user_parents', [
            'route' => [
                'id' => $childId,
            ],
            'values' => [
                'parent_id' => $parentId,
            ],
        ]);
    }

    protected function removeAuthParent($childId, $parentId)
    {
        try {
            $this->api->send('delete_user_parents', [
                'route' => [
                    'id' => $childId,
                ],
                'values' => [
                    'parent_id' => $parentId,
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            // link was not present in auth: not an important error, carry on
        }
    }

        //////////////    FONCTIONS LIEES AUX ROLES     \\\\\\\\\\\\\\\\\

	/**
	 * Cette méthode récupère les droits de l'utilisateur courant pour déterminer quel est son rôle principal
	 * (il faut donc faire un setUser() avant d'utiliser cette méthode)
	 *
	 * @return string la chaîne de caractère qui correspond au rôle principal de l'utilisateur courant
	 */
    public function getMainRole($print = false)
    {
        $rights = $this->getRights();
        $roles = array();
        foreach ($rights as $rightsInGroup) {
            $roles = array_merge($roles, $rightsInGroup['roles']);
        }

        if (0 >= count($roles)) {
            return false;
        }
        if(!$print)
        {
            return strtolower($this->roleManager->getGroupTypeRoleFromId(min($roles))->getType());
        }else{
            return $this->roleManager->getGroupTypeRoleFromId(min($roles))->getLabel();
        }

    }

	public function getUserType()
	{
		$redisDatas = $this->api->getRedisConnection()->hget('user_' . $this->getUser()->getUsername(),'user_type');
		if(!$redisDatas){
			$rights = $this->getRights();
			$rolesArray = array();
			foreach($rights as $group){
				foreach($group['roles'] as $roleId){
					$rolesArray[] = $roleId;
				}
			}
			$rolesObjects = GroupTypeQuery::create()->findById(array_unique($rolesArray));
			$isChild = true;
			foreach($rolesObjects as $role){
				if($role->getType() != 'PUPIL'){
					$isChild = false;
				}
			}

			$value = $isChild == true ? "child" : "adult";
			$this->api->getRedisConnection()->hset('user_' . $this->getUser()->getUsername(),'user_type',$value);
			$redisDatas = $value;
		}
		return $redisDatas;
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
		return $this->getUserType() == 'adult';
	}



	//////////////    FONCTIONS LIEES AUX RESSOURCES     \\\\\\\\\\\\\\\\\

    public function hasUnlimitedResources()
    {
        if (!$this->unlimitedAllowed) {
            return false;
        }

        if (null === $this->unlimitedResources) {
            $contextFactory = $this->container->get('bns_app_core.context.context_group_factory');
            $toggleManager = $this->container->get('qandidate.toggle.manager');
            foreach ($this->getGroupsWherePermission('MEDIA_LIBRARY_MY_MEDIAS') as $group) {
                $context = $contextFactory->createContextGroup($group);

                if ($toggleManager->active('storage_unlimited', $context)) {
                    return $this->unlimitedResources = true;
                }
            }
            $this->unlimitedResources = false;
        }

        return $this->unlimitedResources;
    }

    /*
     * Renvoie le stockage autorisé pour l'utilisateur
     */
    public function getRessourceAllowedSize()
    {
        if ($this->hasUnlimitedResources()) {
            // unlimited == 1 Po 1000 To
            return 1000000000000000.00;
        }
        $authorisedValue = 0;
        $attribute = $this->isAdult() ? 'RESOURCE_QUOTA_USER' : 'RESOURCE_QUOTA_CHILD';
        foreach ($this->getGroupsWherePermission('MEDIA_LIBRARY_MY_MEDIAS') as $group) {
            $value = $group->getAttribute($attribute);
            if ($value > $authorisedValue) {
                $authorisedValue = $value;
            }
        }

        return $authorisedValue;
    }

    /**
     * @return float
     */
    public function getResourceUsageRatio()
    {
        if ($this->hasUnlimitedResources() || $this->getRessourceAllowedSize() == 0) {
            return 0.00;
        }
        $quota = round($this->getUser()->getResourceUsedSize() / $this->getRessourceAllowedSize(), 2) * 100;
        if ($quota > 100) {
            return 100.00;
        }

        return $quota;
    }

    public function getAvailableSize()
    {
        if ($this->hasUnlimitedResources()) {
            // unlimited == 1 Po 1000 To
            return 1000000000000000.00;
        }

        return $this->getRessourceAllowedSize() - $this->getUser()->getResourceUsedSize();
    }

    /**
     * Ajout de contenu pour un utilisateur
     * @param int $size Taille du document en octets
     */
    public function addResourceSize($size)
    {
        $this->getUser()->setResourceUsedSize($this->getUser()->getResourceUsedSize() + $size);
        $this->getUser()->save();
    }



	/**
	 * Permet de réinitialiser le mot de passe de l'utilisateur passé en paramètre et retourne l'objet utilisateur
	 * avec l'attribut password setté
	 *
	 * @param User $user
	 *
	 * @return User
	 */
	public function resetUserPassword(User $user, $sendEmail = true, $baseUrl = null, $expireCredential = true )
	{
        // We can't change Admin password
        if ($this->setUser($user)->hasRightSomeWhere('ADMIN_PRETENDED') || $this->setUser($user)->hasRightSomeWhere('ADMIN_ACCESS')) {
            throw new AccessDeniedException();
        }

		$response = $this->api->send('reset_user_password', array(
			'route' => array(
				'username' => $user->getLogin(),
			), 'values' => array('expire' => $expireCredential)
		));

		$user->setPassword($response['plain_password']);

        if($sendEmail)
        {
            $this->bns_mailer->sendUser('RESET_PASSWORD_SUCCESS', array(
                'first_name'		=> $user->getFirstName(),
                'login'				=> $user->getLogin(),
                'plain_password'	=> $user->getPassword()
            ), $user, [], $baseUrl);
        }
		return $user;
	}

	/**
	 * @param array<User> $users
	 *
	 * @return array<User>
	 */
	public function resetUsersPassword($users, $populateUsers = true)
	{
		$userIds = array();
		foreach ($users as $user) {
            if ($this->setUser($user)->hasRightSomeWhere('ADMIN_PRETENDED') || $this->setUser($user)->hasRightSomeWhere('ADMIN_ACCESS')) {
                // prevent change password for admin
                continue;
            }
            $userIds[] = $user->getId();
		}

		$responses = $this->api->send('reset_user_password', array(
			'route' => array(
				'username' => 'unknown', // don't care
			),
			'values'	=> array(
				'user_ids' => $userIds
			)
		));

		if ($populateUsers) {
			// Populate object
			foreach ($responses as $response) {
				foreach ($users as $user) {
					if ($response['id'] == $user->getId()) {
						$user->setPassword($response['plain_password']);
						break 1;
					}
				}
			}

			return $users;
		}

		return $responses;
	}

    /**
     * @return string The password confirmation token to send in the e-mail
     */
    public function requestConfirmationResetPassword(User $user = null)
    {
        if (!$user) {
            // @deprecated user parameter should be mandatory
            $user = $this->getUser();
        }
        $this->setUser($user);
        if ($this->hasRightSomeWhere('ADMIN_PRETENDED') || $this->hasRightSomeWhere('ADMIN_ACCESS')) {
            throw new AccessDeniedHttpException();
        }

        $confirmationToken = sha1($this->secureRandomGenerator->nextBytes(32));

        $this->api->send(
            'flag_reset_user_password',
            array(
                'route' => array(
                    'username' => $user->getUsername()
                ),
                'values' => array(
                    'confirmation_token' => $confirmationToken
                )
            )
        );

        return $confirmationToken;
    }

	/**
	 * @param User $user
	 */
	public function flagChangePassword(User $user)
	{
		$this->api->send('user_flag_change_password', array(
			'route'		=> array(
				'username' => $user->getUsername()
			)
		));
	}

    /**
     * Supprime définitivement un utilisateur
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $withParent
     */
    public function deleteUser(User $user, $withParent = false)
    {

        $this->setUser($user);
        $groups = $this->getGroupsUserBelong();

        $isChild = $this->isChild();

        //D'abord appliquer récursivement aux parents
        if($withParent)
        {
            foreach($this->getUserParent($user) as $parent)
            {
                $this->deleteUser($parent);
            }
        }
        //On doit : supprimer App + Supprimer auth
        $this->api->send(
            'user_delete', array(
                'route' 	=> array(
                    'id' => $user->getId()
                ),
                'values'	=> array()
            )
        );
		//Clear du cache des groupes auxquel l'utilisateur appartient

        foreach ($groups as $group) {
            $this->api->resetGroup($group->getId(), false);
        }
        $user->archive($this->container->getParameter('user_archive_duration'));

        if(BNSAccess::isConnectedUser())
        {
            $this->container->get('bns.right_manager')->trackAnalytics('ARCHIVED_USER', $this->getUser());
        }
        $this->container->get('bns.analytics.manager')->track('ARCHIVED_USER', $this->getUser());

        //Si c'est en enfant on supprime également les comptes parents
        if($isChild)
        {
            $parents = $user->getParents();
            if($parents)
            {
                foreach($parents as $parent)
                {
                    $children = $this->getUserChildren($parent);
                    if(count($children) > 0) {
                        //Si un seul enfant qui est celui que nous sommes en train de supprimer
                        if (count($children) == 1 && $children[0]->getId() == $user->getId()) {
                            $this->deleteUser($parent);
                        }
                    }
                }
            }
        }
	}

    /**
     * Supprime définitivement un utilisateur
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $withParent
     */
    public function deleteUsers($userIds, $withParent = false)
    {

        if($withParent == true)
        {
            $userIds = array_merge($userIds, UserQuery::create()->usePupilParentLinkRelatedByUserParentIdQuery()->filterByUserPupilId($userIds)->endUse()->find()->getPrimaryKeys());
        }

        $this->api->send(
            'user_delete_in_group', array(
                'route' 	=> array(),
                'values'	=> array('ids' => $userIds)
            )
        );
        foreach(UserQuery::create()->findById($userIds) as $user)
        {
            $user->archive($this->container->getParameter('user_archive_duration'));
            if(BNSAccess::isConnectedUser())
            {
                $this->container->get('bns.right_manager')->trackAnalytics('ARCHIVED_USER', $this->getUser());
            }
            $this->container->get('bns.analytics.manager')->track('ARCHIVED_USER', $this->getUser());
        }
    }


    /**
     * Supprime définitivement un utilisateur
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $withParent
     */
    public function restoreUser(User $user, $withParent = false)
    {
        $this->setUser($user);
        $groups = $this->getGroupsUserBelong();

        //D'abord appliquer récursivement aux parents
        if($withParent)
        {
            foreach($this->getUserParent($user) as $parent)
            {
                $this->restoreUser($parent);
            }
        }
        //On doit : supprimer App + Supprimer auth
        $this->api->send(
            'restore_user', array(
                'route' 	=> array(
                    'username' => $user->getUsername()
                ),
                'values'	=> array()
            )
        );
        //Clear du cache des groupes auxquel l'utilisateur appartient
        foreach ($groups as $group) {
            $this->api->resetGroup($group->getId(), false);
        }
        $user->restore();
    }


    /**
     * Actions liées à l'activation / désactivation
     */

    /**
     * Change le statut d'un utilisateur selon la valeur en paramètre
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $state Statut demandé
     * @param boolean $withParent Doit on appliquer aux parents de l'utilisateur s'il en a
     */
    public function changeStatus(User $user, $state = true, $withParent = false)
    {
        $this->setUser($user);
        if($withParent)
        {
            foreach($this->getUserParent($user) as $parent)
            {
                $this->changeStatus($parent, $state, true);
            }
        }
        $this->setUser($user);
        $this->api->send('disable_user', array(
            'route' => array(
                'username' => $user->getUsername()
            ),
            'values'	=> array(
                'state' => $state
            )
        ));
        $this->resetRights();

        $action = $state ? 'REACTIVATED_USER' : 'DEACTIVATED_USER';

        if(BNSAccess::isConnectedUser())
        {
            $this->container->get('bns.right_manager')->trackAnalytics($action, $user);
        }
        $this->container->get('bns.analytics.manager')->track($action, $user);
    }

    /**
     * Désactive un utilisateur : il ne pourra plus se connecter
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $withParents
     */
	public function disableUser(User $user, $withParents = false)
	{
        $this->changeStatus($user, false, $withParents);
    }

	 /**
     * Active un utilisateur : il pourra se connecter
     * @param \BNS\App\CoreBundle\Model\User $user
     * @param boolean $withParents
     */
	public function enableUser(User $user,$withParents = false)
	{
		$this->changeStatus($user, true, $withParents);
	}

	//////////////    FONCTIONS LIEES AUX INVITATIONS     \\\\\\\\\\\\\\\\\
	/**
	 * Retourne un tableau qui contient la liste des invitations de l'utilisateur courant ($this->user)
	 *
	 * @return array
	 */
	public function getInvitations()
	{
		return $this->api->send('get_user_invitation', array(
			'route' => array(
				'username' => $this->user->getUsername()
			)
		));
	}

	/**
	 * Accepte l'invitation $invitationId pour l'utilisateur courant $this->user
	 *
	 * @param int $invitationId
	 */
	public function acceptInvitation($invitationId)
	{
		$this->api->send('invitation_accept', array(
			'route' => array(
				'invitation_id' => $invitationId
			),
			'values' => array(
				'username' => $this->user->getUsername(),
				'user_id' => $this->user->getId()
			)
		));
	}

	/**
	 * Décline l'invitation $invitationId pour l'utilisateur courant $this->user
	 *
	 * @param int $invitationId
	 */
	public function declineInvitation($invitationId)
	{
		$this->api->send('invitation_decline', array(
			'route' => array(
				'invitation_id' => $invitationId
			),
			'values' => array(
				'username' => $this->user->getUsername(),
				'user_id' => $this->user->getId()
			)
		));
	}

	/**
	 * Bloque l'invitation $invitationId pour toujours pour l'utilisateur courant
	 *
	 * @param int $invitationId
	 */
	public function neverAcceptInvitation($invitationId)
	{
		$this->api->send('invitation_never_accept', array(
			'route' => array(
				'invitation_id' => $invitationId
			),
			'values' => array(
				'username' => $this->user->getUsername(),
				'user_id' => $this->user->getId()
			)
		));
	}

	//////////////    FONCTIONS LIEES A L'IMPORT D'UTILISATEUR PAR DES FICHIERS CSV     \\\\\\\\\\\\\\\\\

	public function importUserFromCSVFile(UploadedFile $file, $format, Group $group, GroupType $role = null)
	{
		$extension = $file->guessExtension();
		if (!$extension) {
			// extension cannot be guessed
			$extension = 'bin';
		}

		// We waiting for TXT extension only
		if (strtolower($extension) != 'txt') {
			throw new UploadException('The file extension is NOT correct, waiting for .CSV file !', 1);
		}

		$fileTmpName = rand(1, 99999).'.'.$extension;
		$tmpDir = $this->tmpDir;
		$file->move($tmpDir, $fileTmpName);

		$rowCount = $successInsertCount = $skipedCount = 0;
		$format = $format == 0 ? 'BNSFormat' : 'BaseElevesFormat';

		if (($handle = fopen($tmpDir . $fileTmpName, 'r')) !== false) {

			try {
			    $groupManager = $this->container->get('bns.group_manager');
			    $groupManager->setGroup($group);

				while (($data = fgetcsv($handle, 0, ';')) !== false) {
					$rowCount++;

					// Header row, don't care
					if ($rowCount == 1) {
						continue;
					}

					if ($format == 'BaseElevesFormat') {
						if (count($data) < 20) {
							throw new UploadException('Wrong format for BaseElevesFormat !', 2);
						}

						$bsDatas = array();
						$bsDatas[0] = $data[0];
						$bsDatas[1] = $data[2];
						$bsDatas[2] = $data[3];
						$bsDatas[3] = $data[4];
						$data = $bsDatas;
					}
					elseif ($format == 'BNSFormat' && count($data) < 4) {
						throw new UploadException('Wrong format for BNSFormat !', 3);
					}

					$success = true;
					$skiped = false;
					$user = null;

					try {

					    $lastname = mb_convert_encoding(trim($data[0]), 'UTF-8', 'ASCII, UTF-8, ISO-8859-1, CP1252');
					    $firstname = mb_convert_encoding(trim($data[1]), 'UTF-8', 'ASCII, UTF-8, ISO-8859-1, CP1252');
					    $birthday = StringUtil::convertDateFormat($data[2]);
                        if ($lastname == "" || $firstname== "" ) {
                            continue;
                        }
					    $userIds = UserQuery::create()
					        ->filterByFirstName($firstname)
					        ->filterByLastName($lastname)
					        ->filterByBirthday($birthday)
					        ->select('Id')
					        ->find()->getArrayCopy();

					    if (0 == count($userIds) || 0 == count(array_intersect($userIds, $groupManager->getUsersIds()))) {
    						$user = $this->createUser(array(
    							'last_name'		=> $lastname,
    							'first_name'	=> $firstname,
    							'birthday'		=> $birthday,
    							'gender'		=> $data[3],
    							'lang'			=> BNSAccess::getLocale()
    						));
					    } else {
					        $success = false;
					        $skiped = true;
					    }
					}
					catch (HttpException $e) {
						$success = false;
					}

					$isPupil = false;
					if (null !== $user) {
						if ($role != null) {
							if ($role->getType() == "PUPIL" && $group->getGroupType()->getType() == "CLASSROOM") {
								$isPupil = true;
								$this->container->get('bns.classroom_manager')->assignPupil($user);
							}
						}

						if (!$isPupil) {
							$this->linkUserWithGroup($user, $group, $role);
						}
					}

					if ($skiped) {
					    $skipedCount++;
					} else if ($success) {
					    $successInsertCount++;
					}
				}

			}
			catch (\Exception $e)
			{
				fclose($handle);
				unlink($tmpDir . $fileTmpName);

				throw $e;
			}

			// Finally
			fclose($handle);
		}

		// Deleting the file
		unlink($tmpDir . $fileTmpName);

		return array(
			'user_count' => $rowCount - 1,
			'success_insertion_count' => $successInsertCount,
		    'skiped_count' => $skipedCount,
		);
	}

    public function importTeacherFromVcardFile(UploadedFile $file)
    {
        $extension = $file->guessExtension();

        if (strtolower($extension) != 'vcf') {
            throw new UploadException('The file extension is NOT correct, waiting for .VCF file !', 1);
        }

        $fileTmpName = rand(1, 99999) . '.' . $extension;
        $tmpDir = $this->tmpDir;
        $file->move($tmpDir, $fileTmpName);
         if (!$this->verifyVCard($tmpDir, $fileTmpName)) {
             throw new BadRequestHttpException(' il faut renseigner au moins un nom, un prénom et un email pour ajouter un enseignant');
         }
        if (($handle = fopen($tmpDir . $fileTmpName, 'r')) !== false) {

            try {
                $vcard = Reader::read(fopen($tmpDir . $fileTmpName, 'r'));
                $datas = array();
                $name = $vcard->N->getJsonValue();
                $datas['last_name'] = $name[0][0];
                $datas['first_name'] = $name[0][1];
                $datas['lang'] = BNSAccess::getLocale();
                $datas['email'] = $vcard->EMAIL->getValue();
                $datas['phone'] = isset($vcard->TEL) ?  $vcard->TEL->getValue() : null;
                $user = UserQuery::create()->filterByEmail($vcard->EMAIL->getValue())->findOne();
                if (!$user) {
                    $newUser = $this->createUser($datas);
                    $profile = $newUser->getProfile();
                    $profile->setOrganization($vcard->ORG->getValue())->setPublicData(false)->setAddress($vcard->ADR->getValue())->setJob($vcard->TITLE->getValue())->save();
                    $this->container->get('bns.classroom_manager')->assignTeacher($newUser);
                } else {
                    throw new BadRequestHttpException('un enseignant avec le mail : ' . $user->getEmail() . ' existe déjà');
                }


            } catch (\Exception $e) {
                fclose($handle);
                unlink($tmpDir . $fileTmpName);

                throw $e;
            }

            // Finally
            fclose($handle);
        }
        unlink($tmpDir . $fileTmpName);

    }

    /**
     * @return boolean
     */
    public function verifyVCard($tmpDir, $fileTmpName)
    {

        if (($handle = fopen($tmpDir . $fileTmpName, 'r')) !== false) {

            try {
                $vcard = Reader::read(fopen($tmpDir . $fileTmpName, 'r'));
                if (isset($vcard->N) && isset($vcard->EMAIL)) {
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                fclose($handle);
                unlink($tmpDir . $fileTmpName);

                throw $e;
            }
        }
    }


	public function linkUserWithGroup(User $user, Group $group, GroupType $role = null)
	{
		$container = BNSAccess::getContainer();
		if (null === $role) {
			$container->get('bns.group_manager')
				->setGroup($group)
				->addUser($user);
		}
		else {
			$container->get('bns.role_manager')
				->setGroupTypeRole($role)
				->assignRole($user, $group->getId());
		}
	}

	public function unlinkUserWithGroup(User $user, Group $group, GroupType $role = null)
	{
		$container = BNSAccess::getContainer();
		if (null === $role) {
			$container->get('bns.group_manager')
				->setGroup($group)
				->removeUser($user);
		}
		else {
			$this->roleManager
				->unassignRole($user->getId(), $group->getId(),$role->getType());
		}
        $this->resetRights();
	}

	/**
	 * @param string $username
	 *
	 * @return array
	 */
	public function getUserFromCentral($username)
	{
		return $this->api->send('user_read',
			array(
				'route' =>  array(
					'username' => $username
				)
			)
		);
	}

    public function getUserFromCentralSafe($username, $cache = true)
    {
        try {
            return $this->api->send('user_read',
                array(
                    'route' =>  array(
                        'username' => $username
                    )
                ), $cache);
        } catch (\Exception $e) { }

        return null;
    }

	/**
	 * @param string $confirmationToken
	 *
	 * @return User
	 */
	public function getUserFromConfirmationToken($confirmationToken)
	{
		$userData = $this->api->send('user_confirmation_token',
			array(
				'route' =>  array(
					'confirmation_token' => $confirmationToken
				)
			)
		);

		return $this->hydrateUser($userData);
	}

    /**
     * @return boolean
     */
    public function isRequestPassword()
    {
        if(!$this->container->hasParameter('disable_password_wheel'))
        {
            $userData = $this->getUserFromCentral($this->getUser()->getUsername());
            return !isset($userData['password_created_at']);
        }else{
            if($this->container->getParameter('disable_password_wheel') === 'child')
            {
                if($this->getUserType() == 'child')
                {
                    return false;
                }else{
                    $userData = $this->getUserFromCentral($this->getUser()->getUsername());
                    return !isset($userData['password_created_at']);
                }
            }elseif($this->container->getParameter('disable_password_wheel') === 'adult'){
                if($this->getUserType() == 'adult')
                {
                    return false;
                }else{
                    $userData = $this->getUserFromCentral($this->getUser()->getUsername());
                    return !isset($userData['password_created_at']);
                }
            }else{
                return false;
            }
        }
    }

    /**
     * @params string $email
     *
     * @return User
     */
    public function getUserByEmail($email, $useCache = true)
    {
        // TODO handle multiple user case
        try {
            $userData = $this->api->send('user_by_email', [
                'values' => ['email' => $email]
            ], $useCache);
        } catch (\Exception $e) {
            return null;
        }

        return $this->hydrateUser($userData);
    }



	/**
	 * When user doesn't exist on the APP instance, we must create a fake User object
	 *
	 * @param array $userData
	 *
	 * @return \BNS\App\CoreBundle\Model\User
	 */
	public function createTemporaryUser($userData)
	{
		$user = new User();
		$user->setId($userData['id']);
		$user->setLogin($userData['username']);
		$user->setIsEnabled($userData['enabled']);
		$user->setFirstName($userData['first_name']);
		$user->setLastName($userData['last_name']);
		$user->setLang($userData['lang']);
        $user->setBirthday(isset($userData['birthday']) ? $userData['birthday'] : null);

		if (isset($userData['email'])) {
			$user->setEmail($userData['email']);
		}
		if (isset($userData['password_requested_at'])) {
			$user->setPasswordRequestedAt($userData['password_requested_at']);
		}
		if (isset($userData['password_created_at'])) {
			$user->setPasswordCreatedAt($userData['password_created_at']);
		}

		// Can't save the user
		$user->setIsReadOnly(true);
		$user->setNew(false);

		return $user;
	}

	/**
	 * Launched when user logging
	 *
	 * @param string $skip the name of the state to skip (invitation, reset_password, notification, ...)
	 *
	 * @return false|string Url to redirect the user
	 */
	public function onLogon($skip = null)
	{
        // TODO refactor this to use only event listener and make them mandatory
        $this->setUser(BNSAccess::getUser());

        $this->container->get('session')->remove('has_cerise');

        /*
         * Vérification des offres from PAAS
         */
        $this->container->get('bns.paas_manager')->initSubscriptionForSession();

        // rescue mode: try to add an EXPRESS licence for fr users
        $userGroups = $this->getGroupsUserBelong();
        if (!count($userGroups)) {
            if ($this->tryAddExpressLicence()) {
                $userGroups = $this->getGroupsUserBelong();
            }
        }

        // Has one or more group ?
        if (count($userGroups) == 0) {
            return $this->container->get('router')->generate('context_no_group');
        }

        switch ($skip) {

            default:

            // Vérification des invitations
            $invitations = $this->getInvitations();
            if (isset($invitations[0])) {
                return $this->container->get('router')->generate('user_invitations');
            }
            case 'invitation': //use to skip invitation on logon page

            // Need to generate new password
            if ($this->isRequestPassword()) {
                return $this->container->get('router')->generate('user_password');
            }
            case 'reset_password': //use to skip reset password on logon page

            // Need to fill email for notifications
            if ($this->isAdult() && !$this->getUser()->getEmail()) {
                return $this->container->get('router')->generate('user_front_add_name_email');
            }
            case 'notification': //use to skip notification on logon page

        }
		/*
		 * /!\
		 *
		 * Do NOT forget to launch again this method after your own process (example, when you finishing to manage the user invitation,
		 * launch this method to know if there is another process in login queue and redirect on them).
		 *
		 * /!\
		 */





		/*
		 * Do NOT add context process AFTER this line !
		 */

		// On initialise le contexte
		$this->container->get('bns.right_manager')->initContext();

		/* bns-9661
		 *  Avant de mettre ajout la date de la dernière connexion,
		 *  vérifier s'il s'agit de la première connexion.
		 *  Si c'est le cas, alors il faut initialiser la date d'expiration de l'user côté App et Auth.
		 MAJ NANTES
		if ($this->getUser()->getLastConnection() === null)
		{
		    $this->getUser()->setExpiresAt(null);

		}
		/* Fin bns-9661 */



		/* bns-9661 */
		// MAJ de l'user dans l'Auth
		//$this->updateUser($this->getUser());
		/* Fin bns-9661 */

        //Si il est enseignant dans une classe non validée

        /*

        if($this->container->hasParameter('check_group_validated') && $this->container->hasParameter('check_group_validated') == true)
        {
            foreach($userGroups as $group)
            {
                if($group->getType() == 'CLASSROOM' && !$group->isValidated() && $this->hasRoleInGroup($group->getId(),'TEACHER'))
                {
                    BNSAccess::getSession()->set('bns.school_validation_alert',"Attention : votre classe n'a pas été validée par votre école. En validant votre classe vous obtiendrez 5Go de stockage dans la médiathèque.");
                }
            }
        }

        */

        // Catch the user target path redirection
        $session = $this->container->get('session');
        if ($redirect = $session->get('_bns.target_path')) {
            $session->remove('_bns.target_path');

            if (preg_match('#^(https?://[a-zA-Z0-9._-]*)?/ent/api/.*#', $redirect)) {
                // prevent redirect to an api route
                // add additional restriction to route that we shouldn't redirect to

                return false;
            }

            return $redirect;
        }

        return false;
    }

    /*
     * Attribution un token de connexion à un utilisateur
     */
    public function generateConnexionToken(User $user)
    {
        // TODO change this to use random generator
        $token = sha1(md5(rand(0,9999999) . $this->container->getParameter('symfony_secret') . rand(0,9999999)));
        $user->setConnexionToken($token);
        $user->save();
        return $token;
    }

    public function generateEmailConfirmationToken(User $user)
    {
        // TODO change this to use random generator
        $token = sha1(md5(rand(0,9999999) . $this->container->getParameter('symfony_secret') . rand(0,9999999)));
        $user->setEmailConfirmationToken($token);
        $user->save();
        return $token;
    }

	/**
	 * @param array $userData
	 *
	 * @return User
	 */
	private function hydrateUser($userData)
	{
		if (null == $userData) {
			return null;
		}

		$user = UserQuery::create('u')
			->where('u.Login = ?', $userData['username'])
		->findOne();

		// The user is NOT found in the APP instance, we must create it manually
		if (null == $user) {
			return $this->createTemporaryUser($userData);
		}

		// Inject extra data
		$user->setIsEnabled($userData['enabled']);
		if (isset($userData['password_requested_at'])) {
			$user->setPasswordRequestedAt($userData['password_requested_at']);
		}
		if (isset($userData['password_created_at'])) {
			$user->setPasswordCreatedAt($userData['password_created_at']);
		}

		return $user;
	}

    /**
     * @param User $user
     * @param string $clientId
     */
    public function getAutologinToken(User $user, $clientId, $redirectUri, $duration = 1800)
    {
        $response = $this->api->send('user_authentication_autologin', array(
            'route' => array(
                'userId'      => $user->getId(),
            ),
            'values' => array(
                'clientId'    => $clientId,
                'redirectUri' => $redirectUri,
                'duration'    => $duration
            )
        ));

        if ($response && is_array($response) && isset($response['token'])) {
            return $response['token'];
        }

        return false;
    }

    /**
     * Converts a text list to an actual array of users. Each user has the following data: name, firstName, gender and
     * date.
     *
     * @param $text
     * @return array
     */
    public function textToUserArray($text)
    {
        if (!$text) {
            return [];
        }

        $pupils = trim(preg_replace('/[ \t]/', ' ', $text));
        $pupils = str_replace("\r\n", "\n", $pupils);
        $pupils = str_replace("\r", "\n", $pupils);
        $pupilsList = explode("\n", $pupils);
        $pupilsFinalList = array();
        // Regexp checking following date formats : dd/mm/yyyy or yyyy/mm/dd
        $dateDetect = '#^[0-9]+[/-][0-9]+[/-][0-9]+$#';
        $dateChecker = "/^([0-9]{4}(\/|-)(0[1-9]|1[0-2])(\/|-)(0[1-9]|[1-2][0-9]|3[0-1]))|((0[1-9]|[1-2][0-9]|3[0-1])(\/|-)(0[1-9]|1[0-2])(\/|-)[0-9]{4})$/";

        foreach ($pupilsList as $key => $pupil) {
            $tempList[$key] = explode(" ", trim($pupil, ' '));
            $j = sizeof($tempList[$key]);
            $genderKey = $dateKey = -1;

            if (sizeof($tempList[$key]) < 2) {
                continue;
            }

            if (false !== array_search('M', $tempList[$key])) {
                $genderKey = array_search('M', $tempList[$key]);
                $pupilsFinalList[$key]['gender'] = 'M';
            } elseif (false !== array_search('F', $tempList[$key])) {
                $genderKey = array_search('F', $tempList[$key]);
                $pupilsFinalList[$key]['gender'] = 'F';
            } elseif (false !== array_search('m', $tempList[$key])) {
                $genderKey = array_search('m', $tempList[$key]);
                $pupilsFinalList[$key]['gender'] = 'M';
            } elseif (false !== array_search('f', $tempList[$key])) {
                $genderKey = array_search('f', $tempList[$key]);
                $pupilsFinalList[$key]['gender'] = 'F';
            }

            $detectDateFields = preg_grep($dateDetect, $tempList[$key]);
            $excludeDateFields = array_keys($detectDateFields);

            foreach ($detectDateFields as $possibleDateKey => $possibleDate) {
                //ToDo real check for date (its doesn't work for english format)
                if (preg_match($dateChecker, $possibleDate)) {
                    $pupilsFinalList[$key]['date'] = $possibleDate;
                    $dateKey = $possibleDateKey;
                    break;
                }
            }

            $max = $j -1;
            if (-1 !== $dateKey || -1 !== $genderKey) {
                $max = max($dateKey, $genderKey);
                if ($max < 3 && -1 !== $dateKey && -1 !== $genderKey) {
                    $max = $j -1;
                } elseif ($max < 2) {
                    $max = $j -1;
                }
            }

            for ($i = $max; $i > 0; $i--) {
                if (!in_array($i, [$dateKey, $genderKey], true) && !in_array($i, $excludeDateFields, true)) {
                    $firstNameIndex = $i;
                    break;
                }
            }

            $pupilsFinalList[$key]['name'] = '';
            $pupilsFinalList[$key]['firstName'] = $tempList[$key][$firstNameIndex];
            $first = true;
            for ($i = 0; $i < $max; $i++) {
                if (!in_array($i, [$firstNameIndex, $dateKey, $genderKey]) && !in_array($i, $excludeDateFields)) {
                    if ($first) {
                        $first = false;
                        $pupilsFinalList[$key]['name'] = $tempList[$key][$i];
                    } else {
                        $pupilsFinalList[$key]['name'] .= ' ' . $tempList[$key][$i];
                    }
                }
            }
        }

        return $pupilsFinalList;
    }

    public function importUserFromTextarea(array $users, Group $group, GroupType $role = null)
    {
        $groupManager = $this->container->get('bns.group_manager');
        $groupManager->setGroup($group);
        $rowCount = $successInsertCount = $skipedCount = 0;

        foreach ($users as $data) {
            $rowCount++;

            $lastname = mb_convert_encoding(trim($data['name']), 'UTF-8', 'ASCII, UTF-8, ISO-8859-1, CP1252');
            $firstname = mb_convert_encoding(trim($data['firstName']), 'UTF-8', 'ASCII, UTF-8, ISO-8859-1, CP1252');
            $birthday = isset($data['date']) ? StringUtil::convertDateFormat($data['date']) : null;

            $userIds = UserQuery::create()
                ->filterByFirstName($firstname)
                ->filterByLastName($lastname)
                ->filterByBirthday($birthday)
                ->select('Id')
                ->find()->getArrayCopy();

            $user = null;

            if (0 == count($userIds) || 0 == count(array_intersect($userIds, $groupManager->getUsersIds()))) {
                $user = $this->createUser(array(
                    'last_name' => $lastname,
                    'first_name' => $firstname,
                    'birthday' => $birthday,
                    'gender' => isset($data['gender']) ? $data['gender'] : null,
                    'lang' => BNSAccess::getLocale()
                ));
            }

            $isPupil = false;
            if (null !== $user) {
                if ($role != null) {
                    if ($role->getType() == "PUPIL" && $group->getGroupType()->getType() == "CLASSROOM") {
                        $isPupil = true;
                        $this->container->get('bns.classroom_manager')->assignPupil($user);
                    }
                }

                if (!$isPupil) {
                    $this->linkUserWithGroup($user, $group, $role);
                }
                $successInsertCount++;
            } else {
                $skipedCount++;
            }

        }

        return array(
            'user_count' => $rowCount,
            'success_insertion_count' => $successInsertCount,
            'skiped_count' => $skipedCount,
        );
    }

    /**
     * Gets the group IDs the given user was in for the given year (defaults to last year).
     *
     * @param User $user
     * @param int $year
     * @return array
     */
    public function getPreviousGroupIds(User $user, $year = null)
    {
        if (!$year) {
            $newYear = (int)$this->container->getParameter('registration.current_year');
            $year = $newYear - 1;
        }

        return $this->api->send('user_belonged', [
            'route' => [
                'username' => $user->getUsername(),
                'year' => (int)$year,
            ],
        ]);
    }

    /**
     * Adds an EXPRESS licence to the first FR classroom the user belongs to.
     * To be used on logon for users with no group.
     */
    public function tryAddExpressLicence()
    {
        /**
         * Find the first FR classroom
         * @var Group $targetGroup
         */
        $targetGroup = null;
        $groupsAndRoles = $this->getGroupsAndRolesUserBelongs();
        foreach ($groupsAndRoles as $groupAndRole) {
            $targetGroup = $groupAndRole['group'];
            if ($targetGroup->getCountry() === 'FR' && $targetGroup->getType() === 'CLASSROOM') {
                break;
            }
            $targetGroup = null;
        }

        // if found add an express licence
        if ($targetGroup) {
            $this->container->get('bns.paas_manager')->generateSubscription(
                $targetGroup,
                'EXPRESS',
                'unlimited'
            );
            $this->resetRights();

            return true;
        }

        return false;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function filterName($name)
    {
        //utilisé pour convertir les noms/prénoms des utilisateurs pour leur login par exemple
        $bad = array(
            'À','à','Á','á','Â','â','Ã','ã','Ä','ä','Å','å','Ă','ă','Ą','ą',
            'Ć','ć','Č','č','Ç','ç',
            'Ď','ď','Đ','đ',
            'È','è','É','é','Ê','ê','Ë','ë','Ě','ě','Ę','ę',
            'Ğ','ğ',
            'Ì','ì','Í','í','Î','î','Ï','ï',
            'Ĺ','ĺ','Ľ','ľ','Ł','ł',
            'Ñ','ñ','Ň','ň','Ń','ń',
            'Ò','ò','Ó','ó','Ô','ô','Õ','õ','Ö','ö','Ø','ø','ő',
            'Ř','ř','Ŕ','ŕ',
            'Š','š','Ş','ş','Ś','ś',
            'Ť','ť','Ť','ť','Ţ','ţ',
            'Ù','ù','Ú','ú','Û','û','Ü','ü','Ů','ů',
            'Ÿ','ÿ','ý','Ý',
            'Ž','ž','Ź','ź','Ż','ż',
            'Þ','þ','Ð','ð','ß','Œ','œ','Æ','æ','µ',
            ' ','-','\'','"',
            '&','<','>',
        );

        $good = array(
            'A','a','A','a','A','a','A','a','Ae','ae','A','a','A','a','A','a',
            'C','c','C','c','C','c',
            'D','d','D','d',
            'E','e','E','e','E','e','E','e','E','e','E','e',
            'G','g',
            'I','i','I','i','I','i','I','i',
            'L','l','L','l','L','l',
            'N','n','N','n','N','n',
            'O','o','O','o','O','o','O','o','Oe','oe','O','o','o',
            'R','r','R','r',
            'S','s','S','s','S','s',
            'T','t','T','t','T','t',
            'U','u','U','u','U','u','Ue','ue','U','u',
            'Y','y','Y','y',
            'Z','z','Z','z','Z','z',
            'TH','th','DH','dh','ss','OE','oe','AE','ae','u',
            '','','',''
        );

        // convert special characters
        return preg_replace('([^a-zA-Z0-9])', '', str_replace($bad, $good, $name));
    }

    /**
     * @param int $max
     *
     * @return string
     */
    private static function getNumbersPattern($max = 4)
    {
        $number = '';
        for ($i=0; $i<$max; $i++) {
            $number .= (string) rand(0, 9);
        }

        return $number;
    }
}

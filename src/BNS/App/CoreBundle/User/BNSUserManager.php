<?php

namespace BNS\App\CoreBundle\User;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Utils\String;

/**
 * @author Eymeric Taelman
 */
class BNSUserManager
{
	protected $container;
	protected $security_context;
	protected $api;
	protected $domain_id;
	protected $user;
	protected $rights;
	protected $roleManager;
	protected $tmpDir;
	protected $bns_mailer;

	/**
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	 * @param int $domain_id
	 * @param string $tmpDir
	 */
	public function __construct($container, $domain_id, $tmpDir)
	{
		$this->container	= $container;
		$this->domain_id	= $domain_id;
		$this->tmpDir		= $tmpDir;
		
		$this->api			= $container->get('bns.api');
		$this->roleManager	= $container->get('bns.role_manager');
		$this->bns_mailer	= $container->get('bns.mailer');
	}

	/**
	 * Commande d'un id sur centrale puis création locale
	 * @params $params
	 */
	public function createUser($params, $autoSendMail = false)
	{
		// Vérification que nous avons assez d'infos : prénom, nom, langue
		// Email et date de naissance facultatifs
		if (
			isset($params['first_name']) && 
			isset($params['last_name']) &&
			isset($params['lang'])
		) {
			$first_name = $params['first_name'];
			$last_name = $params['last_name'];
			$username = isset($params['username']) ? $params['username'] : "temporary";
			$email = isset($params['email']) ? $params['email'] : null;
			$lang = isset($params['lang']) ? $params['lang'] : 'fr';
			$domain_id = $this->domain_id;
			
			$values = array(
				'first_name'	=> $first_name,
				'last_name'		=> $last_name,
				'lang'			=> $lang,
				'username'		=> $username,
				'email'			=> $email,
				'domain_id'		=> $domain_id,
				'lang'			=> $lang,
			);
			
			if (isset($params['gender'])) {
				$values['gender'] = $params['gender'];
			}
			
			if (isset($params['salt']) && isset($params['password'])) {
				$values['salt'] = $params['salt'];
				$values['password'] = $params['password'];
			}

			$response = $this->api->send('user_create',array('values' => $values));

			//Username et user_id sont gérés par la centrale
			$values['user_id'] = $response['id'];
			$values['username'] = $response['username'];
			if (isset($params['birthday'])) {
				$values['birthday'] = $params['birthday'];
            }

			$newUser = UserPeer::createUser($values);
			
			if ($autoSendMail) {
				$this->bns_mailer->send(
					'WELCOME',
					array(
						'first_name'	=> $newUser->getFirstName(),
						'last_name'		=> $newUser->getlastName(),
						'login'			=> $newUser->getLogin(),
						'password'		=> $response['plain_password'],
					),
					$newUser->getEmail(),
					$newUser->getLang()
				);
			}
			
			return $newUser;
		}
		else {
			throw new HttpException(500, 'Not enough datas to create user');
		}
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

	public function usernameFactory($first_name,$last_name)
	{
		return 'username' . rand(1,99999999);
	}

	/**
	 * Permet de mettre à jour la base de données de la centrale avec l'utilisateur $user donné en paramètre
	 * /!\ Attention, cette méthode ne met pas à jour en local mais se base sur les valeurs actuelles des attributs de l'objet $user
	 * et les envoient à la centrale
	 * 
	 * @param BNS\App\CoreBundle\Model\User $user l'utilisateur dont on veut mettre à jour côté central
	 * @see http://redmine.pixel-cookers.com/projects/bns-3-dev/wiki/Centrale-user-api#Mise-à-jour-dun-utilisateur
	 */
	public function updateUser(User $user)
	{
		if (null == $user || null == $user->getId()) {
			throw new InvalidArgumentException('You provide invalide user (potential issue origin: no id, equals to null');
		}

		$this->api->send('user_update', array(
			'route' 	=> array(
				'username' => $user->getUsername()
			),
			'values'	=> array(
				'id'			=> $user->getId(),
				'username'		=> $user->getUsername(),
				'email'			=> $user->getEmail(),
				'first_name'	=> $user->getFirstName(),
				'last_name'		=> $user->getLastName(),
				'domain_id'		=> $this->domain_id,
				'lang'			=> $user->getLang()
			),
		));
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
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * 
	 * @return \BNS\App\CoreBundle\User\BNSUserManager
	 */
	public function setUser(User $user)
	{
		if (null != $this->user && $this->user->getId() != $user->getId()) {
			$this->rights = null;
			$this->groups = null;
		}
		
		$this->user = $user;
		
		return $this;
	}
	
	/**
	 * @return User
	 * 
	 * @throws \Exception
	 */
	public function getUser()
	{
		if (isset($this->user)) {
			return $this->user;
		}
		else {
			throw new \Exception('The user is not set');
		}
	}

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
		if (!isset($this->rights) || null == $this->rights) {
			$redisDatas = $this->api->getRedisConnection()->get('rights:' . $this->getUser()->getUsername());
			if(!$redisDatas){
				$rights = $this->getFullRightsAndGroups();
				$sortedRights = array();
				//manipulation du tableau de droits
				foreach($rights as $group) {
					$currentGroupId = $group['group']['id'];
					$currentGroupGroupType = GroupTypeQuery::create()
						->joinWithI18n(BNSAccess::getLocale())
					->findOneById($group['group']['group_type_id']);

					$parentId = isset($group['group']['group_parent_id']) ? $group['group']['group_parent_id'] : null;

					$currentGroupInfos = array(
						'id'				=> $currentGroupId,
						'group_name'		=> $group['group']['label'],
						'group_type'		=> $currentGroupGroupType->getType(),
						'group_type_id'		=> $group['group']['group_type_id'],
						'group_parent_id'	=> $parentId,
						'domain_id'			=> $group['group']['domain_id'],
						'roles'				=> array()

					);

					$currentGroupInfos['permissions'] = array();
					foreach($group['finals_permissions'] as $permissionInfo) {
						$currentGroupInfos['permissions'][] = $permissionInfo['unique_name'];
					}

					foreach($group['roles'] as $role) {
						$currentGroupInfos['roles'][] = $role['id'];
					}
					
					$sortedRights[$currentGroupId] = $currentGroupInfos;
				}
				$this->saveRights($sortedRights);
				$this->rights = $sortedRights;
			}else{
				$this->rights = json_decode($redisDatas,true);
			}
		}
		return $this->rights;
	}
	
	public function saveRights($sortedRights)
	{
		$this->api->getRedisConnection()->set('rights:' . $this->getUser()->getUsername(),json_encode($sortedRights));
	}
	
	/**
	 * Permet de réinitialiser les droits, utile lors d'un reloadRights() (BNSRightManager)
	 */
	public function resetRights()
	{
		$this->rights = null;
		$this->api->resetUser($this->getUser()->getUsername());
	}
	
	/*
	 * Renvoie tous les droits / groupes de l'utilisateur à partir de la centrale et donc de l'API
	 * @return array
	 */
	public function getFullRightsAndGroups()
	{
		$rights = $this->api->send('user_rights',
			array(
				'route' =>  array(
					'username' => $this->getUser()->getUsername(),
					'format' => 'full'
				)
			)
		);
		
		return $rights;
	}
	/*
	 * L'utilisateur a-t-il le droit passé en paramètre ?
	 * @param $group_id : Id du groupe sur lequel on demande le droit : par défaut le groupe en cours
	 * @param $permission_unique_name  : Unique name de la permission
	 * @return Boolean
	 */
	public function hasRight($permission_unique_name, $group_id)
	{
		$rights = $this->getRights();
		if (isset($rights[$group_id]['permissions'])) {
			return in_array($permission_unique_name,$rights[$group_id]['permissions']);
		}
		
		return false;
	}
	
	/**
	 * @param string $permissionUniqueNamePattern
	 * 
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
			if ($this->hasRight($permission_unique_name,$group_id)) {
				return true;
			}
		}
		
		return false;
	}
	
	/*
	 * Renvoie les id des groupes où j'ai la permission
	 * @params String $permission_unique_name la permission en question
	 */
	public function getGroupIdsWherePermission($permission_unique_name)
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
	
	/*
	 * Renvoie les groupes où j'ai la permission
	 * @params String $permission_unique_name la permission en question
	 */
	public function getGroupsWherePermission($permission_unique_name)
	{
		return GroupQuery::create()
			->joinWith('GroupType')
			->add(GroupPeer::ID, $this->getGroupIdsWherePermission($permission_unique_name), \Criteria::IN)
		->find();
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
	 * @return array<Group> 
	 */
	public function getGroupsUserBelong()
	{
		if (!isset($this->groups)) {
			$this->groups = GroupQuery::create()
				->joinWith('GroupType')
			->findPks($this->getGroupsIdsUserBelong());
		}
		
		return $this->groups;
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
	
	//////////////    FONCTIONS LIEES AUX UTILISATEURS PARENT     \\\\\\\\\\\\\\\\\
	public function getUserParent(User $user)
	{
		if (null == $user) {
			throw new InvalidArgumentException('Parameter `user` given must be != null');
		}
		
		$pupilParentLinks = PupilParentLinkQuery::create()
			->add(PupilParentLinkPeer::USER_PUPIL_ID, $user->getId())
		->find();
		
		// TODO: on renvoi tous les parents ou juste un seul parent ?
		return 0 < count($pupilParentLinks)? $pupilParentLinks[0]->getUserRelatedByUserParentId() : null;
	}

	//////////////    FONCTIONS LIEES AUX ROLES     \\\\\\\\\\\\\\\\\
	
	/**
	 * Cette méthode récupère les droits de l'utilisateur courant pour déterminer quel est son rôle principal
	 * (il faut donc faire un setUser() avant d'utiliser cette méthode)
	 * 
	 * @return string la chaîne de caractère qui correspond au rôle principal de l'utilisateur courant
	 */
	public function getMainRole()
	{
		$rights = $this->getRights();
		$roles = array();
		foreach ($rights as $rightsInGroup) {
			$roles = array_merge($roles, $rightsInGroup['roles']);
		}
		
		if (0 >= count($roles)) {
			throw new \RuntimeException('User ' . $this->user->getId() . ' does not have any role in any group!');
		}
		
		return strtolower($this->roleManager->getGroupTypeRoleFromId(min($roles))->getType());
	}
	
	//////////////    FONCTIONS LIEES AUX RESSOURCES     \\\\\\\\\\\\\\\\\
	
	/*
	 * Renvoie le stockage autorisé pour l'utilisateur
	 */
	public function getRessourceAllowedSize()
	{
		$authorisedValue = 0;
		foreach($this->getGroupsWherePermission('RESOURCE_MY_RESOURCES') as $group){
			$authorisedValue += $group->getAttribute("RESOURCE_QUOTA_USER");
		}
		return $authorisedValue;
	}
	/*
	 * Renvoie le ratio d'utilisation pour les ressources de l'utilisateur
	 */
	public function getResourceUsageRatio(){
		return round($this->getUser()->getResourceUsedSize() / $this->getRessourceAllowedSize(),2) * 100;
	}
	
	public function getAvailableSize()
	{
		return $this->getRessourceAllowedSize() - $this->getUser()->getResourceUsedSize();
	}
	
	
	
	
	/**
	 * Permet de réinitialiser le mot de passe de l'utilisateur passé en paramètre et retourne l'objet utilisateur
	 * avec l'attribut password setté
	 * 
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * @return \BNS\App\CoreBundle\Model\User
	 */
	public function resetUserPassword(User $user)
	{
	   $route = array(
		 'username' => $user->getLogin(),
	   );

	   $response = $this->api->send('reset_user_password', array(
		   'route' => $route
		));
	   
	   $user->setPassword($response['plain_password']);
	   
	   $this->bns_mailer->sendUser('RESET_PASSWORD_SUCCESS', array(
			'first_name'		=> $user->getFirstName(),
			'login'				=> $user->getLogin(),
			'plain_password'	=> $user->getPassword()
		), $user);
	   
	   return $user;
	}
	
	/**
	 * @return string The password confirmation token to send in the e-mail
	 */
	public function requestConfirmationResetPassword()
	{
		$user = $this->getUser();
		$confirmationToken = md5($user->getUsername() . time() . 'bns3resetpassword' . $user->getEmail());
		
		$this->api->send('flag_reset_user_password', array(
			'route'		=> array(
				'username' => $user->getUsername()
			),
			'values'	=> array(
				'confirmation_token' => $confirmationToken
			)
		));
		
		return $confirmationToken;
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

		$rowCount = $successInsertCount = 0;
		$format = $format == 0 ? 'BNSFormat' : 'BaseElevesFormat';
		
		if (($handle = fopen($tmpDir . $fileTmpName, 'r')) !== false) {
			$con = \Propel::getConnection($this->container->getParameter('propel.dbal.default_connection'));
			\Propel::setForceMasterConnection(true);

			try {
				$con->beginTransaction();

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
					$user = null;
					try {
						$user = $this->createUser(array(
							'last_name'		=> utf8_encode(trim($data[0])),
							'first_name'	=> utf8_encode(trim($data[1])),
							'birthday'		=> String::convertDateFormat($data[2]),
							'gender'		=> $data[3],
							'lang'			=> BNSAccess::getLocale()
						));
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
					
					$successInsertCount += $success === true ? 1 : 0;
				}

				$con->commit();
			}
			catch (\Exception $e)
			{
				$con->rollBack();
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
			'success_insertion_count' => $successInsertCount
		);
	}
	
	private function createUserFromCFormat(array $data, Group $group, GroupType $role = null)
	{
		
	}
	
	private function createUserFromBaseElevesFormat(array $data, Group $group, GroupType $role = null)
	{/*
		$success = true;
		$user = null;
		try {
			$user = $this->createUser(array(
				'last_name'		=> utf8_encode(trim($data[0])),
				'first_name'	=> utf8_encode(trim($data[2])),
				'birthday'		=> strtotime($data[3]),
				//'gender'		=> ,
				'lang'			=> BNSAccess::getLocale()
			));
		}
		catch(HttpException $e) {
			$success = false;
		}
		
		if (null !== $user) {
			$this->linkUserWithGroup($user, $group, $role);
		}
		
		return $success;*/
	}
	
	private function linkUserWithGroup(User $user, Group $group, GroupType $role = null)
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
	
	/**
	 * @param string $username
	 * 
	 * @return array
	 */
	private function getUserFromCentral($username)
	{
		return $this->api->send('user_read',
			array(
				'route' =>  array(
					'username' => $username
				)
			)
		);
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
		$userData = $this->getUserFromCentral($this->getUser()->getUsername());
		
		return !isset($userData['password_created_at']);
	}
	
	/**
	 * @params string $email
	 * 
	 * @return User
	 */
	public function getUserByEmail($email)
	{
		// FIXME Trouver un moyen de clear le cache redis correctement.
		$userData = $this->api->send('user_read_by_email',
			array(
				'route' =>  array(
					'email' => urlencode($email)
				),
			),
			false
		);
		
		return $this->hydrateUser($userData);
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
		
		if (isset($userData['password_requested_at'])) {
			$user->setPasswordRequestedAt($userData['password_requested_at']);
		}
		if (isset($userData['password_created_at'])) {
			$user->setPasswordCreatedAt($userData['password_created_at']);
		}
		
		return $user;
	}
	
	/**
	 * Launched when user logging
	 * 
	 * @return false|string Url to redirect the user
	 */
	public function onLogon()
	{
		$this->setUser(BNSAccess::getUser());
		
		// Vérification des invitations
		$invitations = $this->getInvitations();
		if (isset($invitations[0])) {
			return $this->container->get('router')->generate('user_invitations');
		}
		
		// Need to generate new password
		if ($this->isRequestPassword()) {
			return $this->container->get('router')->generate('user_password');
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
		
		// Update last connection to now
		$this->getUser()->updateLastConnection();
		
		// Catch the user target path redirection
		if (BNSAccess::getSession()->has('_bns.target_path', false)) {
			$redirect = BNSAccess::getSession()->get('_bns.target_path');
			BNSAccess::getSession()->remove('_bns.target_path');
			
			return $redirect;
		}
		
		return false;
	}
}
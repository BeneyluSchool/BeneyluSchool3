<?php

namespace BNS\App\CoreBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;
use InvalidArgumentException;
use Criteria;

use BNS\App\CoreBundle\Model\om\BaseUser;
use BNS\App\CoreBundle\Model\Profile;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Date\ExtendedDateTime;

/**
 * Skeleton subclass for representing a row from the 'user' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class User extends BaseUser implements UserInterface
{
	const NAME_FORMAT_FIRSTNAME_LASTNAME		= 'f l';
	const NAME_FORMAT_LASTNAME_FIRSTNAME		= 'l f';
	const NAME_FORMAT_CAPLASTNAME_FIRSTNAME		= 'L f';
	const NAME_FORMAT_LASTNAME_CAPFIRSTNAME		= 'l F';
	const NAME_FORMAT_CAPLASTNAME_CAPFIRSTNAME	= 'L F';
	const NAME_FORMAT_CAPFIRSTNAME_CAPLASTNAME	= 'F L';
	
	const BIRTHDAY_FORMAT_FULL					= 'full';
	const BIRTHDAY_FORMAT_MEDIUM				= 'medium';
	const BIRTHDAY_FORMAT_DAY					= 'day';
	const BIRTHDAY_FORMAT_MONTH					= 'month';
	const BIRTHDAY_FORMAT_YEAR					= 'year';
	const BIRTHDAY_FORMAT_TIMESTAMP				= 'timestamp';
	
	/**
	 * @var $notification array<Notification> 
	 */
	private $notifications;
	
	/**
	 * @var $sentNotification array<Notification> 
	 */
	private $sentNotifications;
	
	/**
	 * @var $groups array<Group>
	 */
	private $groups;
	
	/**
	 * @var array<Role> 
	 */
	private $roles;
	
	/**
	 * @var string
	 */
	private $password;
	
	/**
	 * @var string 
	 */
	private $passwordRequestedAt;
	
	/**
	 * @var string
	 */
	private $passwordCreatedAt;
	
	/**
	 * Used by OAuth at the authentication, do NOT edit this method !
	 */
	public function getRoles()
	{
		return array('ROLE_USER');
	}
	
	/**
	 * Renvoie le type d'objet
	 */
	public function getClassName()
	{
		return 'User';
	}
	
	/**
	 * @return array<Role>
	 * 
	 * @throws InvalidArgumentException
	 */
	public function getUserRoles()
	{
		if (!isset($this->roles)) {
			$context = BNSAccess::getContainer()->get('bns.right_manager')->getContext();
			
			if (null == $context) {
				throw new InvalidArgumentException("Can't determine the context here !");
			}
			
			$roleIds = BNSAccess::getContainer()->get('bns.user_manager')->getRolesFromGroup($this, $context['id']);
			
			if (count($roleIds) == 0) {
				$this->roles = array();
			}
			else {
				$this->roles = RoleQuery::create()
					->joinWithI18n(BNSAccess::getLocale())
					->add(RolePeer::ID, $roleIds, Criteria::IN)
				->find();
			}
		}
		
		return $this->roles;
	}
	
	/**
     * Retourne le mot de passe de l'utilisateur; (Est utilisé une seule fois, lors de la création d'un élève
	 * ou d'un parent)
     *
     * @return string le mot de passe
     */
	public function getPassword()
	{
		return $this->password;
	}
	
	/**
	 * Set le mot de passe de l'utilisateur; (Est utilisé une seule fois, lors de la création d'un élève
	 * ou d'un parent)
	 * 
	 * @param string $password
	 */
	public function setPassword($password) 
	{
		$this->password = $password;
	}
	
	/**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt()
	{
		// TODO
	}

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
	{
		return $this->getLogin();
	}

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     *
     * @return void
     */
    public function eraseCredentials()
	{
		// TODO
	}
	
	/**
	 * @return User
	 */
	public function getUser()
	{
		return $this;
	}
	
	/**
	 * @return string The primary key
	 */
	public function getPrimaryKey()
	{
		return $this->getId();
	}
	
	public function getChildren(){
		return UserQuery::create()->childrenFilter($this->getId())->find();
	}
	
	public function getChild(){
		return UserQuery::create()->childrenFilter($this->getId())->findOne();
	}
	
	public function getParents(){
		return UserQuery::create()->parentsFilter($this->getId())->find();
	}
	
	public function getParent(){
		return UserQuery::create()->parentsFilter($this->getId())->findOne();
	}
	
	/**
	 * @param string $format Le format d'affichage du nom complet. Constante NAME_FORMAT_*
	 * 
	 * @return string Nom complet de l'utilisateur
	 */
	public function getFullName($format = null)
	{
		switch ($format)
		{
			case self::NAME_FORMAT_LASTNAME_FIRSTNAME:
				return ucfirst(strtolower($this->getLastName())) . ' ' . ucfirst(strtolower($this->getFirstName()));
		
			case self::NAME_FORMAT_CAPLASTNAME_FIRSTNAME:
				return strtoupper($this->getLastName()) . ' ' . ucfirst(strtolower($this->getFirstName()));
		
			case self::NAME_FORMAT_LASTNAME_CAPFIRSTNAME:
				return ucfirst(strtolower($this->getLastName())) . ' ' . strtoupper($this->getFirstName());
		
			case self::NAME_FORMAT_CAPLASTNAME_CAPFIRSTNAME:
				return strtoupper($this->getLastName()) . ' ' . strtoupper($this->getFirstName());
		
			case self::NAME_FORMAT_CAPFIRSTNAME_CAPLASTNAME:
				return strtoupper($this->getFirstName()) . ' ' . strtoupper($this->getLastName());
		
			default:
				return ucfirst(strtolower($this->getFirstName())) . ' ' . ucfirst(strtolower($this->getLastName()));
		}
	}
	
	/**
	 * @return array<Notification> Les notifications reçues
	 */
	public function getNotifications($criteria = null, PropelPDO $con = null)
	{
		if (!isset($this->notifications))
		{
			$this->notifications = NotificationQuery::create()
				->joinFull()
				->add(NotificationPeer::TARGET_USER_ID, $this->getId())
			->find();
		}
		
		return $this->notifications;
	}
	
	/**
	 * @return array<Notification> Les notifications envoyées
	 */
	public function getSentNotifications()
	{
		if (!isset($this->sentNotifications))
		{
			$this->notifications = NotificationQuery::create()
				->joinFull()
				->add(NotificationPeer::SENDER_USER_ID, $this->getId())
			->find();
		}
		
		return $this->sentNotifications;
	}
	
	/**
	 * @return int L'âge de la personne
	 */
	public function getAge()
	{
		// Si l'utilisateur n'a pas saisi sa date de naissance
		if (null == $this->getBirthday())
		{
			return null;
		}
		
		$now = array();
		$now['year']	= date('Y', time());
		$now['month']	= date('m', time());
		$now['day']		= date('d', time());
		
		$birthday = array();
		$birthday['year']	= date('Y', $this->getBirthday(self::BIRTHDAY_FORMAT_TIMESTAMP));
		$birthday['month']	= date('m', $this->getBirthday(self::BIRTHDAY_FORMAT_TIMESTAMP));
		$birthday['day']	= date('d', $this->getBirthday(self::BIRTHDAY_FORMAT_TIMESTAMP));
		
		$age = $now['year'] - $birthday['year'];
		
		// Si la date d'anniversaire n'est pas encore passée pour l'année en cours
		if ($birthday['month'] > $now['month'] || $birthday['month'] == $now['month'] && $birthday['day'] > $now['day'])
		{
			return $age - 1;
		}
		
		return $age;
	}
	
	/**
	 * @param string $format Constante de User - permet de donner le template de date à retourner
	 * 
	 * @return string Date d'anniversaire
	 * 
	 * @throws \RuntimeException 
	 */
	public function getBirthday($format = null)
	{
		if (null == parent::getBirthday())
		{
			return null;
		}
		if (null == $format)
		{
			return parent::getBirthday();
		}
		if ($format == self::BIRTHDAY_FORMAT_FULL)
		{
			return date('d/m/Y', parent::getBirthday()->getTimestamp());
		}
		if ($format == self::BIRTHDAY_FORMAT_MEDIUM)
		{
			return date('d/m', parent::getBirthday()->getTimestamp());
		}
		if ($format == self::BIRTHDAY_FORMAT_DAY)
		{
			return date('d', parent::getBirthday()->getTimestamp());
		}
		if ($format == self::BIRTHDAY_FORMAT_MONTH)
		{
			return date('m', parent::getBirthday()->getTimestamp());
		}
		if ($format == self::BIRTHDAY_FORMAT_YEAR)
		{
			return date('Y', parent::getBirthday()->getTimestamp());
		}
		if ($format == self::BIRTHDAY_FORMAT_TIMESTAMP)
		{
			return parent::getBirthday()->getTimestamp();
		}
		
		throw new \RuntimeException('Unknown birthday format for value : ' . $format . ' !');
		
		return null;
	}
	
	/**
	 * @return boolean Vrai si la date d'anniversaire est renseignée
	 */
	public function hasBirthday()
	{
		return parent::getBirthday() != null;
	}
	
	/**
	 * @return boolean 
	 */
	public function isExpert()
	{
		return $this->getIsExpert();
	}
	
	/**
	 * @return array<Group> 
	 */
	public function getGroups()
	{
		if (!isset($this->groups)) {
			$this->groups = BNSAccess::getContainer()->get('bns.right_manager')->getGroupsIBelong();
		}
		
		return $this->groups;
	}
	
	/**
	 * @return Profile 
	 */
	public function createProfile()
	{
		// Création du profile
		$profile = new Profile();
		$profile->save();
		
		$this->setProfileId($profile->getId());
		
		return $profile;
	}
	
	/* RESSOURCES */
	/**
	 * 
	 */
	public function createResourceLabelRoot()
	{
		$root = New ResourceLabelUser();
		$root->makeRoot();
		$root->setScopeValue($this->getId());
		$root->setLabel($this->getFullName());
		$root->save();
	}
	
	/**
	 * @param type $with_root
	 * 
	 * @return array<ResourceLabelUser> 
	 */
	public function getRessourceLabels($with_root = true)
	{
		if($with_root){
			return ResourceLabelUserQuery::create()->orderByBranch()->filterByUserId($this->getId())->find();
		}else{
			$labels = ResourceLabelUserQuery::create()->filterByTreeLevel(array('min' => 1))->findTree($this->getId());
			return $labels;
		}
	}
	
	/**
	 * @return array<ResourceLabelUser> 
	 */
	public function getResourceLabelRoot()
	{
		return ResourceLabelUserQuery::create()->findRoot($this->getId());
	}
	
	public function addResourceSize($value)
	{
		$this->setResourceUsedSize($this->getResourceUsedSize() + $value);
		$this->save();
	}
	
	public function deleteResourceSize($value)
	{
		$this->setResourceUsedSize($this->getResourceUsedSize() - $value);
		$this->save();
	}
	
	/*
	 * Fonctions liées à l'avatar
	 */
	public function hasAvatar()
	{
		$hasAvatar = false;
		if (null != $this->getProfile()) {
			$hasAvatar = $this->getProfile()->getAvatarId() != null? true : false;
		}
		
		return $hasAvatar; 
	}
	
	public function getAvatarResource()
	{
		return $this->getProfile()->getResource();
	}
	
	public function updateLastConnection()
	{
		$this->setLastConnection(time());
		$this->save();
	}

	/**
	 * @param string $time
	 */
	public function setPasswordRequestedAt($time)
	{
		$this->passwordRequestedAt = new ExtendedDateTime($time);
	}
	
	/**
	 * @return ExtendedDateTime
	 */
	public function getPasswordRequestedAt()
	{
		return $this->passwordRequestedAt;
	}
	
	/**
	 * @param string $time
	 */
	public function setPasswordCreatedAt($time)
	{
		$this->passwordCreatedAt = new ExtendedDateTime($time);
	}
	
	/**
	 * @return ExtendedDateTime
	 */
	public function getPasswordCreatedAt()
	{
		return $this->passwordCreatedAt;
	}
}
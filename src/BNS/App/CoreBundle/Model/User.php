<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\GroupBundle\Model\CeriseLinkQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MigrationBundle\Model\MigrationIconitoQuery;
use BNS\App\PaasBundle\Client\PaasClientInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use InvalidArgumentException;
use Criteria;

use BNS\App\CoreBundle\Model\om\BaseUser;
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
class User extends BaseUser implements UserInterface, PaasClientInterface, \Serializable
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
    const BIRTHDAY_FORMAT_ISO                   = 'iso';

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
	 * @var boolean
	 */
	private $isEnabled;

	/**
	 * @var array<Boolean, Boolean>
	 */
	private $isChildOf = array();

	/**
	 * @var array<Boolean, Boolean>
	 */
	private $isParentOf = array();

	/**
	 * @var array<User>
	 */
	private $children;

	/**
	 * @var array<User>
	 */
	private $parents;

	/**
	 * @var boolean
	 */
	private $isReadOnly = false;

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
     * @deprecated to be removed
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
                // WTF invalid class
				$this->roles = RoleQuery::create()
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

    public function getNotificationEmail()
    {
        return $this->getEmailPrivate() ? $this->getEmailPrivate() : $this->getEmail();
    }

    public function isChild()
    {
        //WARNING : ce n'est pas une méthode 100% safe car elle ne s'appuie que sur le HiGH Role Id en dur, bien plus performant
        return $this->getHighRoleId() == 8;
    }

    public function isStillBaseParentName()
    {
        return substr($this->first_name,0,9) == "Parent de";
    }

    public function isAdult()
    {
        return !$this->isChild();
    }

	/**
	 * @param \BNS\App\ResourceBundle\Model\ResourceLabelUser $user
	 *
	 * @return boolean
	 */
	public function isChildOf(User $user)
	{
		if (!isset($this->isChildOf[$user->getId()])) {
			$this->isChildOf[$user->getId()] = UserQuery::create()
				->childrenFilter($user->getId())
				->filterById($this->getId())
			->count() > 0;
		}

		return $this->isChildOf[$user->getId()];
	}

	/**
	 * @param \BNS\App\ResourceBundle\Model\ResourceLabelUser $user
	 *
	 * @return boolean
	 */
	public function isParentOf(User $user)
	{
		if (!isset($this->isParentOf[$user->getId()])) {
			$this->isParentOf[$user->getId()] = UserQuery::create()
				->parentsFilter($user->getId())
				->filterById($this->getId())
			->count() > 0;
		}

		return $this->isParentOf[$user->getId()];
	}

	/**
	 * @param string $format Le format d'affichage du nom complet. Constante NAME_FORMAT_*
	 *
	 * @return string Nom complet de l'utilisateur
	 */
	public function getFullName($format = null, TranslatorInterface $translator = null)
	{
        if ( null === $translator){
            $translator = BNSAccess::getContainer()->get('translator');
        }

        if($this->isChild() || $this->isStillBaseParentName())
        {
            $first_name = $this->getFirstName();
        }else{
            switch($this->getGender())
            {
                case "M":
                    $first_name = $translator->trans('LABEL_SHORTEN_MISTER', array(), 'CORE');
                    break;
                case "F":
                    $first_name = $translator->trans('LABEL_SHORTEN_MISTRESS', array(), 'CORE');
                    break;
            }
        }





		switch ($format)
		{
			case self::NAME_FORMAT_LASTNAME_FIRSTNAME:
				return $this->getUcFirstPartName($this->getLastName()) . ' ' . $this->getUcFirstPartName($first_name);

			case self::NAME_FORMAT_CAPLASTNAME_FIRSTNAME:
				return strtoupper($this->getLastName()) . ' ' . $this->getUcFirstPartName($first_name);

			case self::NAME_FORMAT_LASTNAME_CAPFIRSTNAME:
				return $this->getUcFirstPartName($this->getLastName()) . ' ' . strtoupper($first_name);

			case self::NAME_FORMAT_CAPLASTNAME_CAPFIRSTNAME:
				return strtoupper($this->getLastName()) . ' ' . strtoupper($first_name);

			case self::NAME_FORMAT_CAPFIRSTNAME_CAPLASTNAME:
				return strtoupper($first_name) . ' ' . strtoupper($this->getLastName());

			default:
				return $this->getUcFirstPartName($first_name) . ' ' . $this->getUcFirstPartName($this->getLastName());
		}
	}

    public function getFirstAndLastName()
    {
        return ucfirst(strtolower($this->getFirstName())) . ' ' . strtoupper($this->getLastName());
    }

    public function getFirstName()
    {
        return $this->getUcFirstPartName(parent::getFirstName());
    }

    public function getLastName()
    {
        return $this->getUcFirstPartName(parent::getLastName());
    }

    public function displayFirstName(TranslatorInterface $translator = null)
    {
        if ( null === $translator){
            $translator = BNSAccess::getContainer()->get('translator');
        }

        if($this->isChild() || $this->isStillBaseParentName())
        {
            return $this->getFirstName();
        }else{
            switch($this->getGender())
            {
                case "M":
                    return $translator->trans('LABEL_SHORTEN_MISTER', array(), 'CORE');
                case "F":
                    return $translator->trans('LABEL_SHORTEN_MISTRESS', array(), 'CORE');
            }
        }
    }

    public function displayLastName()
    {
        if($this->isChild() || $this->isStillBaseParentName())
        {
            return $this->getLastName();
        }else{
            return "";
        }
    }

    public function isMale()
    {
        return $this->getGender() == "M";
    }

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	private function getUcFirstPartName($name)
	{
		if (!preg_match('# #', $name)) {
			return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}

		if ('Parents de' == $name) {
			return $name;
		}

		$nameParts = explode(' ', $name);
		foreach ($nameParts as &$namePart) {
			$namePart = mb_convert_case(mb_strtolower($namePart, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}

		return implode(' ', $nameParts);
	}

	/**
	 * @return array<Notification> Les notifications reçues
	 */
	public function getNotifications($criteria = null, \PropelPDO $con = null)
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

        if ($format == self::BIRTHDAY_FORMAT_ISO)
        {
            return date('Y-m-d', parent::getBirthday()->getTimestamp());
        }

		throw new \RuntimeException('Unknown birthday format for value : ' . $format . ' !');

		return null;
	}

	public function getBirthdayDate()
    {
        return $this->getBirthday(self::BIRTHDAY_FORMAT_ISO);
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
	 * @return Group[]
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
		$root = New MediaFolderUser();
		$root->makeRoot();
		$root->setScopeValue($this->getId());
		$root->setLabel($this->getFirstName() . ' ' . $this->getLastName());
        $root->setSlug('utilisateur-' . $this->getId());
		$root->save();
	}

    public function getMediaFolder()
    {
        return MediaFolderUserQuery::create()->filterByUserId($this->getId())->findRoot($this->getId());
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
	 * @return MediaFolderUser
	 */
	public function getMediaFolderRoot()
	{
		return MediaFolderUserQuery::create()->findRoot($this->getId());
	}

	/**
	 * @return array<ResourceLabelUser>
	 */
	public function getResourceLabelByLevel($level = 1)
	{
		return ResourceLabelUserQuery::create()->filterByUserId($this->getId())->filterByTreeLevel($level)->find();
	}

	public function addResourceSize($value)
	{
		$this->reload();
		$this->setResourceUsedSize($this->getResourceUsedSize() + $value);
		$this->save();

	}

	public function deleteResourceSize($value)
	{
		$this->reload();
		$this->setResourceUsedSize(max(0,$this->getResourceUsedSize() - $value));
		$this->save();
	}

	/*
	 * Fonctions liées à l'avatar
	 */
	public function hasAvatar()
	{
		$hasAvatar = false;
		if (null != $this->getProfile()) {
			$hasAvatar = $this->getProfile()->getAvatarId() != null ? true : false;
		}

		return $hasAvatar;
	}

	public function getAvatarResource()
	{
		return $this->getProfile()->getResource();
	}

	public function getAvatarUrl()
	{
		return BNSAccess::getContainer()->get('twig.extension.resource')->getAvatar($this);
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

	/**
	 * @return boolean Injected by BNSGroupManager
	 */
	public function isEnabled()
	{
		return $this->isEnabled;
	}

	/**
	 * @param boolean $boolean
	 */
	public function setIsEnabled($boolean)
	{
		$this->isEnabled = $boolean;
	}

    /**
     * Archive de l'utilisateur : il ne pourra plus se connecter et ne sera plus remonté dans les listes d'utilisateurs
     * On utilisateur archivé est supprimé au bout d'un an par défaut
     */
    public function archive($expire = 31536000)
    {
        $this->setArchived(true);
        $this->setArchiveDate(time());
        $this->setExpiresAt(time() + $expire);
        $this->save();
    }

    public function restore()
    {
        $this->setArchived(false);
        $this->setArchiveDate(null);
        $this->setExpiresAt(null);
        $this->save();
    }

    public function isArchived()
    {
        return $this->getArchived();
    }

    /**
	 * @param array<User> $children
	 */
	public function setChildren($children)
	{
		$this->children = $children;
	}

	/**
	 * @return array|User[]
	 */
	public function getChildren()
	{
		if(!isset($this->children)){
			$userManager = BNSAccess::getContainer()->get('bns.user_manager');
			$this->children = $userManager->getUserChildren($this);
		}
		return $this->children;
	}

    /**
     * @return array|User[]
     */
    public function getActiveChildren()
    {
        $children = [];
        foreach ($this->getChildren() as $child) {
            if (!$child->isArchived()) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @param array $pupilIds
     * @return $this|null
     */
    public function getFirstActiveChildInList(array $pupilIds)
    {
        return $this->activeChildren = UserQuery::create()
            ->filterById($pupilIds, \Criteria::IN)
            ->filterByArchived(false)
            ->usePupilParentLinkRelatedByUserPupilIdQuery()
                ->filterByUserParentId($this->getId())
            ->endUse()
            ->findOne()
        ;
    }

	/**
	 * @return array|User[]
	 */
	public function getParents()
	{
        $pupilParentLinks = PupilParentLinkQuery::create('p')
                ->innerJoinUserRelatedByUserParentId('parent_user')->with('parent_user')
                ->innerJoinUserRelatedByUserPupilId('pupil_user')->with('pupil_user')
            ->where('p.UserPupilId = ?', $this->getId())
            ->find();
        $parents = array();
        foreach ($pupilParentLinks as $parent) {
            $parents[] = $parent->getUserRelatedByUserParentId();
        }
        return $parents;
	}

	/**
	 * @param boolean $boolean
	 */
	public function setIsReadOnly($boolean)
	{
		$this->isReadOnly = $boolean;
	}

	/**
	 * @param \PropelPDO $con
	 *
	 * @return int affected rows
	 */
	public function save(\PropelPDO $con = null)
	{
		// When user is on read only mode, we can't save him, but it doesn't throw exception : silent save
		if ($this->isReadOnly) {
			return 0;
		}

		return parent::save($con);
	}

    public function isLoginUsed($context)
    {
        if(isset($this->oldLogin))
        {
            if($this->login != $this->oldLogin)
            {
                if (null != $this->login && '' != $this->login && BNSAccess::getContainer()->get('bns.user_manager')->getLoginExists($this->login)) {
                    $context->addViolationAt('login', "L'identifiant saisi est déjà utilisé. Veuillez en saisir un autre", array(), null);
                }
            }
        }
    }

    public function setOldLogin($login)
    {
        $this->oldLogin = $login;
    }

    /**
     * Remet à zéro le token de première connexion
     */
    public function resetConnexionToken()
    {
        $this->setConnexionToken(null);
        $this->save();
    }

    /**
     * Action de confirmation d'email, enlève le token de confirmation d'email et valide l'email en base
     */
    public function confirmEmail()
    {
        $this->setEmailValidated(true);
        $this->save();
    }

    //Match ID BNS / ICONITO / Cerise

    public function getCeriseId()
    {
        $originKey = MigrationIconitoQuery::create()->filterByBnsKey($this->getId())->findOne();
        if(!$originKey)
        {
            return $this->getId();
        }
        $ceriseLink = CeriseLinkQuery::create()->filterByUserId($originKey->getOriginKey())->findOne();
        if(!$ceriseLink)
        {
            return $this->getId();
        }else{
            return $ceriseLink->getBuId();
        }
    }

    public function getPaasType()
    {
        return 'USER';
    }

    public function getPaasIdentifier()
    {
        return $this->getId();
    }

    public function hasRegistered()
    {
        // 0 if user was created before the new registration process, null if he has completed the process
        return !$this->getRegistrationStep();
    }

    public function serialize()
    {
        return serialize([
            $this->id,
            $this->login,
        ]);
    }

    public function unserialize($serialized)
    {
        list(
            $this->id,
            $this->login
            ) = unserialize($serialized)
        ;
    }

    public function getAssistants()
    {
        $assistants = UserQuery::create()
            ->usePupilAssistantLinkRelatedByAssistantIdQuery()
                ->filterByPupilId($this->getId())
            ->endUse()
            ->find();

        return $assistants;
    }

}

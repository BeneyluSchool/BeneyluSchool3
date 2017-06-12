<?php

namespace BNS\App\ResourceBundle\Right;

use \BNS\App\CoreBundle\Access\BNSAccess;
use \BNS\App\CoreBundle\Model\GroupQuery;
use \BNS\App\ResourceBundle\Model\Resource;
use \BNS\App\ResourceBundle\Model\ResourceInternetSearch;
use \BNS\App\ResourceBundle\Model\ResourceJoinObjectQuery;
use \BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use \BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use \BNS\App\ResourceBundle\Model\ResourceLabelUser;
use \BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use \BNS\App\ResourceBundle\Model\ResourceLinkUserQuery;
use \BNS\App\ResourceBundle\Model\ResourceQuery;
use \BNS\App\ResourceBundle\Model\ResourceWhiteListQuery;
use \Criteria;
use \Exception;



/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des droits des Ressources
 */

class BNSResourceRightManager
{
	/**
	 * @var \BNS\App\ResourceBundle\BNSResourceManager
	 */
	protected $resource_manager;


	/**
	 * @param \BNS\App\ResourceBundle\BNSResourceManager $resource_manager
	 */
	public function __construct($resource_manager)
	{
		$this->resource_manager = $resource_manager;
    }

	//////////////////////     Fonctions racourcies     \\\\\\\\\\\\\\\\\\\\\\\\

	public function getResourceManager()
	{
		return $this->resource_manager;
	}

	public function getUserManager()
	{
		return $this->resource_manager->getUserManager();
	}

	public function getGroupManager()
	{
		return $this->resource_manager->getGroupManager();
	}

	/**
	 * @param User $user
	 *
	 * @return \BNS\App\ResourceBundle\Right\BNSResourceRightManager
	 */
	public function setUser($user)
	{
		$this->getResourceManager()->setUser($user);

		return $this;
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return $this->getResourceManager()->getUser();
	}

	/**
	 * Renvoie le right manager pour les vérifications de droits
	 * N'est pas instancié avec le système de service container car en scope Request => conflits
	 * @return BNSRightManager
	 */
	public function getRightManager()
	{
		return BNSAccess::getContainer()->get('bns.right_manager');
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il administrer l'utilisateur en paramètre ?
	 *
	 * @param int $userId
	 *
	 * @return boolean
	 */
	public function canManageUser($userId)
	{
		$currentUser = $this->getUser();
		if ($currentUser->getId() == $userId) {
			return true;
		}

		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		$adminUsersGroups = $userManager->getGroupsWherePermission('RESOURCE_USERS_ADMINISTRATION');

		foreach ($adminUsersGroups as $group) {
			$this->getGroupManager()->setGroup($group);

			if (in_array($userId, $this->getGroupManager()->getUsersIds())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il administrer le groupe en paramètre ?
	 * @param int $groupId
	 * @return boolean
	 */
	public function canManageGroup($groupId)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		return in_array($groupId,$userManager->getGroupIdsWherePermission("RESOURCE_ADMINISTRATION"));
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il lire le groupe en paramètre ?
	 *
	 * @param int $groupId
	 *
	 * @return boolean
	 */
	public function canReadGroup($groupId)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);

		return in_array($groupId, $userManager->getGroupIdsWherePermission('RESOURCE_ACCESS'));
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il lire l'utilisateur en paramètre ?
	 *
	 * @param int $userId
	 *
	 * @return boolean
	 */
	public function canReadUser($userId)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);

        if ($currentUser->getId() == $userId) {
            return $this->getRightManager()->hasRightSomeWhere('RESOURCE_ACCESS');
        }

		$groupManager = $this->getGroupManager();
		foreach ($userManager->getGroupsWherePermission('RESOURCE_ACCESS') as $group) {
			$groupManager->setGroup($group);
			if (in_array($userId, $groupManager->getUsersIds())) {
				return true;
			}
		}

		return false;
	}

	//////////////  FONCTIONS LIEES AUX LIBELLES  \\\\\\\\\\\\\\\\

	/**
	 * Vérification des droits du User en cours sur un label (utilisé pour les droits en CRUD)
	 *
	 * @param $label (ResourceLabelUser OU ResourceLabelGroup)
	 *
	 * @return boolean
	 */
	public function canManageLabel($label)
	{
		if ($label->getType() == 'user') {
			return $this->canManageUser($label->getUserId());
		}
		elseif ($label->getType() == 'group') {
			return $this->canManageGroup($label->getGroupId());
		}

		return false;
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il lire $label
	 * @param $label (ResourceLabelUser OU ResourceLabelGroup)
	 * @return boolean
	 */
	public function canReadLabel($label)
	{
		// Selon type
		if ($label->getType() == 'user') {
			return $this->canReadUser($label->getUserId());
		}
		elseif ($label->getType() == 'group') {
			return $this->canReadGroup($label->getGroupId());
		}

		return false;
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il créer dans $parent un label
	 * @param $parent (ResourceLabelUser OU ResourceLabelGroup)
	 * @return boolean
	 */
	public function canCreateLabel($parent)
	{
		return $this->canManageLabel($parent);
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il éditer $label
	 * @param $label (ResourceLabelUser OU ResourceLabelGroup)
	 * @return boolean
	 */
	public function canUpdateLabel($label)
	{
		return $this->canManageLabel($label);
	}

	/**
	 * L'utilisateur set dans le resourceManager peut il supprimer $label
	 * @param $label (ResourceLabelUser OU ResourceLabelGroup)
	 * @return boolean
	 */
	public function canDeleteLabel($label)
	{
		return $this->canManageLabel($label);
	}

	//////////////  FONCTIONS LIEES AUX RESSOURCES  \\\\\\\\\\\\\\\\

	/**
	 * Est il l'auteur de la ressource
	 * @param type Resource $resource
	 * @return boolean
	 */
	public function isAuthor(Resource $resource)
	{
		return $resource->getUserId() == $this->getUser()->getId();
	}

	/**
	 * L'utilisateur peut il créer une resource dans ce label ?
	 * @param $label
	 * @return boolean
	 */
	public function canCreateResource($label)
	{
		return $label == null ? false : $this->canManageLabel($label);
	}

	/**
	 * L'utilisateur peut il lire la ressource ?
	 *
	 * @param Resource $resource
	 *
	 * @return boolean
	 */
    public function canReadResource(Resource $resource, $isVisualise = false)
    {
        $currentUser = $this->getUser();
        $userManager = $this->getUserManager();
        $userManager->setUser($currentUser);

        // Cas le plus simple
        if ($this->isAuthor($resource)) {
            return true;
        }

        $accessGroupIds = array_merge($userManager->getGroupIdsWherePermission('RESOURCE_USERS_ADMINISTRATION'),$userManager->getGroupIdsWherePermission('RESOURCE_ADMINISTRATION'));

        // Si visualisation ou si publique
        if ($isVisualise || !$resource->isPrivate()) {
            $accessGroupIds = array_merge($accessGroupIds,$userManager->getGroupIdsWherePermission('RESOURCE_ACCESS'));
        }

        // TODO test me
        $attachmentLinks = ResourceJoinObjectQuery::create('rjo')
            ->join('rjo.ResourceJoinObjectLinks rjol')
            ->where('rjo.ResourceId = ?', $resource->getId())
            ->where('rjol.userId = ?', $currentUser->getId())
            ->orWhere('rjol.groupId IN ?', $accessGroupIds)
            ->count();

        if ($attachmentLinks > 0) {
            return true;
        }

        $resourceGroupIds = $this->resource_manager->getResourceGroupIds($resource);
        foreach ($accessGroupIds as $groupId) {
            if (in_array($groupId, $resourceGroupIds)) {
                return true;
            }
        }

        return false;
    }

	/**
	 * L'utilisateur peut il manager la ressource ?
	 *
	 * @param Resource				  $resource
	 * @param null|ResourceLabelGroup $label	Si null, c'est un label User donc inutile de le lier
	 *
	 * @return boolean
	 */
	public function canManageResource(Resource $resource, $label = null)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);

		// Est l'auteur
		if ($this->isAuthor($resource)) {
			return true;
		}

		// Peut gérer l'utilisateur et donc ses publications
		if ($this->canManageUser($resource->getUserId())) {
			return true;
		}

		// Peut gérer le groupe
		if (null != $label && $label->getType() == 'group') {
			return $userManager->hasRight('RESOURCE_ADMINISTRATION', $label->getGroup()->getId());
		}

		return false;
	}

	/**
	 * L'utilisateur peut-il agir sur un document sélectionnée
	 *
	 * @param Resource		$resource
	 * @param int			$userId
	 * @param ResourceLabel $label
	 * @param null|boolean	$canManage
	 *
	 * @return boolean
	 */
	public function canManageResourceFromSelection(Resource $resource, $userId, $label, $canManage = null)
	{
		// Tous les droits, on accepte
		if (null == $canManage && $this->canManageResource($resource, $label) || $canManage === true) {
			return true;
		}

		if ($label->getType() == 'user') {
			$link = ResourceLinkUserQuery::create('rlu')
				->join('rlu.ResourceLabelUser rlau')
				->join('rlu.Resource r')
				->where('r.Id = ?', $resource->getId())
				->where('rlau.UserId = ?', $userId)
				->where('rlau.Id = ?', $label->getId())
			->findOne();

			return null != $link;
		}

		return false;
	}

	/**
	 * L'utilisateur peut il mettre à jour la ressource
	 *
	 * @param Resource $resource
	 *
	 * @return boolean
	 */
	public function canUpdateResource($resource)
	{
		return $this->canManageResource($resource);
	}

	/**
	 * L'utilisateur peut il supprimer la ressource ?
	 *
	 * @param Resource		$resource
	 * @param int			$userId
	 * @param ResourceLabel $label
	 * @param null|boolean	$canManage
	 *
	 * @return boolean
	 */
	public function canDeleteResource(Resource $resource, $userId, $label, $canManage = null)
	{
		// Tous les droits, on accepte
		if (null == $canManage && $this->canManageResource($resource, $label) || $canManage === true) {
			return true;
		}

		if ($label->getType() == 'user') {
			$link = ResourceLinkUserQuery::create('rlu')
				->join('rlu.ResourceLabelUser rlau')
				->join('rlu.Resource r')
				->where('r.Id = ?', $resource->getId())
				->where('rlau.UserId = ?', $userId)
				->where('rlau.Id = ?', $label->getId())
			->findOne();

			return null != $link;
		}

		return false;
	}

	/**
	 * Peut il tagger la ressource ?
	 * @param Resource $resource
	 * @return boolean
	 */
	public function canAssign(Resource $resource)
	{
		return $this->canRead($resource);
	}

	/**
	 * Peut il uploader une ressource ? (vérification des droits de stockage)
	 * @param Resource $size
	 */
	public function canUpload($label,$size){
		if($this->canCreateResource($label)){
			if($label->getType() == 'user'){
				$labeLuser = $label->getUser();
				$userManager = $this->getUserManager();
				$userManager->setUser($labeLuser);
				if($userManager->getAvailableSize() >= $size){
					return true;
				}
			}elseif($label->getType() == 'group'){
				$labelGroup = $label->getGroup();
				$groupManager = $this->getGroupManager();
				$groupManager->setGroup($labelGroup);
				if($groupManager->getResourceAvailableSize() >= $size){
					return true;
				}
			}
		}else{
			return false;
		}
	}
}
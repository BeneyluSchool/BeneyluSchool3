<?php

namespace BNS\App\ResourceBundle\Right;

use \Exception;
use \Criteria;

use BNS\App\ResourceBundle\Model\ResourceQuery,
	BNS\App\ResourceBundle\Model\Resource,
	BNS\App\ResourceBundle\Model\ResourceWhiteListQuery,
	BNS\App\ResourceBundle\Model\ResourceJoinObjectQuery,
	BNS\App\CoreBundle\Model\GroupQuery,
	BNS\App\ResourceBundle\Model\ResourceInternetSearch,
    BNS\App\CoreBundle\Access\BNSAccess;



/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des droits des Ressources
 */

class BNSResourceRightManager
{	
	
	protected $resource_manager;
		
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
	
	public function setUser($user)
	{
		$this->getResourceManager()->setUser($user);
	}
	
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
	 * @param int $userId
	 * @return boolean
	 */
	public function canManageUser($userId)
	{
		$currentUser = $this->getUser();
		if($currentUser->getId() == $userId){
			return true;
		}
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		$adminUsersGroups = $userManager->getGroupsWherePermission("RESOURCE_USERS_ADMINISTRATION");
		foreach($adminUsersGroups as $group){
			$this->getGroupManager()->setGroup($group);
			if(in_array($userId,$this->getGroupManager()->getUsersIds())){
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
	 * @param int $groupId
	 * @return boolean
	 */
	public function canReadGroup($groupId)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		return in_array($groupId,$userManager->getGroupIdsWherePermission("RESOURCE_ACCESS"));
	}
	
	/**
	 * L'utilisateur set dans le resourceManager peut il lire l'utilisateur en paramètre ?
	 * @param int $userId
	 * @return boolean
	 */
	public function canReadUser($userId)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$groupManager = $this->getGroupManager();
		$userManager->setUser($currentUser);
		foreach($userManager->getGroupsWherePermission("RESOURCE_ACCESS") as $group){
			$groupManager->setGroup($group);
			if(in_array($userId,$groupManager->getUsersIds())){
				return true;
			}
		}
		return false;
	}
	
	//////////////  FONCTIONS LIEES AUX LIBELLES  \\\\\\\\\\\\\\\\
	
	/**
	 * Vérification des droits du User en cours sur un label (utilisé pour les droits en CRUD)
	 * @param $label (ResourceLabelUser OU ResourceLabelGroup)
	 * @return boolean
	 */
	public function canManageLabel($label)
	{
		//Selon type
		$type = $label->getType();
		if($type == 'user'){
			return $this->canManageUser($label->getUserId());
		}elseif($type == 'group'){
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
		//Selon type
		$type = $label->getType();
		if($type == 'user'){
			return $this->canReadUser($label->getUserId());
		}elseif($type == 'group'){
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
	 * @param Resource $resource
	 * @return boolean
	 */
	public function canReadResource(Resource $resource)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		//Cas le plus simple
		if($this->isAuthor($resource)){
			return true;
		}
		$userAccessGroupIds = $userManager->getGroupIdsWherePermission("RESOURCE_ACCESS");
		/*
		 * Pour le READ : est lisible si la ressource est linkée à un label, dans un group dans lequel j'ai le droit RESOURCE_ACCESS
		 */
		$resourceLinkedGroups = $resource->getResourceLinkGroups();
		foreach($resourceLinkedGroups as $resourceLinkedGroup){
			if(in_array($resourceLinkedGroup->getGroupId(),$userAccessGroupIds)){
				return true;
			}
		}
		//Sinon est elle liée à un utilisateur sur lequel j'ai les droits de lecture
		$resourceLinkedUsers = $resource->getResourceLinkUsers();
		foreach($resourceLinkedUsers as $resourceLinkedUser){
			if($this->canReadUser($resourceLinkedUser->getResourceLabelUserId())){
				return true;
			}
		}
		//Sinon elle est en pièce jointe
		$attachmentLinks = ResourceJoinObjectQuery::create()
			->filterByResourceId($resource->getId())
			->joinResourceJoinObjectLinks('ResourceJoinObjectLinks')
			->where('ResourceJoinObjectLinks.userId = ?',$currentUser->getId())
			->orWhere('ResourceJoinObjectLinks.groupId IN ?',$userAccessGroupIds)
			->count();
		if($attachmentLinks > 0){
			return true;
		}
		return false;
	}
	
	/**
	 * L'utilisateur peut il manager la ressource ?
	 * @param Resource $resource
	 * @return boolean
	 */
	public function canManageResource(Resource $resource)
	{
		$currentUser = $this->getUser();
		$userManager = $this->getUserManager();
		$userManager->setUser($currentUser);
		
		if($this->isAuthor($resource)){
			return true;
		}
		
		if($this->canManageUser($resource->getUserId())){
			return true;
		}
		
		$strongLinkedGroup = $resource->getStrongLinkedGroup();
		if($strongLinkedGroup){
			$actionUserRights = $userManager->getGroupIdsWherePermission("RESOURCE_ADMINISTRATION");
			if(in_array($strongLinkedGroup->getId(),$actionUserRights)){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * L'utilisateur peut il mettre à jour la ressource
	 * @param type $resource
	 * @return type
	 */
	public function canUpdateResource($resource)
	{
		return $this->canManageResource($resource);
	}
	
	/**
	 * L'utilisateur peut il supprimer la ressource ?
	 * @param Resource $resource
	 * @return type
	 */
	public function canDeleteResource(Resource $resource)
	{
		return $this->canManageResource($resource);
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
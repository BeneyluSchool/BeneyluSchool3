<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResource;
use BNS\App\ResourceBundle\Model\ResourceLinkUser;
use BNS\App\ResourceBundle\Model\RessourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\RessourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceFavoritesQuery;
use BNS\App\ResourceBundle\Model\ResourceFavorites;
use BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\ResourceBundle\BNSResourceManager;

/**
 * Skeleton subclass for representing a row from the 'resource' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class Resource extends BaseResource {

	public function isImage(){
		return $this->getTypeUniqueName() == "IMAGE";
	}
	
	public function isEmbeddedVideo(){
		return $this->getTypeUniqueName() == "EMBEDDED_VIDEO";
	}
	
	public function isLink(){
		return $this->getTypeUniqueName() == "LINK";
	}
	
	/*
	 * Linkage ressource / label_group || label_user
	 */
	public function linkLabel($type = 'group',$object_id = null,$label_id,$is_strong = false){
		$ok = false;
		if($type == 'group'){
			$link = new ResourceLinkGroup();
			$link->setResourceLabelGroupId($label_id);
			$ok = true;
		}elseif($type == 'user'){
			$link = new ResourceLinkUser();
			$link->setResourceLabelUserId($label_id);
			$ok = true;
		}
		if($ok){
			//Strong_link correspond aux attachements forts (pour la taille notammenet et les droits)
			$link->setIsStrongLink($is_strong);
			$link->setResourceId($this->getId());
			$link->save();
		}
		return $link;
	}
	
	/*
	 * Unlinkage ressource / label_group || label_user
	 */
	public function unlinkLabel($type = 'group',$label_id){
		if($type == 'group'){
			$labelQuery = ResourceLinkGroupQuery::create();
			$labelQuery	->filterByResourceLabelGroupId($label_id);
		}elseif($type == 'user'){
			$labelQuery = ResourceLinkUserQuery::create();
			$labelQuery	->filterByResourceLabelUserId($label_id);
		}
		$labelQuery->filterByResourceId($this->getId());
		$labelQuery->delete();
	}
	
	public function getStrongLinkedGroup(){
		return GroupQuery::create()			
			->useResourceLabelGroupQuery()
				->useResourceLinkGroupQuery()
					->filterByIsStrongLink(true)
					->filterByResourceId($this->getId())
				->endUse()
			->endUse()
		->findOne();
	}
	
	public function getStrongLinkedUser(){
		return UserQuery::create()			
			->useResourceLabelUserQuery()
				->useResourceLinkUserQuery()
					->filterByIsStrongLink(true)
					->filterByResourceId($this->getId())
				->endUse()
			->endUse()
		->findOne();
	}
	
	public function getStrongLinkedLabel(){
		$user = ResourceLabelUserQuery::create()			
					->useResourceLinkUserQuery()
						->filterByIsStrongLink(true)
						->filterByResourceId($this->getId())
					->endUse()
			->findOne();
		if(!$user){
			$group = ResourceLabelGroupQuery::create()			
					->useResourceLinkGroupQuery()
						->filterByIsStrongLink(true)
						->filterByResourceId($this->getId())
					->endUse()
				->findOne();
			if(!$group)
				return false;
			else
				return $group;
		}else{
			return $user;
		}
	}
	
	
	
	
	/*
	 * Si stockage local : Date (jour) + user Id + object Id
	 */
	
	public function getFilePathPattern(){
		return $this->getCreatedAt('Y_m_d') . '/' . $this->getUserId() . '/' . $this->getId() . '/';
	}
	
	public function getFilePath($size = null){
		$pattern = $this->getFilePathPattern();
		if($size == null || $size == "original")
			return $pattern . $this->getFilename();
		if($this->isImage() || $this->isEmbeddedVideo() || $this->isLink())
			return $pattern . $size . '/' . $this->getFilename();
		return null;
	}
	
	public function getEncodedContentPath($size)
	{
		return str_replace($this->getFileName(),'base_64_' . $this->getFilename(),$this->getFilePath($size));
	}
	
	public function isValueable(){
		return in_array($this->getTypeUniqueName(),array('LINK'));
	}
	
	
	public function printType(){
		$print = "";
		switch($this->getTypeUniqueName()){
			case "IMAGE":
				$print = "Image";
			break;
			case "EMBEDDED_VIDEO":
				$print = "Vidéo";
			break;
			case "VIDEO":
				$print = "Vidéo";
			break;
			case "FILE":
				$print = "Fichier";
			break;
			case "LINK":
				$print = "Lien";
			break;
			case "AUDIO":
				$print = "Son";
			break;
		}
		return $print;
	}
	
	
	public function getGender(){
		$gender = "m";
		switch($this->getTypeUniqueName()){
			case "IMAGE":
				$gender = "f";
			break;
			case "EMBEDDED_VIDEO":
				$gender = "f";
			break;
			case "VIDEO":
				$gender = "f";
			break;
		}
		return $gender;
	}
	
	public function getEmbeddedVideoCode(){
		if($this->getTypeUniqueName() == "EMBEDDED_VIDEO"){
			
			$value = unserialize($this->getValue());
			$type = $value['type'];
			$id = $value['value'];
			
			switch($type){
				case "youtube":
					return '<iframe width="560" height="315" src="http://www.youtube.com/embed/'. $id .'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
				case "dailymotion":
					return '<iframe width="560" height="315" src="http://www.dailymotion.com/embed/video/'. $id .'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
				case "vimeo":
					return '<iframe width="560" height="315" src="http://player.vimeo.com/video/' . $id . '" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
			}
			
		}else
			Throw new \Exception("This is not an Embedded video");
	}
	
	
	public function getAllLabels(){
		
		$users_links = $this->getResourceLinkUsersJoinResourceLabelUser();
		$groups_links = $this->getResourceLinkGroupsJoinResourceLabelGroup();
		
		$labels = array();
		
		foreach($users_links as $link){
			$labels[] = $link->getResourceLabelUser();
		}
		
		foreach($groups_links as $link){
			$labels[] = $link->getResourceLabelGroup();
		}
		return $labels;
	}
	
	
		
	
	/*
	 * Alterne favori / pas favori pour l'utilisateur
	 * Si forced value => on impose la nouvelle valeur
	 */
	public function toggleFavorite($user_id,$forced_value = null)
	{
		$exists = ResourceFavoritesQuery::create()->filterByUserId($user_id)->filterByResourceId($this->getId())->findOne();
		
		if(($exists == true && $forced_value != true) || $forced_value === false){
			if($exists)
				$exists->delete();
			return false;
		}else{
			$favorite = new ResourceFavorites();
			$favorite->setUserId($user_id);
			$favorite->setResourceId($this->getId());
			$favorite->save();
			return true;
		}
	}
	
	public function isFavorite($user_id)
	{
		$favorite = ResourceFavoritesQuery::create()->filterByUserId($user_id)->filterByResourceId($this->getId())->findOne();
		
		if($favorite)
			return true;
		return false;
	}
	
	public function isActive()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_ACTIVE;
	}
	
	public function isGarbaged()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_GARBAGED;
	}
	
	public function isDeletedForever()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_DELETED;
	}
	
	public function move($from,$to){
		//Recupération et destruction du from
		$fromLink = $this->getLinkFromLabel($from);
		//Création du to
		if($to->getType() == 'user'){
			$toLink = new ResourceLinkUser();
			$toLink->setResourceLabelUserId($to->getId());
		}elseif($to->getType() == 'group'){
			$toLink = new ResourceLinkGroup();
			$toLink->setResourceLabelGroupId($to->getId());
		}
		$toLink->setResourceId($this->getId());
		$fromLink->delete();
		$toLink->save();
		
	}
	
	public function getLinkFromLabel($label){
		if($label->getType() == 'user'){
			$query = ResourceLinkUserQuery::create();
			$query->filterByResourceLabelUserId($label->getId());
		}elseif($label->getType() == 'group'){
			$query = ResourceLinkGroupQuery::create();
			$query->filterByResourceLabelGroupId($label->getId());
		}
		$query->filterByResourceId($this->getId());
		$return = $query->findOne();
		return $return;
	}
	
	
	
	
	
	
} // Resource

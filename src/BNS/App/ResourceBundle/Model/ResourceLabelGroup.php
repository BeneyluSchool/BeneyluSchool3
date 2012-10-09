<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'resource_label_group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLabelGroup extends BaseResourceLabelGroup {

	public function getType(){
		return 'group';
	}
	
	public function isUserFolder(){
		return $this->getIsUserFolder();
	}
	
	public function getToken()
	{
		return $this->getType() . '_' . $this->getGroupId() . '_' . $this->getId();
	}
	
	public function getChildren($criteria = null, PropelPDO $con = null)
	{
		if(!$this->getIsUserFolder()){
			return parent::getChildren();
		}else{
			//Recupération des dossiers users
			$gm = BNSAccess::getContainer()->get('bns.group_manager');
			$gm->setGroup($this->getGroup());
			$users = $gm->getUsersByRoleUniqueName('PUPIL',true);
			
			$usersIds = array();
			foreach($users as $user){
				$usersIds[] = $user->getId();
			}
			return ResourceLabelUserQuery::create()->filterByUserId($usersIds)->findRoots();
		}
	}
	
	/**
	 * Fonctions liées aux droits sur les labels
	 */
	
	public function isChoiceable()
	{
		return !$this->getIsUserFolder();
	}
	
	public function isDeleteable()
	{
		return !$this->getIsUserFolder();
	}
	
	public function isEditable()
	{
		return !$this->getIsUserFolder();
	}
	
	public function isMoveable()
	{
		return true;
	}
	
	
	
} // ResourceLabelGroup

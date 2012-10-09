<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'resource_label_user' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLabelUser extends BaseResourceLabelUser {

	public function getType()
	{
		return 'user';
	}
	
	public function getToken()
	{
		return $this->getType() . '_' . $this->getUserId() . '_' . $this->getId();
	}
	
	public function hasParent(PropelPDO $con = null)
	{
	   $rightManager = BNSAccess::getContainer()->get('bns.right_manager');
		$session = $rightManager->getSession();
		if($session->has('resource_current_user_folder_id')){
			return true;
		}
		return false;
	}
	
	public function getParent(PropelPDO $con = null)
	{
		if($this->isRoot()){
			$rightManager = BNSAccess::getContainer()->get('bns.right_manager');
			$session = $rightManager->getSession();
			if($session->has('resource_current_user_folder_id')){
				return ResourceLabelGroupQuery::create()->findOneById($session->get('resource_current_user_folder_id'));
			}
		}else{
			return parent::getParent();
		}
	}
	
	public function isChoiceable()
	{
		return true;
	}
	
	public function isDeleteable()
	{
		return true;
	}
	
	public function isEditable()
	{
		return true;
	}
	
	public function isMoveable()
	{
		return true;
	}
	
} // ResourceLabelUser

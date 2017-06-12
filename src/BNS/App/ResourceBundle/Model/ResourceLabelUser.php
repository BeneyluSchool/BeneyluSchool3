<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

class ResourceLabelUser extends BaseResourceLabelUser
{

    public function delete(\PropelPDO $con = null)
    {
        //Récupération des resources stronged linked
        $resources = ResourceQuery::create()
            ->useResourceLinkUserQuery()
            ->filterByIsStrongLink(true)
            ->filterByResourceLabelUserId($this->getId())
            ->endUse()
            ->find();
        $rm = BNSAccess::getContainer()->get('bns.resource_manager');
        foreach($resources as $resource)
        {
            $rm->delete($resource, $resource->getUserId(), $this->getType(), $this->getId(), true);
            $resource->setStatusDeletion(Resource::DELETION_STATUS_DELETED);
            $resource->save();
        }

        parent::delete();

        $rm->recalculateQuota($this->getType(), $this->getUserId());
    }

	/**
	 * @return string
	 */
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

	/**
	 * @return array<ResourceLinkUser>
	 */
	public function getResourceLinks()
	{
		return $this->getResourceLinkUsers();
	}

	/**
	 * Generate slug if not exists
	 *
	 * @return string The slug
	 */
	public function getSlug()
	{
		if (null == parent::getSlug()) {
			$this->setSlug($this->createSlug());
			$this->save();
		}

		return parent::getSlug();
	}

	/**
	 * @return int
	 */
	public function getObjectLinkedId()
	{
		return $this->getUserId();
	}
}
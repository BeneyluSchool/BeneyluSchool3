<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

class ResourceLabelGroup extends BaseResourceLabelGroup
{
    private $users;

    public function delete(\PropelPDO $con = null)
    {
        //Récupération des resources stronged linked
        $resources = ResourceQuery::create()
            ->useResourceLinkGroupQuery()
                ->filterByIsStrongLink(true)
                ->filterByResourceLabelGroupId($this->getId())
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

        $rm->recalculateQuota($this->getType(), $this->getGroupId());
    }
    
    public function isUserFolder()
    {
        return $this->getIsUserFolder();
    }

    public function getToken()
    {
        return $this->getType() . '_' . $this->getGroupId() . '_' . $this->getId();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return 'group';
    }

    public function getChildren($criteria = null, \PropelPDO $con = null)
    {

        //Espace utilisateur uniquement pour les classes
        if (!$this->getIsUserFolder() || $this->getGroup()->getGroupType()->getType() != 'CLASSROOM') {
            return parent::getChildren();
        }

        // TODO : Remove BnsAccess Call
        //Recupération des dossiers users
        $gm = BNSAccess::getContainer()->get('bns.group_manager');
        $gm->setGroupById($this->getGroupId());
        return ResourceLabelUserQuery::create()->filterByUser($gm->getUsersByPermissionUniqueName('RESOURCE_MY_RESOURCES',true))->filterByTreeLevel(0)->find();
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

    /**
     * @return array<ResourceLinkGroup>
     */
    public function getResourceLinks()
    {
        return $this->getResourceLinkGroups();
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
        return $this->getGroupId();
    }

    public function initUserFolder($label = "Espace utilisateurs")
    {
        if (null === $this->getGroupId() || $this->isNew()) {
            throw new \Exception('Cannot initUserFolder, root folder isNew or no groupId is set');
        }

        $hasUserFolder = ResourceLabelGroupQuery::create()
            ->filterByGroupId($this->getGroupId())
            ->filterByIsUserFolder(true)
            ->count();

        if (!$hasUserFolder) {
            $userFolder = new ResourceLabelGroup();
            $userFolder->setSlug('documents-utilisateurs-' . $this->getGroupId());
            $userFolder->setLabel("Documents utilisateurs");
            $userFolder->setGroupId($this->getGroupId());
            $userFolder->insertAsFirstChildOf($this);
            $userFolder->setIsUserFolder(true);
            $userFolder->save();
        }
    }
}

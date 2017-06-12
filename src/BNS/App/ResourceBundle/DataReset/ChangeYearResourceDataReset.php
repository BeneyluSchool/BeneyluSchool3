<?php

namespace BNS\App\ResourceBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\ResourceBundle\Form\Type\ChangeYearResourceDataResetType;
use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearResourceDataReset extends AbstractDataReset
{
    /**
     * @var string 
     */
    public $choice;

    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_resource';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        if ('KEEP' == $this->choice) {
            return;
        }

        // DELETE
        $resourcesId = ResourceQuery::create('r')
            ->select('r.Id')
            ->join('r.ResourceLinkGroup rlg')
            ->join('rlg.ResourceLabelGroup rlag')
            ->where('rlag.GroupId = ?', $group->getId())
        ->find();

        foreach($resourcesId as $resourceId)
        {
            $resource = ResourceQuery::create()->findOneById($resourceId);
            $resource->setStatusDeletion(Resource::DELETION_STATUS_DELETED);
            $resource->save();
        }

        // Group resources
        ResourceQuery::create('r')
            ->where('r.Id IN ?', $resourcesId)
        ->delete();

        // Group folders
        $root = ResourceLabelGroupQuery::create('rlag')->findRoot($group->getId());
        ResourceLabelGroupQuery::create('rlag')
            ->where('rlag.Id != ?', $root->getId())
            ->where('rlag.IsUserFolder = ?', false)
            ->where('rlag.GroupId = ?', $group->getId())
        ->delete();

        //On remet bien tree left et tree right
        $root->setTreeLeft(1);
        $root->setTreeRight(4);
        $root->save();

        // Reset resource quota for groupe
        $group->setAttribute('RESOURCE_USED_SIZE', 0);
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppResourceBundle:DataReset:change_year_resource.html.twig';
    }

    /**
     * @return ChangeYearResourceDataResetType
     */
    public function getFormType()
    {
        return new ChangeYearResourceDataResetType();
    }

    /**
     * @return array<String, String> 
     */
    public static function getChoices()
    {
        return array(
            'KEEP'     => 'Conserver les documents de la classe',
            'DELETE'   => 'Supprimer les documents du groupe'
        );
    }
}
<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroupPeer;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use \Exception;

/**
 * Skeleton subclass for performing query and update operations on the 'resource_label_group' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLabelGroupPeer extends BaseResourceLabelGroupPeer
{
    //TODO : remove this static function
    //Initialisation à la création du groupe
    public static function createResourceLabelGroup($params)
    {
        if (!isset($params['group_id']) || !isset($params['label'])) {
            throw new Exception('Please provide a group_id and a label');
        }
        $labelGroup = new ResourceLabelGroup();
        $labelGroup->setLabel($params['label']);
        $labelGroup->setSlug("groupe-" . $params['group_id']);
        $labelGroup->setGroupId($params['group_id']);
        $labelGroup->makeRoot();
        $labelGroup->save();

        $labelGroup->initUserFolder("Espace utilisateurs " . $labelGroup->getLabel());

        return $labelGroup;
    }
} // ResourceLabelGroupPeer

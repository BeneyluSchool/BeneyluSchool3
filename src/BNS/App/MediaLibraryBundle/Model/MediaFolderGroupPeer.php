<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Model\om\BaseMediaFolderGroupPeer;

class MediaFolderGroupPeer extends BaseMediaFolderGroupPeer
{


    public static function createMediaFolderGroup($params)
    {
        if (!isset($params['group_id']) || !isset($params['label'])) {
            throw new Exception('Please provide a group_id and a label');
        }
        $labelGroup = new MediaFolderGroup();
        $labelGroup->setLabel($params['label']);
        $labelGroup->setSlug("groupe-" . $params['group_id']);
        $labelGroup->setGroupId($params['group_id']);
        $labelGroup->setIsPrivate(false);
        $labelGroup->setStatusDeletion(MediaFolderManager::STATUS_ACTIVE);
        $labelGroup->makeRoot();
        $labelGroup->save();

        $labelGroup->initUserFolder("Espace utilisateurs " . $labelGroup->getLabel());

        return $labelGroup;
    }

}

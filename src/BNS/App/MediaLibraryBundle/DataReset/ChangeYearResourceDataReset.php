<?php

namespace BNS\App\MediaLibraryBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MediaLibraryBundle\Form\Type\ChangeYearResourceDataResetType;
use BNS\App\PaasBundle\Manager\PaasManager;


/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearResourceDataReset extends AbstractDataReset
{
    /** @var  MediaManager $mediaManager */
    protected $mediaManager;

    /** @var PaasManager  */
    protected $paasManager;

    public function __construct(MediaManager $mediaManager, PaasManager $paasManager)
    {
        $this->mediaManager = $mediaManager;
        $this->paasManager = $paasManager;
    }

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

        $mediaFolderIds = MediaFolderGroupQuery::create()
            ->filterByGroupId($group->getId())
            ->select(MediaFolderGroupPeer::ID)
            ->find()->toArray();



        $medias = MediaQuery::create()
            ->filterByMediaFolderType('GROUP')
            ->filterByMediaFolderId($mediaFolderIds)
            ->find();

        $manager = $this->mediaManager;

        foreach($medias as $media)
        {
            $manager->setMediaObject($media);
            $manager->delete(null,MediaManager::STATUS_DELETED);
        }

        MediaFolderGroupQuery::create()
            ->filterByTreeLevel('0',\Criteria::GREATER_THAN)
            ->filterByIsUserFolder('0')
            ->filterByGroupId($group->getId())
            ->delete();

        $root = MediaFolderGroupQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByTreeLevel(0)
            ->findOne();

        $root->setTreeLeft(1);
        $root->setTreeRight(4);
        $root->save();

        $userFolder = MediaFolderGroupQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByIsUserFolder(true)
            ->findOne();

        $userFolder->setTreeLeft(2);
        $userFolder->setTreeRight(3);
        $userFolder->setTreeLevel(1);
        $userFolder->save();


        // Reset resource quota for groupe
        $group->setAttribute('RESOURCE_USED_SIZE', 0);

        // reset Resource Spot cache
        $this->paasManager->resetClient($group);
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppMediaLibraryBundle:DataReset:change_year_resource.html.twig';
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

            'KEEP'     => 'KEEP_DOCUMENT',
            'DELETE'   => 'DELETE_DOCUMENT'
        );
    }
}

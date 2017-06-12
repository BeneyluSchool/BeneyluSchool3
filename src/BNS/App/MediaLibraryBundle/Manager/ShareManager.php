<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\NotificationBundle\Manager\NotificationManager;
use BNS\App\NotificationBundle\Notification\MediaLibraryBundle\MediaLibraryNewShareNotification;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ShareManager
 *
 * @package BNS\App\MediaLibraryBundle\Manager
 */
class ShareManager
{

    /**
     * @var array|MediaFolderUser[]
     */
    protected $shareDestinationCache = array();

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(MediaManager $mediaManager, ContainerInterface $container)
    {
        $this->mediaManager = $mediaManager;
        $this->container = $container;
    }

    /**
     * Shares the given Media to the Users given by their ids.
     *
     * @param Media $media
     * @param array $userIds
     * @param int $initiatorId
     */
    public function shareMedia(Media $media, $userIds, $initiatorId = null)
    {
        $destinations = $this->getDestinationFolders($userIds);

        $this->mediaManager->setMediaObject($media);
        foreach ($destinations as $destination) {
            $copy = $this->mediaManager->mCopy($destination, $destination->getUserId());
            $copy->setSharedBy($initiatorId)
                ->setIsPrivate(true)
                ->save()
            ;

            $this->container->get('notification_manager')->send(
                $destination->getUser(),
                new MediaLibraryNewShareNotification($this->container, $copy->getId())
            );
        }
    }

    /**
     * Gets the share destination folders for the given user ids. Folders will be created if missing.
     *
     * @param $userIds
     * @return array|MediaFolderUser[]
     */
    protected function getDestinationFolders($userIds)
    {
        $destinations = array();
        $unknownIds = array();

        // get cached destination folders
        foreach ($userIds as $id) {
            if (isset($this->shareDestinationCache[$id])) {
                $destinations[] = $this->shareDestinationCache[$id];
            } else {
                $unknownIds[] = $id;
            }
        }

        // handle not-cached folders
        if (count($unknownIds)) {
            // find existing share folders
            /** @var MediaFolderUser[] $existingFolders */
            $existingFolders = MediaFolderUserQuery::create()
                ->filterByUserId($unknownIds)
                ->filterByIsShareDestination(true)
                ->find()
            ;
            foreach ($existingFolders as $folder) {
                $destinations[] = $this->shareDestinationCache[$folder->getUserId()] = $folder;

                // folder exists, so remove it from unknown
                $idx = array_search($folder->getUserId(), $unknownIds);
                unset($unknownIds[$idx]);
            }

            // create share folders that are missing
            foreach ($unknownIds as $id) {
                $destinations[] = $this->shareDestinationCache[$id] = $this->createShareDestination($id);
            }
        }

        return $destinations;
    }

    /**
     * Creates a share destination folder for the given user id.
     *
     * @param $userId
     * @return MediaFolderUser
     */
    protected function createShareDestination($userId)
    {
        $root = MediaFolderUserQuery::create()->findRoot($userId);
        $folder = new MediaFolderUser();
        $folder->setUserId($userId)
            ->setLabel('Documents reçus')   // TODO i18n
            ->setIsShareDestination(true)
            ->insertAsFirstChildOf($root)
            ->save()
        ;

        return $folder;
    }

}

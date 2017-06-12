<?php

namespace BNS\App\HomeworkBundle\StarterKit;

use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\StarterKitBundle\Model\StarterKitState;
use BNS\App\StarterKitBundle\StarterKit\AbstractStarterKitProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HomeworkStarterKitProvider
 *
 * @package BNS\App\HomeworkBundle\StarterKit
 */
class HomeworkStarterKitProvider extends AbstractStarterKitProvider
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();

        $this->container = $container;

        $this->setHandler('2-2.3', function (StarterKitState $state) {
            $this->importMedia($state->getUser(), 'carte-guilde.jpg');
        });
        $this->setHandler('2-4.3', function (StarterKitState $state) {
            $this->importMedia($state->getUser(), 'code.png');
        });
        $this->setHandler('3-2.3', function (StarterKitState $state) {
            $this->importMedia($state->getUser(), 'sur-la-trace-des-dinosaures.pdf');
        });
        $this->setHandler('3-3.2', function (StarterKitState $state) {
            $this->importMedia($state->getUser(), 'diplodocus.pdf');
            $this->importMedia($state->getUser(), 'triceratops.pdf');
            $this->importMedia($state->getUser(), 'tyrannosaure.pdf');
        });
        $this->setHandler('3-4.1', function (StarterKitState $state) {
            $this->importMedia($state->getUser(), 'diplodocus.jpg');
            $this->importMedia($state->getUser(), 'triceratops.jpg');
            $this->importMedia($state->getUser(), 'tyrannosaure.jpg');
        });
    }

    /**
     * Module unique name concerned by this starter kit
     *
     * @return string
     */
    public function getName()
    {
        return 'HOMEWORK';
    }

    public function importMedia(User $user, $filename)
    {
        $folder = $this->getMediaLibraryFolder();

        // check if media already exist
        foreach ($folder->getMedias() as $media) {
            if ($media->getLabel() === $filename) {
                return $media;
            }
        }

        $path = $this->directory . '/' . $filename;
        $mediaCreator = $this->container->get('bns.media.creator');

        $mimeType = $mediaCreator->extensionToContentType(substr(strrchr($path, '.'), 1));

        $params = array(
            'label' => substr(strrchr($path, '/'),1),
            'type' => $mediaCreator->getModelTypeFromMimeType($mimeType),
            'mime_type' => $mimeType,
            'media_folder' => $folder,
            'user_id' => $user->getId(),
            'filename' => $filename,
        );

        $media = $mediaCreator->createModelDatas($params);

        $this->container->get('bns.file_system_manager')->getFileSystem()->write($media->getFilePath(), file_get_contents($path));

        return $media;
    }

    /**
     * Gets the media library folder where medias of this starter kit should be uploaded. This depends on the user.
     *
     * @return MediaFolderGroup
     */
    public function getMediaLibraryFolder()
    {
        $folderName = $this->container->get('translator')->trans('HOMEWORK', [], 'MODULE');
        $folderManager = $this->container->get('bns.media_folder.manager');
        $group = $this->container->get('bns.right_manager')->getCurrentGroup();
        $groupFolder = $folderManager->getGroupFolder($group);

        // check if folder already exist
        foreach ($groupFolder->getChildren() as $child) { /** @var MediaFolderGroup $child */
            if ($child->getLabel() === $folderName) {
                return $child;
            }
        }

        // create a new folder
        $folder = $folderManager->create($folderName, $groupFolder->getId(), 'GROUP');
        $folder->setIsPrivate(true);
        $folder->save();
    }

}

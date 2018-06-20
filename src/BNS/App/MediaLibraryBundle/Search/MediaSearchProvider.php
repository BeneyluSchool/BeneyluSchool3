<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 16:30
 */

namespace BNS\App\MediaLibraryBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MediaSearchProvider extends AbstractSearchProvider
{
    /**
     * @var Router $router
     */
    protected $router;

    /**
     * @var BNSRightManager $rightManager
     */
    protected $rightManager;
    /**
     * @var TokenStorage $tokenStorage
     */
    protected $tokenStorage;

    /**
     * BlogSearchProvider constructor.
     * @param Router $router
     * @param BNSRightManager $rightManager
     * @param TokenStorage $tokenStorage
     */
    public function __construct(Router $router, BNSRightManager $rightManager, TokenStorage $tokenStorage)
    {
        $this->router = $router;
        $this->rightManager = $rightManager;
        $this->tokenStorage = $tokenStorage;
    }

    public function search($search = null, $options = array()) {
        $user = $this->getUser();
        if ($search == null || $user == null) {
           return;
        }
        $groupIds = $this->rightManager->getGroupIdsWherePermission('MEDIALIBRARY_ACCESS');
        $userMediaIds = MediaQuery::create('a')
            ->filterByUserId($user->getId())
            ->select('a.Id')
            ->find()
            ->toArray();
        $groupMediaFolderIds = MediaFolderGroupQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();
        $groupMediasIds = MediaQuery::create()
            ->filterByIsPrivate(false)
            ->filterByMediaFolderId($groupMediaFolderIds)
            ->select('id')
            ->find()
            ->toArray();
        $mediaIds = array_unique(array_merge($userMediaIds, $groupMediasIds));
        $medias = MediaQuery::create()->filterById($mediaIds, \Criteria::IN)
            ->filterByStatusDeletion(1)
            ->filterByDescription('%' . htmlentities($search) . '%', \Criteria::LIKE)
            ->_or()
            ->filterByLabel('%' . $search . '%', \Criteria::LIKE)
            ->find();

        $response = array();
        foreach ($medias as $media) {
            /** @var Media $media */
            $response [] = ['id' => $media->getId(),
                'type' => $this->getName(),
                'title' => $media->getLabel(),
                'date' => $media->getUpdatedAt('Y-m-d'),
                'url' => $this->router->generate('BNSAppMediaLibraryBundle_front_media', ['mediaId' => $media->getId()]) ];

        }
        return $response;
    }

    /**
     * Module unique name concerned by this search
     *
     * @return string
     */
    public function getName()
    {
       return 'MEDIA_LIBRARY';
    }

    protected function getUser()
    {
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user && $user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}

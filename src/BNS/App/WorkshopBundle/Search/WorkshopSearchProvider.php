<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 22/01/2018
 * Time: 13:11
 */

namespace BNS\App\WorkshopBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use BNS\App\WorkshopBundle\Manager\RightManager;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopContentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class WorkshopSearchProvider extends AbstractSearchProvider
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
     * @var RightManager $workshopRightManager
     */
    protected $workshopRightManager;

    /**
     * WorkshopSearchProvider constructor.
     * @param Router $router
     * @param BNSRightManager $rightManager
     * @param TokenStorage $tokenStorage
     * @param RightManager $workshopRightManager
     */
    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager, Router $router, RightManager $workshopRightManager)
    {
        $this->router = $router;
        $this->rightManager = $rightManager;
        $this->tokenStorage = $tokenStorage;
        $this->workshopRightManager = $workshopRightManager;
    }


    public function search($search = null, $options = array()) {
        $user = $this->getUser();
        if ($search == null || $user == null) {
            return;
        }
        $potentialAuthorIds = $this->workshopRightManager->getManagedAuthorIds($user);
        if (!in_array($user->getId(), $potentialAuthorIds)) {
            $potentialAuthorIds[] = $user->getId();
        }
        $contentIds = WorkshopContentQuery::create()
            ->useMediaQuery()
            ->filterByStatusDeletion(1)
            ->endUse()
            ->lastUpdatedFirst()
            ->filterByAuthorId($potentialAuthorIds)
            ->_or()
            ->useWorkshopContentContributorQuery(null, \Criteria::LEFT_JOIN)
            ->filterByUser($user)
            ->endUse()
            ->_or()
            ->useWorkshopContentGroupContributorQuery(null, \Criteria::LEFT_JOIN)
            ->filterByGroup($user->getGroups())
            ->endUse()
            ->select('id')
            ->find()
            ->toArray()
        ;

        $contents = WorkshopContentQuery::create()
            ->filterById($contentIds, \Criteria::IN)
            ->useMediaQuery()
                ->filterByDescription('%' . htmlentities($search) . '%', \Criteria::LIKE)
                ->_or()
                ->filterByLabel('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->find();

        $response = array();
        foreach ($contents as $content) {
            /** @var WorkshopContent $content */

            if ($content->getType() === WorkshopContentPeer::TYPE_AUDIO) {
                $url = $this->router->generate('BNSAppMediaLibraryBundle_front_media', ['mediaId' => $content->getMediaId()]);
            } else {
                $url = $this->router->generate('BNSAppWorkshopBundle_front') . '/documents/' . $content->getId() . '/pages/1/index';
            }
            $response [] = ['id' => $content->getId(),
                'type' => $this->getName(),
                'title' => $content->getLabel(),
                'date' => $content->getUpdatedAt('Y-m-d'),
                'url' => $url];

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
        return 'WORKSHOP';
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

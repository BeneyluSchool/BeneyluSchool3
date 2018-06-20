<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 16:45
 */

namespace BNS\App\MiniSiteBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MiniSiteSearchProvider extends AbstractSearchProvider
{

    /**
     * @var TokenStorage $tokenStorage
     */
    protected $tokenStorage;

    /**
     * @var BNSRightManager $rightManager
     */
    protected $rightManager;
    /**
     * @var Router $router
     */
    protected $router;

    /**
     * HomeworkSearchProvider constructor.
     * @param TokenStorage $tokenStorage
     */
    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager, Router $router)
    {
        $this->tokenStorage = $tokenStorage;
        $this->rightManager = $rightManager;
        $this->router = $router;
    }
    public function search($search = null, $options= array()) {
        $user = $this->getUser();
        if ($search == null || $user == null) {
            return;
        }
        $groupIds = $this->rightManager->getGroupIdsWherePermission('MINISITE_ACCESS');
        $minisitePageIds = MiniSitePageQuery::create()
            ->useMiniSiteQuery()
                ->filterByGroupId($groupIds, \Criteria::IN)
            ->endUse()
            ->filterByIsActivated(true)
            ->select('id')
            ->find()
            ->toArray()
            ;
        $pages = MiniSitePageQuery::create()->filterById($minisitePageIds)
            ->useMiniSitePageTextQuery()
                ->filterByPublishedContent('%' . htmlentities($search) . '%', \Criteria::LIKE)
                ->_or()
                ->filterByPublishedTitle('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->find();
        $response = array();
        foreach ($pages as $page) {
            /** @var MiniSitePage $page */
            $response [] = ['id' => $page->getId(),
                'type' => $this->getName(),
                'title' => $page->getTitle(),
                'url' => $this->router->generate('BNSAppMiniSiteBundle_front_page', array('slug' => $page->getMiniSite()->getSlug(), 'page' => $page->getSlug())) ];
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
        return 'MINISITE';
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

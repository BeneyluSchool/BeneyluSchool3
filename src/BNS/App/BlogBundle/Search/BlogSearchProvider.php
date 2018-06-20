<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 15:48
 */

namespace BNS\App\BlogBundle\Search;


use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class BlogSearchProvider extends AbstractSearchProvider
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
     * @param TokenStorage $tokenStorage
     * @param BNSRightManager $rightManager
     * @param Router $router
     */
    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager, Router $router)
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
        $groupIds = $this->rightManager->getGroupIdsWherePermission('BLOG_ACCESS');
        $articleIds = BlogArticleQuery::create('a')
            ->useBlogArticleBlogQuery()
                ->useBlogQuery()
                    ->filterByGroupId($groupIds, \Criteria::IN)
                ->endUse()
            ->endUse()
            ->select('a.Id')
            ->find()
            ->toArray();

        $articles = BlogArticleQuery::create()->filterById($articleIds, \Criteria::IN)
            ->filterByStatus(BlogArticlePeer::STATUS_PUBLISHED)
            ->filterByContent('%' . htmlentities($search) . '%', \Criteria::LIKE)
            ->_or()
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->find();

        $response = array();
        foreach ($articles as $article) {
            /** @var BlogArticle $article */
            $response [] = ['id' => $article->getId(),
                'type' => $this->getName(),
                'title' => $article->getTitle(),
                'date' => $article->getPublishedAt('Y-m-d'),
                'url' => $this->router->generate('BNSAppBlogBundle_front') ];

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
        return 'BLOG';
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

<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 22/01/2018
 * Time: 09:15
 */

namespace BNS\App\ForumBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\ForumBundle\Model\Forum;
use BNS\App\ForumBundle\Model\ForumQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ForumSearchProvider extends AbstractSearchProvider
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
     * ForumSearchProvider constructor.
     * @param TokenStorage $tokenStorage
     * @param BNSRightManager $rightManager
     * @param Router $router
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

        $groupIds = $this->rightManager->getGroupIdsWherePermission('FORUM_ACCESS');

        $forumIds = ForumQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();

        $forums = ForumQuery::create()->filterById($forumIds)
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->_or()
            ->useForumSubjectQuery()
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->find();
        $response = array();
        foreach ($forums as $forum) {
            /** @var Forum $forum */
            $response [] = ['id' => $forum->getId(),
                'type' => $this->getName(),
                'title' => $forum->getTitle(),
                'date' => $forum->getUpdatedAt(),
                'url' => $this->router->generate('BNSAppForumBundle_front_slug', array('slug' => $forum->getSlug())) ];
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
       return 'FORUM';
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

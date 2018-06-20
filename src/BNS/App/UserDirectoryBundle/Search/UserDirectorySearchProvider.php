<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 22/01/2018
 * Time: 12:18
 */

namespace BNS\App\UserDirectoryBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use BNS\App\UserDirectoryBundle\Manager\UserDirectoryManager;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class UserDirectorySearchProvider extends AbstractSearchProvider
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
     * @var UserDirectoryManager $tokenStorage
     */
    protected $userDirectoryManager;

    /**
     * UserDirectorySearchProvider constructor.
     * @param Router $router
     * @param BNSRightManager $rightManager
     * @param TokenStorage $tokenStorage
     * @param UserDirectoryManager $userDirectoryManager
     */
    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager, Router $router, UserDirectoryManager $userDirectoryManager)
    {
        $this->router = $router;
        $this->rightManager = $rightManager;
        $this->tokenStorage = $tokenStorage;
        $this->userDirectoryManager = $userDirectoryManager;
    }


    public function search($search = null, $options = array()) {
        $user = $this->getUser();
        if ($search == null || $user == null) {
            return;
        }
        $userIds = [];
        $groups = $this->rightManager->getGroupsWherePermission('USER_DIRECTORY_ACCESS');
        foreach ($groups as $group) {

            $recipients = $this->userDirectoryManager->getUserIdsByRoles($group, 'search');
            foreach ($recipients as $idsByRole) {
                $userIds = array_merge($userIds, $idsByRole);
            }
        }
        $users = UserQuery::create()->filterById($userIds, \Criteria::IN)
            ->filterByLastName('%' . $search . '%', \Criteria::LIKE)
            ->_or()
            ->filterByFirstName('%' . $search . '%', \Criteria::LIKE)
            ->find();

        $response = array();
        foreach ($users as $user) {
            /** @var User $user */
            $response [] = ['id' => $user->getId(),
                'type' => $this->getName(),
                'title' => $user->getFullName(),
                'date' => $user->getUpdatedAt('Y-m-d'), 'url' => $this->router->generate('BNSAppProfileBundle_view_profile', ['userSlug' => $user->getSlug()]) ];

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
        return 'USER_DIRECTORY';
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

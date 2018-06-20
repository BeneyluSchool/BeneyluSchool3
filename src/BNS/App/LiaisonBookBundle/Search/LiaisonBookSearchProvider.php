<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 17:59
 */

namespace BNS\App\LiaisonBookBundle\Search;


use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class LiaisonBookSearchProvider extends AbstractSearchProvider
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
        $groupIds = $this->rightManager->getGroupIdsWherePermission('LIAISONBOOK_ACCESS');
        $liaisonBookIndividualizedIds = LiaisonBookQuery::create()
            ->filterByIndividualized(true)
            ->useLiaisonBookUserQuery()
            ->filterByUserId($user->getId())
            ->endUse()
            ->select('id')
            ->find()
            ->toArray()
        ;
        $liaisonBookGroupIds = LiaisonBookQuery::create()
            ->filterByIndividualized(false)
            ->filterByGroupId($groupIds)
            ->select('id')
            ->find()
            ->toArray();
        $liaisonBookIds = array_unique(array_merge($liaisonBookGroupIds, $liaisonBookIndividualizedIds));
        $liaisonBooks = LiaisonBookQuery::create()->filterById($liaisonBookIds)
            ->filterByContent('%' . htmlentities($search) . '%', \Criteria::LIKE)
            ->_or()
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->find();
        $response = array();
        foreach ($liaisonBooks as $liaisonBook) {
            /** @var LiaisonBook $liaisonBook */
            $response [] = ['id' => $liaisonBook->getId(),
                'type' => $this->getName(),
                'title' => $liaisonBook->getTitle(),
                'date' => $liaisonBook->getDate('Y-m-d'),
                'url' => $this->router->generate('liaison_book_message', array('slug' => $liaisonBook->getSlug())) ];
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
        return 'LIAISONBOOK';
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

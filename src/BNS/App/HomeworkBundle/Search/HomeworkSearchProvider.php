<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 13:20
 */

namespace BNS\App\HomeworkBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkGroupPeer;
use BNS\App\HomeworkBundle\Model\HomeworkGroupQuery;
use BNS\App\HomeworkBundle\Model\HomeworkQuery;
use BNS\App\HomeworkBundle\Model\HomeworkUserPeer;
use BNS\App\HomeworkBundle\Model\HomeworkUserQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use BNS\App\WorkshopBundle\Manager\RightManager;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class HomeworkSearchProvider extends AbstractSearchProvider
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

    /**
     * Module unique name concerned by this search
     *
     * @return string
     */
    public function getName()
    {
       return 'HOMEWORK';
    }

    public function search($search = null, $options= array()) {
        $user = $this->getUser();
        if ($search == null || $user == null) {
            return;
        }
        $groupIds = $this->rightManager->getGroupIdsWherePermission('HOMEWORK_ACCESS');
        $homeworkFromGroupsIds = HomeworkGroupQuery::create()->filterByGroupId($groupIds, \Criteria::IN)->select(HomeworkGroupPeer::HOMEWORK_ID)->find()->toArray();
        $individualizedHomeworkIds = HomeworkUserQuery::create()->filterByUserId($user->getId())->select(HomeworkUserPeer::HOMEWORK_ID)->find()->toArray();
        $homeworkIds = array_unique(array_merge($homeworkFromGroupsIds, $individualizedHomeworkIds));
        $homeworks = HomeworkQuery::create()->filterById($homeworkIds)
            ->filterByDescription('%' . htmlentities($search) . '%', \Criteria::LIKE)
            ->_or()
            ->filterByName('%' . $search . '%', \Criteria::LIKE)
            ->find();
        $response = array();
        foreach ($homeworks as $homework) {
            /** @var Homework $homework */
            $response [] = ['id' => $homework->getId(),
                'type' => $this->getName(),
                'title' => $homework->getName(),
                'date' => $homework->getUpdatedAt('Y-m-d'),
                'url' => $this->router->generate('BNSAppHomeworkBundle_front') . '/' . $homework->getDate('Y-m-d') ];
        }

        return $response;

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

<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 22/01/2018
 * Time: 09:34
 */

namespace BNS\App\LunchBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\LunchBundle\Model\LunchWeek;
use BNS\App\LunchBundle\Model\LunchWeekQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class LunchSearchProvider extends AbstractSearchProvider
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
     * LunchSearchProvider constructor.
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

        $groupIds = $this->rightManager->getGroupIdsWherePermission('LUNCH_ACCESS');

        $lunchIds = LunchWeekQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();
        $lunchs = LunchWeekQuery::create()->filterById($lunchIds)
            ->useLunchDayQuery()
                ->filterByAccompaniment('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByDessert('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByStarter('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByMainCourse('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByDairy('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByAfternoonSnack('%' . $search . '%', \Criteria::LIKE)
                ->_or()
                ->filterByFullMenu('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->_or()
            ->filterByLabel('%' . $search . '%', \Criteria::LIKE)
            ->_or()
            ->filterByDescription('%' . $search . '%', \Criteria::LIKE)
            ->groupById() // avoid duplicates introduced by "join lunch day"
            ->find();
        $response = array();
        foreach ($lunchs as $lunch) {
            /** @var LunchWeek $lunch */
            $response [] = ['id' => $lunch->getId(),
                'type' => $this->getName(),
                'title' => $lunch->getLabel(),
                'date' => $lunch->getDateStart('Y-m-d'),
                'url' => $this->router->generate('BNSAppLunchBundle_front') . $lunch->getDateStart('Y-m-d') ];
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
       return 'LUNCH' ;
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

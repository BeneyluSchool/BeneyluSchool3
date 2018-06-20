<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 18:31
 */

namespace BNS\App\CalendarBundle\Search;


use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use BNS\App\StatisticsBundle\Model\CalendarQuery;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class CalendarSearchProvider extends AbstractSearchProvider
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

        $groupIds = $this->rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS');

        $calendarIds = AgendaQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();

        $agendas = AgendaQuery::create()->filterById($calendarIds)
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->_or()
            ->useAgendaEventQuery()
            ->filterByTitle('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->find();
        $response = array();
        foreach ($agendas as $agenda) {
            /** @var Agenda $agenda */
            $response [] = ['id' => $agenda->getId(),
                'type' => $this->getName(),
                'title' => $agenda->getTitle(),
                'url' => $this->router->generate('BNSAppCalendarBundle_front') ];
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
        return 'CALENDAR';
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

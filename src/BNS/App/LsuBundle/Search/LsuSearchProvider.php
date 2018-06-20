<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 22/01/2018
 * Time: 16:12
 */

namespace BNS\App\LsuBundle\Search;


use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\LsuBundle\Model\Lsu;
use BNS\App\LsuBundle\Model\LsuQuery;
use BNS\App\SearchBundle\Search\AbstractSearchProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class LsuSearchProvider extends AbstractSearchProvider
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
     * LsuSearchProvider constructor.
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
        $groupIds = $this->rightManager->getGroupIdsWherePermission('LSU_ACCESS');
        $lsuIds = LsuQuery::create()
            ->useLsuTemplateQuery()
                ->useLsuConfigQuery()
                    ->filterByGroupId($groupIds)
                ->endUse()
            ->endUse()
            ->select('id')
            ->find()
            ->toArray()
        ;
        $lsus = LsuQuery::create()
            ->filterById($lsuIds)
            ->useLsuTemplateQuery()
            ->filterByPeriod('%' . $search . '%', \Criteria::LIKE)
            ->endUse()
            ->_or()
            ->filterByGlobalEvaluation('%' . $search . '%', \Criteria::LIKE)
            ->find();
        $response = array();
        foreach ($lsus as $lsu) {
            /** @var Lsu $lsu */
            $response [] = ['id' => $lsu->getId(),
                'type' => $this->getName(),
                'title' => $lsu->getLsuTemplate()->getPeriod(),
                'date' => $lsu->getUpdatedAt(),
                'url' => $this->router->generate('BNSAppLsuBundle_front') . '/records/' . $lsu->getId()];
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
       return 'LSU';
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

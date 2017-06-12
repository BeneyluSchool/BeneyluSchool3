<?php

namespace BNS\App\StoreBundle\Subscription;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\StoreBundle\Client\StoreClient;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class SubscriptionManager
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @param StoreClient $storeClient
     */
    public function __construct(StoreClient $storeClient)
    {
        $this->storeClient = $storeClient;
    }

    /**
     * @param Group $group
     *
     * @return array
     */
    public function getSubcriptions(Group $group)
    {
        $uai = $group->getAttribute('UAI', false);
        if (false === $uai) {
            // TODO add exception ?
            return array();
        }

        $response = $this->storeClient->get('/subscriptions/{uai}', array(
                'uai' => $uai
            ))
            ->useCache()
        ->send();

        return $response->toArray();
    }

    /**
     * @return array
     */
    public function getProviders()
    {
        return $this->storeClient->get('/providers')->send()->toArray();
    }

    /**
     * @param \BNS\App\CoreBundle\Model\Group $group
     *
     * @return array
     */
    public function getAvailableProviders(Group $group)
    {
        $uai = $group->getAttribute('UAI', false);
        if (false === $uai) {
            // TODO add exception ?
            return array();
        }

        $response = $this->storeClient->get('/providers/{uai}', array(
                'uai' => $uai
            ))
            ->useCache()
            ->expires('+1 day')
        ->send();

        return $response->toArray();
    }

    /**
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @param array $providerIds
     */
    public function addSubscriptions(Group $group, $providerIds)
    {
        $uai = $group->getAttribute('UAI', false);
        if (false === $uai) {
            // TODO add exception ?
        }

        $this->storeClient->post('/subscriptions/{uai}', array(
                'uai' => $uai
            ),
            array(
                'providers' => $providerIds
            ))
        ->send();

        // Remove cache
        $this->clearSubscriptionsCache($uai);
    }

    /**
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @param array $providerIds
     */
    public function removeSubscriptions(Group $group, $providerIds)
    {
        $uai = $group->getAttribute('UAI', false);
        if (false === $uai) {
            // TODO add exception ?
        }

        $this->storeClient->delete('/subscriptions/{uai}', array(
                'uai' => $uai
            ),
            array(
                'providers' => $providerIds
            ))
        ->send();

        // Remove cache
        $this->clearSubscriptionsCache($uai);
    }

    /**
     * @param int $uai
     */
    private function clearSubscriptionsCache($uai)
    {
        $this->storeClient->removeCache('/subscriptions/{uai}', array(
            'uai' => $uai
        ));
    }
}
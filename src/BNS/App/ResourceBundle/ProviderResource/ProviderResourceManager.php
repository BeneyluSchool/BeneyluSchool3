<?php

namespace BNS\App\ResourceBundle\ProviderResource;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\ResourceBundle\Model\ResourceProvider;
use BNS\App\StoreBundle\Client\StoreClient;
use BNS\App\StoreBundle\Exception\WaitingForCacheException;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ProviderResourceManager
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var SecurityContext
     */
    private $securityContext;


    /**
     * @param StoreClient     $storeClient
     * @param BNSGroupManager $groupManager
     * @param BNSUserManager  $userManager
     * @param SecurityContext $securityContext
     */
    public function __construct(StoreClient $storeClient, BNSGroupManager $groupManager, BNSUserManager $userManager, SecurityContext $securityContext)
    {
        $this->storeClient     = $storeClient;
        $this->groupManager    = $groupManager;
        $this->userManager     = $userManager;
        $this->securityContext = $securityContext;
    }

    /**
     * @param string $uai
     *
     * @return boolean
     */
    public function hasUai($uai)
    {
        $this->userManager->setUser($this->securityContext->getToken()->getUser());
        $graph = $this->groupManager->buildGraph($this->userManager->getGroupsWherePermission('RESOURCE_ACCESS'));

        foreach ($graph->getNodes() as $node) {
            if ($node->getGroup()->hasAttribute('UAI') && $node->getGroup()->getAttribute('UAI') == $uai) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ResourceProvider $resourceProvider
     *
     * @return array
     */
    public function getProviderResourceByResourceProvider(ResourceProvider $resourceProvider)
    {
        return $this->getProviderResource($resourceProvider->getUai(), $resourceProvider->getProviderId(), $resourceProvider->getId());
    }

    /**
     * @param string $uai
     * @param int    $providerId
     * @param string $resourceId
     *
     * @return array
     *
     * @throws WaitingForCacheException
     */
    public function getProviderResource($uai, $providerId, $resourceId)
    {
        $response = $this->storeClient->get('/resources/{uai}/resource/{resourceId}/provider/{providerId}', array(
            'uai'        => $uai,
            'resourceId' => $resourceId,
            'providerId' => $providerId
        ))->send();

        if (206 == $response->getStatusCode()) {
            throw new WaitingForCacheException('The store cache is being built, please retry after a few seconds.');
        }

        if (false === $response->getContent()) {
            return null;
        }

        return new ProviderResource($uai, $response->toArray());
    }

    public function getProviderResourceUrl($uai, $providerId, $resourceId)
    {
        $response = $this->storeClient->get('/resources/{uai}/url/{resourceId}/provider/{providerId}', array(
            'uai'        => $uai,
            'resourceId' => $resourceId,
            'providerId' => $providerId
        ))->send();

        if (206 == $response->getStatusCode()) {
            throw new WaitingForCacheException('The store cache is being built, please retry after a few seconds.');
        }

        if (false === $response->getContent()) {
            return null;
        }

        return json_decode($response->getContent());
    }
}
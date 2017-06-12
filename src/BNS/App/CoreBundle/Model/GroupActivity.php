<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupActivity;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class GroupActivity extends BaseGroupActivity
{
    // Inject values based on current user before render by the serialiser
    public $canOpen = false;
    public $isUninstallable = false;
    public $hasAccessFront = false;
    public $hasAccessBack = false;

    public function getRouteFront()
    {
        return 'bns_activity_front';
    }

    public function getRouteParameters()
    {
        return array(
            'groupId' => $this->getGroupId(),
            'activityName' => $this->getUniqueName()
        );
    }

    public function hasRouteFront(RouterInterface $router)
    {
        try {
            // try to generate the url, this prevent a call to getRouteCollection
            $router->generate($this->getRouteFront(), $this->getRouteParameters());
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }

    public function hasRouteBack(RouterInterface $router)
    {
        return false;
    }

    public function getUniqueName()
    {
        return $this->getActivity()->getUniqueName();
    }

    public function getLabel()
    {
        return $this->getActivity()->getLabel();
    }

    public function getImageUrl()
    {
        return $this->getActivity()->getImageUrl();
    }
}

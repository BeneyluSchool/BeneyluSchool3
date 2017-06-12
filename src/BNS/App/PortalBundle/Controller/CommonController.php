<?php

namespace BNS\App\PortalBundle\Controller;

use BNS\App\PortalBundle\Model\Portal;
use BNS\App\PortalBundle\Model\PortalPeer;
use BNS\App\PortalBundle\Model\PortalQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CommonController extends Controller
{
    /**
     * @var Portal $currentPortal
     */
    protected $currentPortal;

    /**
     * @return Portal
     */
    protected function getCurrentPortal()
    {
        if(!isset($this->currentPortal))
        {
            $this->currentPortal = PortalQuery::create()->findOneByGroupId($this->get('bns.right_manager')->getCurrentGroupId());
            if(!$this->currentPortal)
            {
                $this->currentPortal = $this->get('bns.portal_manager')->create($this->get('bns.right_manager')->getCurrentGroup());
            }
        }
        return $this->currentPortal;
    }
}

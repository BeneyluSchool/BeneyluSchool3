<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

class SpotController extends Controller
{

    /**
     * Accueil de la page d'attente Spot
     * @Template()
     * @RightsSomeWhere("MAIN_SPOT_ACCESS")
     * @Route("/bientot", name="BNSAppMainBundle_spot_waiting")
     */
    public function waitingAction()
    {
        $this->get('bns.analytics.manager')->track('SPOT_DOCKBAR_USER', $this->get('bns.right_manager')->getModelUser());
        if($this->get('bns.right_manager')->getModelUser()->getSpotClic() == true)
        {
            return $this->redirect($this->generateUrl('BNSAppMainBundle_spot_offers'));
        }
        return array();
    }


    /**
     * Clic au call to Action
     * @Template()
     * @RightsSomeWhere("MAIN_SPOT_ACCESS")
     * @Route("/presentation", name="BNSAppMainBundle_spot_offers")
     */
    public function offersAction()
    {
        if($this->get('bns.right_manager')->getModelUser()->getSpotClic() == false)
        {
            $this->get('bns.analytics.manager')->track('SPOT_WAITING_CTA_USER', $this->get('bns.right_manager')->getModelUser());
            $this->get('bns.right_manager')->getModelUser()->setSpotClic(true)->save();
        }
        return array();
    }

    /**
     * Clic au call to Action vers spot
     * @RightsSomeWhere("MAIN_SPOT_ACCESS")
     * @Route("/presentation-spot", name="BNSAppMainBundle_spot_redirect")
     */
    public function redirectAction()
    {
        $this->get('bns.analytics.manager')->track('SPOT_WAITING_ORDER_USER', $this->get('bns.right_manager')->getModelUser());
        return $this->redirect("https://beneylu.com/spot/cartes-beneylu-spot");
    }
}
<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MedialandesController extends Controller
{

    /**
     * Index de l'Iframe Medialandes
     * @Template
     * @Route("/medialandes", name="BNSAppMainBundle_medialandes_index", options={"expose"=true})
     */
    public function indexAction()
    {
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasMedialandes());
        return array();
    }

}

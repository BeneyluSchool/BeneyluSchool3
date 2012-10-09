<?php

namespace BNS\App\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BNS\App\CoreBundle\Annotation\Rights;

class HomeController extends Controller
{
	/**
	 * Page d'accueil de l'administration
	 * @Route("/accueil", name="BNSAppAdminBundle_front")
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
    	return $this->render('BNSAppAdminBundle:Home:index.html.twig');
		
    }
}
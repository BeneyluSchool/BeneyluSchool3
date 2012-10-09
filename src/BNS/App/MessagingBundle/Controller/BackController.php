<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use BNS\App\MessagingBundle\Controller\CommonController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
/**
 * @Route("/gestion")
 */
class BackController extends CommonController
{	
	
	/**
	 * Page d'accueil de la gestion
	 * @Route("/", name="BNSAppMessagingBundle_back")
	 * @Template()
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function indexAction()
	{
		if($this->getMessagingType() == 'light'){
			return $this->render('BNSAppMessagingBundle:Back/Light:index.html.twig', array());	
		}elseif($this->getMessagingType() == 'real'){
			return $this->render('BNSAppMessagingBundle:Back:index.html.twig', array());	
		}
	}
}


<?php

namespace BNS\App\HelloWorldBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use BNS\App\CoreBundle\Annotation\Rights;

/**
 * Prefix général pour toutes les routes de ce controller
 * Vu que nous sommes dans le controller de l'administration, il est logique
 * d'avoir un prefix "gestion" (admin).
 * 
 * @Route("/gestion")
 */
class BackController extends Controller
{
	/**
	 * Exemple de vérification de permission avec une annotation (ne pas oublier le "use BNS\App\CoreBundle\Annotation\Rights",
	 * vous pouvez aussi mettre plusieurs permissions à la suite, séparées par une virgule, exemple : Rights("HELLOWORLD_ADMINISTRATION, HELLOWORLD_DELETE_USER")
	 * 
	 * @Route("/", name="BNSAppHelloWorldBundle_back")
	 * @Rights("HELLOWORLD_ACCESS_BACK")
	 * @Template()
	 */
	public function indexAction()
	{
		return array();
	}
	
	
	/* PARTIALS */
	
	/**
	 * @Route("/boutons", name="hello_world_manager_buttons")
	 */
	public function buttonsAction()
	{
		return $this->render('BNSAppHelloWorldBundle:Back:buttons.html.twig');
	}
	
	/**
	 * @Route("/administration-categories", name="hello_world_manager_categories_management")
	 */
	public function categoriesManagementAction()
	{
		return $this->render('BNSAppHelloWorldBundle:Back:categories_management.html.twig');
	}

    /**
     * @Route("/flash-messages", name="hello_world_manager_flash_messages")
     */
    public function flashMessagesAction()
    {
        return $this->render('BNSAppHelloWorldBundle:Back:flash_messages.html.twig');
    }

    /**
     * @Route("/formulaire", name="hello_world_manager_forms")
     */
    public function formsAction()
    {
        return $this->render('BNSAppHelloWorldBundle:Back:forms.html.twig');
    }
	
	/**
	 * @Route("/exemples-administration", name="hello_world_manager_administration")
	 */
	public function administrationAction()
	{
		return $this->render('BNSAppHelloWorldBundle:BackExample:index.html.twig');
	}
}
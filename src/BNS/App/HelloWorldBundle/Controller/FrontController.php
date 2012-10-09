<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\HelloWorldBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends Controller
{	
	/**
	 * Page d'accueil du module : affiche le module et les bonnes pratiques à prendre
	 * Nommage "BNSAppHelloWorldBundle_front" obligatoire, généralement sur le "/" (pas forcément)
	 * Routing en annotation	
	 * @Route("/", name="BNSAppHelloWorldBundle_front")
	 * @Template()
	 */
	public function indexAction()
	{		
		
		$right_manager = $this->get('bns.right_manager');
		
		//Vérification du / des droits => renvoie ici sur une 404 (si erreur de slug par exemple)
		//Si tentative frauduleuse, passer par l'action ci dessous (FobiddenAction)
		if(!$right_manager->hasRight('HELLOWORLD_ACCESS')){
			throw new NotFoundHttpException("Perdu vous n'avez pas les droits, petit malin !");
		}

		//Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
		$context = $this->get('bns.right_manager')->getContext();
		
		$locale = $right_manager->getLocale();
		
		
		return array('context' => $context,'locale' => $locale);
	}
	
	/**
	 * Exemple de page / action interdite
	 * @Route("/forbidden", name="BNSAppHelloWorldBundle_front_forbidden")
	 * @Template()
	 */
	public function forbiddenAction()
	{
		$this->get('bns.right_manager')->forbidIf(true);
	}
	
	/**
	 * Exemple d'envoi de mail
	 * @Route("/email", name="BNSAppHelloWorldBundle_front_email")
	 * @Template()
	 */
	public function emailAction()
	{
		// UNIQUE_NAME du modèle de mail
		$this->get('bns.mailer')->send('WELCOME', array(
				// Tableau de variables, lié au modèle
				'first_name' => $this->getUser()->getUser()->getFirstName(),
				'last_name' => $this->getUser()->getUser()->getLastName()
			),
			$this->getRequest()->get('email'),
			$this->getUser()->getLang()
		);
		// Choix possibles : send(), sendUser(), sendMultiple(), voir BNSMailer.php pour les paramètres
			
		/*
		 * Pour les Flash : notice, notice_warning, notice_success, notice_error
		 */
		$this->get('session')->setFlash('notice',"Front.Index.notice.email_sent");

		return $this->redirect($this->generateUrl('BNSAppHelloWorldBundle_front'));
		
	}
	
	
	/**
	 * Exemple d'envoi de mail
	 * @Route("/changement-langue/{culture}", name="BNSAppHelloWorldBundle_front_change_culture")
	 */
	public function changeCultureAction($culture)
	{
		
		$this->get('bns.right_manager')->setLocale($culture);
		
		$this->get('session')->setFlash('notice',"Front.Index.notice.update_done");
		
		return $this->redirect($this->generateUrl('BNSAppHelloWorldBundle_front'));
		
	}
	
}


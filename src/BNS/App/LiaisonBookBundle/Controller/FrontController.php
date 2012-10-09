<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;

class FrontController extends Controller
{	
	
    /**
        * Page d'accueil du module : affiche le module et les bonnes pratiques à prendre
        * Nommage "BNSAppLiaisonBookBundle_front" obligatoire, généralement sur le "/" (pas forcément)
        * 
	 * @Route("/{month}/{year}", name="BNSAppLiaisonBookBundle_front", requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
	 * @Rights("LIAISONBOOK_ACCESS")
	 */
	public function indexAction($month, $year)
	{
		$right_manager = $this->get('bns.right_manager');
		//Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
		$context = $right_manager->getContext();

		//Récupération de la liste des liaisons books du groupe avec une selection de date (mois/année)
		if($month == 0 || $year == 0) {
			$month = date("n");//mois courant
			$year = date("Y");//année courante
		}
		
		$date = mktime(0, 0, 0, $month, 1, $year);
		$liaison_book_manager = $this->get('bns.liaison_book_manager');
		$liaisonBooks = $liaison_book_manager->getLiaisonBooksByGroupIdAndDate($context['id'], $month, $year);

		//L'utilisateur peut-il signer ?
		$canSign = $right_manager->hasRight('LIAISONBOOK_ACCESS_SIGN');

		//Couple new/liste des id de signatures
		$listSignatures = array();
		//Liste avec le carnet de liaison et la liste des utilisateurs ayant signés
		foreach ($liaisonBooks as $liaisonBook) {
			$elementList = array();
			$elementList['liaisonBookId'] = $liaisonBook->getId();
			$elementListSignature = array();

			//Pour chaque utilisateur on récupère la signature
			foreach ($liaisonBook->getLiaisonBookSignatures() as $signature) {
				$elementListSignature[] = $signature->getUser()->getId();
			}

			$elementList['signatures'] = $elementListSignature;

			$listSignatures[] = $elementList;
		}

		return $this->render('BNSAppLiaisonBookBundle:Front:index.html.twig', array(
			'context'			=> $context, 
			'news'				=> $liaisonBooks, 
			'canSign'			=> $canSign, 
			'listSignatures'	=> $listSignatures, 
			'newsDate'			=> $date
		));
	}
        
	/**
	 * Action signature d'un liaisonBook
	 * 
	 * @Route("/signer-message/{liaisonBookId}", name="BNSAppLiaisonBookBundle_front_sign", options={"expose"=true})
	 * @Rights("LIAISONBOOK_ACCESS_SIGN")
	 */
	public function signAction($liaisonBookId)
	{
		$right_manager = $this->get('bns.right_manager');
		$liaison_book_manager = $this->get('bns.liaison_book_manager');

		//Recupération des éléments
		$liaisonBook = $liaison_book_manager->getLiaisonBooksById($liaisonBookId);
		$user = $right_manager->getModelUser();
		$context = $right_manager->getContext();
		$currentGroupId = $context['id'];

		if ($user == null || $liaisonBook == null || $currentGroupId != $liaisonBook->getGroupId()) {
			throw new NotFoundHttpException("Mmmmh, ça c'est de la triche petit malin !");
		}

		//Signer
		$liaison_book_manager->signLiaisonBook($user, $liaisonBook);

		return new Response(json_encode(true));
	}
       
}


<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\LiaisonBookBundle\Form\LiaisonBookType;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;

class BackController extends Controller
{
    /**
	 * Page d'accueil du module : affiche le module et les bonnes pratiques à prendre
	 * Nommage "BNSAppLiaisonBookBundle_back" obligatoire, généralement sur le "/" (pas forcément)
	 * 
	 * @Route("/{month}/{year}", name="BNSAppLiaisonBookBundle_back", requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
	 * @Rights("LIAISONBOOK_ACCESS_BACK")
	 */
	public function indexAction($month, $year)
	{		
		$right_manager = $this->get('bns.right_manager');

		//Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
		$context = $right_manager->getContext();

		//Récupération de la liste des liaisons books du groupe avec une selection de date (mois/année)
		if ($month == 0 || $year == 0) {
			$month = date("n");//mois courant
			$year = date("Y");//année courante
		}
		
		$date = mktime(0, 0, 0, $month, 1, $year);
		$liaison_book_manager = $this->get('bns.liaison_book_manager');
		$liaisonBooks = $liaison_book_manager->getLiaisonBooksByGroupIdAndDate($context['id'], $month, $year);

		//Récupération nombre total de signatures attendu (parents dans le groupe)
		$group_manager = $this->get('bns.group_manager');
                $group_manager->setGroup($right_manager->getCurrentGroup());
                $users = $group_manager->getUsersByRoleUniqueName('PARENT', true, null);//$right_manager->getUsersThatHaveThePermissionInGroup('LIAISONBOOK_ACCESS_SIGN', $context['id']);

		//Nombre total de signatures attendues
		$totalSignatures = count($users);

		//Liste des dates archives
		$datesArchives = array();
		for ($index = 0; $index < 6; $index++) {
			$dateArchive = strtotime('-'.$index.' month', $date);
			$datesArchives[] = $dateArchive;
		}
                
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

		return $this->render('BNSAppLiaisonBookBundle:Back:index.html.twig', array(
			'context'			=> $context, 
			'news'				=> $liaisonBooks, 
			'totalSignatures'	=> $totalSignatures, 
			'listSignatures'	=> $listSignatures, 
			'newsDate'			=> $date, 
			'datesArchives'		=> $datesArchives
		));
	}
        
    /**
	 * @Route("/nouveau-message", name="BNSAppLiaisonBookBundle_back_create")
	 * @Rights("LIAISONBOOK_ACCESS_BACK")
	 */
	public function newMessageAction()
	{
		$month = date("n");//mois courant
		$year = date("Y");//année courante
		$date = mktime(0, 0, 0, $month, 1, $year);

		return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
			'form'			=> $this->createForm(new LiaisonBookType(), new LiaisonBook())->createView(),
			'isEditionMode'	=> false,
			'newsDate'		=> $date
		));
	}
	
    /**
	 * @Route("/nouveau-message/valider", name="BNSAppLiaisonBookBundle_back_create_finish")
	 * @Rights("LIAISONBOOK_ACCESS_BACK")
	 */
	public function finishNewMessageAction()
	{
                $month = date("n");//mois courant
		$year = date("Y");//année courante
		$date = mktime(0, 0, 0, $month, 1, $year);
		if ('POST' == $this->getRequest()->getMethod()) {
			$context = $this->get('bns.right_manager')->getContext();
			$form = $this->createForm(new LiaisonBookType(), new LiaisonBook());
			$form->bindRequest($this->getRequest());

			if ($form->isValid()) {
				$liaisonBook = $form->getData();
				$liaisonBook->setDate(new \DateTime());
				$liaisonBook->setGroupId($context['id']);

				// Finally
				$liaisonBook->save();
				$this->get('bns.resource_manager')->saveAttachments($liaisonBook,$this->getRequest());

				/*
				* Pour les Flash : notice, notice_warning, notice_success, notice_error
				*/
				$this->get('session')->setFlash('notice_success_msg_only',"Votre message a bien été créé");
			}
			else {
				return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
                                        'form'			=> $form->createView(),
                                        'isEditionMode'	=> false,
                                        'newsDate'		=> $date
                                ));
			}
		}

		return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back'));
	}
	
	/**
	 * @Route("/editer-message/{liaisonBookId}", name="BNSAppLiaisonBookBundle_back_edit", options={"expose"=true})
	 * @Rights("LIAISONBOOK_ACCESS_BACK")
	 */
	public function editMessageAction($liaisonBookId)
	{
		$liaisonBook = LiaisonBookQuery::create()->findOneById($liaisonBookId);
		if (null == $liaisonBook) {
				throw new NotFoundHttpException('LiaisonBook not found for id : ' . $liaisonBookId . ' !');
		}

                $right_manager = $this->get('bns.right_manager');
                $right_manager->forbidIfHasNotRight('LIAISONBOOK_ACCESS_BACK', $liaisonBook->getGroupId());
                
		$isEditionMode = true;
		$form = $this->createForm(new LiaisonBookType($isEditionMode), $liaisonBook);
		$month = date("n");//mois courant
		$year = date("Y");//année courante
		$date = mktime(0, 0, 0, $month, 1, $year);
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bindRequest($this->getRequest());
			if ($form->isValid()) {
				$liaisonBook = $form->getData();

				// Finally
				$liaisonBook->save();
				//Gestion des PJ
				$this->get('bns.resource_manager')->saveAttachments($liaisonBook,$this->getRequest());

				/*
				* Pour les Flash : notice, notice_warning, notice_success, notice_error
				*/
				$this->get('session')->setFlash('notice_success_msg_only',"Votre message a bien été modifié");

				return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back'));
			}
		}

		return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
			'form'			=> $form->createView(),
			'isEditionMode'	=> $isEditionMode,
			'newsDate'		=> $date
		));
	}
        
    /**
	 * @Route("/supprimer-message/{liaisonBookId}", name="BNSAppLiaisonBookBundle_back_delete", options={"expose"=true})
	 * @Rights("LIAISONBOOK_ACCESS_BACK")
	 */
	public function deleteMessageAction($liaisonBookId)
	{
            $right_manager = $this->get('bns.right_manager');
            
		$liaisonBook = LiaisonBookQuery::create()->findOneById($liaisonBookId);
		if (null == $liaisonBook) {
			throw new NotFoundHttpException('LiaisonBook not found for id : ' . $liaisonBookId . ' !');
		}

                $right_manager->forbidIfHasNotRight('LIAISONBOOK_ACCESS_BACK', $liaisonBook->getGroupId());

		$liaisonBook->delete();

		return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back'));
	}

}
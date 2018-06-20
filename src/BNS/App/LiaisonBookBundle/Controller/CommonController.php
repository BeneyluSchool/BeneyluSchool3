<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use Symfony\Component\VarDumper\VarDumper;

class CommonController extends Controller
{

        /**
	 * Render template archive front
	 */
	public function archivesAction($newsDate, $admin, $isParent=false)
	{
		$right_manager = $this->get('bns.right_manager');

		//Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
		$context = $right_manager->getContext();
		$lbnumber = array();

		//Récupération de la liste des dates des archives à partir de la date courante pour 6 mois
		$month = date("n");//mois courant
		$year = date("Y");//année courante
		$date = mktime(0, 0, 0, $month, 1, $year);

		//Date du mois en cours (normal, même si il n'y a pas de message)
		$datesArchives[] = $date;
		$lbnumber[$date] = 0;

		//Récupération des messages pour lister les dates
		$liaison_book_manager = $this->get('bns.liaison_book_manager');
		$liaisonBooks = $liaison_book_manager->getLiaisonBooksByGroupIdAndLessOneYear($context['id'], $this->getUser());

		foreach ($liaisonBooks as $liaisonBook) {
			$lbDate = $liaisonBook->getDate()->getTimestamp();
			$m = date("n", $lbDate);
			$y = date("Y", $lbDate);
			$lbDateToAdd = mktime(0, 0, 0, $m, 1, $y);
			if (!in_array($lbDateToAdd, $datesArchives)) {
				$datesArchives[] = $lbDateToAdd;
			}

			$user = $right_manager->getModelUser()->getId();
			$elementListSignature = array();
			foreach ($liaisonBook->getLiaisonBookSignatures() as $signature) {
				$elementListSignature[] = $signature->getUser()->getId();
			}

			if(!in_array($user ,$elementListSignature)){
				if(isset($lbnumber[$lbDateToAdd]))
					$lbnumber[$lbDateToAdd] += 1;
				else
					$lbnumber[$lbDateToAdd] = 1;
			}
			else {
				if(!isset($lbnumber[$lbDateToAdd]))
				$lbnumber[$lbDateToAdd] = 0;
			}

			rsort($datesArchives);
		}

		if($admin) {
			//Check rights
			$right_manager->forbidIfHasNotRight("LIAISONBOOK_ACCESS_BACK", $right_manager->getCurrentGroupId());

			return $this->render('BNSAppLiaisonBookBundle:Back:block_archives_back.html.twig', array(
				'datesArchives' => $datesArchives,
				'newsDate' => $newsDate
			));
		}
		else {
			//Check rights
			$right_manager->forbidIfHasNotRight("LIAISONBOOK_ACCESS", $right_manager->getCurrentGroupId());

			return $this->render('BNSAppLiaisonBookBundle:Front:block_archives.html.twig', array(
				'groupName' => $context['group_name'],
				'datesArchives' => $datesArchives,
				'lbnumber' => $lbnumber,
				'newsDate' => $newsDate,
				'isParent' => $isParent
			));
		}
	}

}


<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;

class CommonController extends Controller
{	

        /**
	 * Render template archive front
	 */
	public function archivesAction($newsDate, $admin)
	{
		$right_manager = $this->get('bns.right_manager');

		//Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
		$context = $right_manager->getContext();


		//Récupération de la liste des dates des archives à partir de la date courante pour 6 mois
		$month = date("n");//mois courant
		$year = date("Y");//année courante
		$date = mktime(0, 0, 0, $month, 1, $year);

		//Date du mois en cours (normal, même si il n'y a pas de message)
		$datesArchives[] = $date;

		//Récupération des messages pour lister les dates
		$liaison_book_manager = $this->get('bns.liaison_book_manager');
		$liaisonBooks = $liaison_book_manager->getLiaisonBooksByGroupIdAndLessOneYear($context['id']);

		foreach ($liaisonBooks as $liaisonBook) {
			$lbDate = $liaisonBook->getDate()->getTimestamp();
			$m = date("n", $lbDate);
			$y = date("Y", $lbDate);
			$lbDateToAdd = mktime(0, 0, 0, $m, 1, $y);
			if (!in_array($lbDateToAdd, $datesArchives)) {
				$datesArchives[] = $lbDateToAdd;
			}
		}

                if($admin)
                {
                    //Check rights
                    $right_manager->forbidIfHasNotRight("LIAISONBOOK_ACCESS_BACK", $right_manager->getCurrentGroupId());
                    
                    return $this->render('BNSAppLiaisonBookBundle:Back:block_archives_back.html.twig', array(
			'datesArchives' => $datesArchives, 
			'newsDate' => $newsDate
                    ));
                }
                else
                {
                    //Check rights
                    $right_manager->forbidIfHasNotRight("LIAISONBOOK_ACCESS", $right_manager->getCurrentGroupId());
                    
                    return $this->render('BNSAppLiaisonBookBundle:Front:block_archives.html.twig', array(
			'datesArchives' => $datesArchives, 
			'newsDate' => $newsDate
                    ));
                }
                
		
	}
}


<?php

namespace BNS\App\NoteBookBundle\Controller;

use BNS\App\NoteBookBundle\Model\NoteBookQuery;

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
        $rightManager = $this->get('bns.right_manager');

        //Récupération de la liste des dates des archives à partir de la date courante pour 6 mois
        $month = date("n");//mois courant
        $year = date("Y");//année courante
        $date = mktime(0, 0, 0, $month, 1, $year);

        $datesArchives = NoteBookQuery::create()->filterByGroupId($rightManager->getCurrentGroupId())->findDistinctMonth();
        if (!$datesArchives->contains(date('Y-m-01', $date))) {
            //Date du mois en cours (normal, même si il n'y a pas de message)
            $datesArchives[] = $date;
        }

        if ($admin) {
            //Check rights
            $rightManager->forbidIfHasNotRight("NOTEBOOK_ACCESS_BACK");

            return $this->render('BNSAppNoteBookBundle:Back:block_archives_back.html.twig', array('datesArchives' => $datesArchives, 'newsDate' => $newsDate));
        } else {
            //Check rights
            $rightManager->forbidIfHasNotRight("NOTEBOOK_ACCESS");

            return $this->render('BNSAppNoteBookBundle:Front:block_archives.html.twig', array('datesArchives' => $datesArchives, 'newsDate' => $newsDate));
        }

    }

}


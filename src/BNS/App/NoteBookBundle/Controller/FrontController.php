<?php

namespace BNS\App\NoteBookBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\NoteBookBundle\Model\NoteBookQuery;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends Controller
{
    /**
     * Nommage "BNSAppNoteBookBundle_front" obligatoire, généralement sur le "/" (pas forcément)
     *
     * @Route("/{month}/{year}", name="BNSAppNoteBookBundle_front", requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
     * @Route("/message/{slug}", name="note_book_message", defaults={"month" = 0, "year" = 0})
     * @Rights("NOTEBOOK_ACCESS")
     */
    public function indexAction($month, $year, $slug = null)
    {
        //Récupération de la liste des notes books du groupe avec une selection de date (mois/année)
        if (0 == $month || 0 == $year) {
            $month = date("n");//mois courant
            $year = date("Y");//année courante
        }

        $noteBooks = NoteBookQuery::create()
            ->orderByDate(\Criteria::DESC)
            ->filterByGroupId($this->get('bns.right_manager')->getCurrentGroupId())
            ->filterByMonthAndYear($month, $year)
            ->find()
        ;

        return $this->render('BNSAppNoteBookBundle:Front:index.html.twig', array(
                'news' => $noteBooks,
                'newsDate' => mktime(0, 0, 0, $month, 1, $year),
                'slug' => $slug));
    }
}


<?php

namespace BNS\App\NoteBookBundle\Controller;

use BNS\App\CoreBundle\Right\BNSRightManager;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\NoteBookBundle\Model\NoteBook;
use BNS\App\NoteBookBundle\Model\NoteBookQuery;
use BNS\App\NoteBookBundle\Form\NoteBookType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BackController extends Controller
{
    /**
     * Nommage "BNSAppNoteBookBundle_back" obligatoire, généralement sur le "/" (pas forcément)
     *
     * @Route("/{month}/{year}", name="BNSAppNoteBookBundle_back", options={"expose"=true}, requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function indexAction(Request $request, $month, $year)
    {
        $right_manager = $this->get('bns.right_manager');

        //Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
        $context = $right_manager->getContext();

        $session = $this->get('session');
        if ($month != 0 && $year != 0) {
            $session->set("notebook-archive-date-month", $month);
            $session->set("notebook-archive-date-year", $year);
        }

        $sessionMonth = $session->get('notebook-archive-date-month', date('n'));
        $sessionYear = $session->get('notebook-archive-date-year', date('Y'));

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        $noteBooks = NoteBookQuery::create()
            ->orderByDate(\Criteria::DESC)
            ->filterByGroupId($context['id'])
            ->filterByMonthAndYear($sessionMonth, $sessionYear)
            ->find()
        ;

        return $this->render('BNSAppNoteBookBundle:Back:index.html.twig', array(
                'news' => $noteBooks,
                'newsDate' => $date,
                'datesArchives' => NoteBookQuery::create()->filterByGroupId($context['id'])->findDistinctMonth()
                ));
    }

    /**
     * @Route("/nouveau-message", name="BNSAppNoteBookBundle_back_create")
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function newMessageAction(Request $request)
    {
        $sessionMonth = $this->get('session')->get("notebook-archive-date-month", date('n'));
        $sessionYear = $this->get('session')->get("notebook-archive-date-year", date('Y'));

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);
        $noteBook = new NoteBook();
        $noteBook->setDate(new \DateTime());

        return $this->render('BNSAppNoteBookBundle:Back:new_message.html.twig', array(
                'form' => $this->createForm(new NoteBookType(), $noteBook)->createView(),
                'isEditionMode' => false,
                'newsDate' => $date
                ));
    }

    /**
     * @Route("/detail/{slug}", name="BNSAppNoteBookBundle_back_detail", options={"expose"=true})
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function detailMessageAction($slug)
    {
        $sessionMonth = $this->get('session')->get("notebook-archive-date-month", date('n'));
        $sessionYear = $this->get('session')->get("notebook-archive-date-year", date('Y'));

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        $new = NoteBookQuery::create()->filterBySlug($slug)->filterByGroupId($this->get('bns.right_manager')->getCurrentGroupId())->findOne();
        if (!$new) {
            throw $this->createNotFoundException(sprintf('the notebook with slug %s does not exist', $slug));
        }

        return $this->render('BNSAppNoteBookBundle:Back:detail_message.html.twig', array(
                'new' => $new,
                'newsDate' => $date,
                ));
    }

    /**
     * @Route("/nouveau-message/valider", name="BNSAppNoteBookBundle_back_create_finish")
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function finishNewMessageAction(Request $request)
    {
        $sessionMonth = $this->get('session')->get("notebook-archive-date-month", date('n'));
        $sessionYear = $this->get('session')->get("notebook-archive-date-year", date('Y'));

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        if ($request->isMethod('post')) {
            $context = $this->get('bns.right_manager')->getContext();
            $noteBook = new NoteBook();
            $form = $this->createForm(new NoteBookType(), $noteBook);
            $form->bind($request);
            $this->get('bns.media.manager')->bindAttachments($noteBook, $request);
            if ($form->isValid()) {
                $noteBook = $form->getData();
                $noteBook->setGroupId($context['id']);
                $noteBook->setAuthorId($this->get('bns.right_manager')->getUserSessionId());

                // Finally
                $noteBook->save();
                $this->get('bns.media.manager')->saveAttachments($noteBook, $this->getRequest());

                /*
                 * Pour les Flash : notice, notice_warning, notice_success, notice_error
                 */
                $this->get('session')->getFlashBag()->add('notice_success_msg_only', "Votre message a été enregistré avec succès");

                return $this->redirect($this->generateUrl('BNSAppNoteBookBundle_back'));
            }
        }

        return $this->render('BNSAppNoteBookBundle:Back:new_message.html.twig', array(
                'form' => $form->createView(),
                'isEditionMode' => false,
                'newsDate' => $date,
                'errors' => $this->get('validator')->validate($form->getData())));
    }

    /**
     * @Route("/editer-message/{slug}", name="BNSAppNoteBookBundle_back_edit", options={"expose"=true})
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function editMessageAction(Request $request, $slug)
    {
        $noteBook = NoteBookQuery::create()
            ->filterBySlug($slug)
            ->filterByGroupId($this->get('bns.right_manager')->getCurrentGroupId())
            ->findOne()
        ;
        if (!$noteBook) {
            throw new NotFoundHttpException('NoteBook not found for slug : ' . $slug . ' !');
        }

        $form = $this->createForm(new NoteBookType(), $noteBook);

        $sessionMonth = $this->get('session')->get("notebook-archive-date-month", date('n'));
        $sessionYear = $this->get('session')->get("notebook-archive-date-year", date('Y'));

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);
        if ($request->isMethod('post')) {
            $form->bind($request);
            $this->get('bns.media.manager')->bindAttachments($noteBook, $request);
            if ($form->isValid()) {
                $noteBook = $form->getData();

                // Finally
                $noteBook->save();
                //Gestion des PJ
                $this->get('bns.media.manager')->saveAttachments($noteBook, $request);

                /*
                 * Pour les Flash : notice, notice_warning, notice_success, notice_error
                 */
                $this->get('session')->getFlashBag()->add('notice_success_msg_only', "Votre message a bien été modifié");

                return $this->redirect($this->generateUrl('BNSAppNoteBookBundle_back_detail', array('slug' => $noteBook->getSlug())));
            }
        }

        return $this->render('BNSAppNoteBookBundle:Back:new_message.html.twig', array(
                'form' => $form->createView(),
                'isEditionMode' => true,
                'newsDate' => $date,
                'errors' => $this->get('validator')->validate($form->getData())
                ));
    }

    /**
     * @Route("/supprimer-message/{slug}", name="BNSAppNoteBookBundle_back_delete", options={"expose"=true})
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function deleteMessageAction($slug)
    {
        $noteBook = NoteBookQuery::create()
            ->filterBySlug($slug)
            ->filterByGroupId($this->get('bns.right_manager')->getCurrentGroupId())
            ->findOne();

        if (!$noteBook) {
            throw new NotFoundHttpException('NoteBook not found for slug : ' . $slug . ' !');
        }

        $noteBook->delete();

        /*
         * Pour les Flash : notice, notice_warning, notice_success, notice_error
         */
        $this->get('session')->getFlashBag()->add('notice_success_msg_only', "Votre message a bien été supprimé");

        return $this->redirect($this->generateUrl('BNSAppNoteBookBundle_back'));
    }

    /**
     * @Route("/exporter/{date}", name="BNSAppNoteBookBundle_back_export", options={"expose"=true})
     * @Rights("NOTEBOOK_ACCESS_BACK")
     */
    public function exportAction(Request $request, $date)
    {
        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));

        $messages = NoteBookQuery::create()
            ->orderByDate()
            ->filterByGroupId($this->get('bns.right_manager')->getCurrentGroupId())
            ->filterByMonthAndYear($month, $year)
            ->find();

        $response = $this->render('BNSAppNoteBookBundle:Back:export.txt.twig', array(
                'messages' => $messages,
                'date' => $date
                ));
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="cahier_jounal_' . date('d-m-Y', strtotime($date)) .'.txt"');
        $response->setContent(str_replace("\n", "\r\n", $response->getContent()));

        return $response;
    }
}

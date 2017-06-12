<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\LiaisonBookBundle\Form\LiaisonBookType;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\UserQuery;use BNS\App\MessagingBundle\Form\Type\MessageType;

class BackController extends Controller
{

    /**
     * Page d'accueil du module : affiche le module et les bonnes pratiques à prendre
     * Nommage "BNSAppLiaisonBookBundle_back" obligatoire, généralement sur le "/" (pas forcément)
     *
     * @Route("/{month}/{year}", name="BNSAppLiaisonBookBundle_back", options={"expose"=true}, requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function indexAction($month, $year)
    {
        $right_manager = $this->get('bns.right_manager');

        //Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
        $context = $right_manager->getContext();

        //Récupération de la liste des liaisons books du groupe avec une selection de date (mois/année)
        $this->initDateSessionIfNotSet();

        if (0 === $month && 0 === $year) {
            $this->get('stat.liaisonbook')->visit();
        }

        if ($month != 0 && $year != 0) {
            $this->getRequest()->getSession()->set("liaisonbook-archive-date-month", $month);
            $this->getRequest()->getSession()->set("liaisonbook-archive-date-year", $year);
        }

        $sessionMonth = $this->getRequest()->getSession()->get("liaisonbook-archive-date-month");
        $sessionYear = $this->getRequest()->getSession()->get("liaisonbook-archive-date-year");

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        $liaison_book_manager = $this->get('bns.liaison_book_manager');
        $liaisonBooks = $liaison_book_manager->getLiaisonBooksByGroupIdAndDate($context['id'], $sessionMonth, $sessionYear);

        //Récupération nombre total de signatures attendu (parents dans le groupe)
        $group_manager = $this->get('bns.group_manager');
        $group_manager->setGroup($right_manager->getCurrentGroup());
        $users = $right_manager->getUsersThatHaveThePermissionInGroup('LIAISONBOOK_ACCESS_SIGN', $context['id']);//$group_manager->getUsersByRoleUniqueName('PARENT', true, null); //
        //Nombre total de signatures attendues
        $totalSignatures = count($users);

        //Liste des dates archives
        $datesArchives = array();
        for ($index = 0; $index < 6; $index++) {
            $dateArchive = strtotime('-' . $index . ' month', $date);
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
            'context' => $context,
            'news' => $liaisonBooks,
            'totalSignatures' => $totalSignatures,
            'listSignatures' => $listSignatures,
            'newsDate' => $date,
            'datesArchives' => $datesArchives
        ));
    }

    /**
     * @Route("/nouveau-message", name="BNSAppLiaisonBookBundle_back_create")
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function newMessageAction()
    {
        $this->initDateSessionIfNotSet();
        $sessionMonth = $this->getRequest()->getSession()->get("liaisonbook-archive-date-month");
        $sessionYear = $this->getRequest()->getSession()->get("liaisonbook-archive-date-year");

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);
        $liaisonBook = new LiaisonBook();
        $liaisonBook->setDate(new \DateTime());

        return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
            'form' => $this->createForm(new LiaisonBookType(), $liaisonBook)->createView(),
            'isEditionMode' => false,
            'newsDate' => $date
        ));
    }

    /**
     * @Route("/detail/{slug}", name="BNSAppLiaisonBookBundle_back_detail", options={"expose"=true})
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function detailMessageAction($slug)
    {
        $this->initDateSessionIfNotSet();
        $sessionMonth = $this->getRequest()->getSession()->get("liaisonbook-archive-date-month");
        $sessionYear = $this->getRequest()->getSession()->get("liaisonbook-archive-date-year");

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        $new = LiaisonBookQuery::create()->filterBySlug($slug)->findOne();

        //Récupération nombre total de signatures attendu (parents dans le groupe)
        $right_manager = $this->get('bns.right_manager');
        $group_manager = $this->get('bns.group_manager');

        if ($right_manager->getCurrentGroupId() != $new->getGroupId()) {
            throw new NotFoundHttpException("Mmmmh, ça c'est de la triche petit malin !");
        }

        $group_manager->setGroup($right_manager->getCurrentGroup());
        $users = $right_manager->getUsersThatHaveThePermissionInGroup('LIAISONBOOK_ACCESS_SIGN', $right_manager->getCurrentGroupId());//$group_manager->getUsersByRoleUniqueName('PARENT', true, null); //
        //Nombre total de signatures attendues
        $totalSignatures = count($users);

        $usersNotSign = array();
        $usersNotSignIds = array();
        $usersSign = array();

        //Récupération des personnes ayant signé
        foreach($new->getLiaisonBookSignatures() as $signature)
        {
            $usersSign[] = $signature->getUserId();
        }

        //Déduction des personnes n'ayant pas signé
        foreach ($users as $u) {
            if(!in_array($u['id'], $usersSign))
            {
                $usersNotSignIds[] = $u['id'];
            }
        }

        $usersNotSign = UserQuery::create()->filterById($usersNotSignIds, \Criteria::IN)->find();

        return $this->render('BNSAppLiaisonBookBundle:Back:detail_message.html.twig', array(
            'new' => $new,
            'newsDate' => $date,
            'totalSignatures' => $totalSignatures,
            'usersNotSign' => $usersNotSign
        ));
    }

    /**
     * @Route("/nouveau-message/valider", name="BNSAppLiaisonBookBundle_back_create_finish")
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function finishNewMessageAction()
    {
        $this->initDateSessionIfNotSet();
        $sessionMonth = $this->getRequest()->getSession()->get("liaisonbook-archive-date-month");
        $sessionYear = $this->getRequest()->getSession()->get("liaisonbook-archive-date-year");

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);

        if ('POST' == $this->getRequest()->getMethod()) {
            $context = $this->get('bns.right_manager')->getContext();
            $liaisonBook = new LiaisonBook();
            $form = $this->createForm(new LiaisonBookType(),$liaisonBook);
            $form->bind($this->getRequest());
            $this->get('bns.media.manager')->bindAttachments($liaisonBook,$this->getRequest());

            if ($form->isValid()) {
                $liaisonBook = $form->getData();
                $liaisonBook->setGroupId($context['id']);
                $liaisonBook->setAuthorId($this->get('bns.right_manager')->getUserSessionId());

                // Finally
                $liaisonBook->save();
                $this->get('bns.media.manager')->saveAttachments($liaisonBook, $this->getRequest());

                //statistic action
                $this->get("stat.liaisonbook")->newMessage();

                /*
                 * Pour les Flash : notice, notice_warning, notice_success, notice_error
                 */
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_MESSAGE_SAVE_SUCCESS', array(), 'LIAISONBOOK'));
            } else {
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_ERROR_OCCURED', array(), 'LIAISONBOOK'));

                return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
                    'form' => $form->createView(),
                    'isEditionMode' => false,
                    'newsDate' => $date,
                    'errors' => $this->get('validator')->validate($form->getData())
                ));
            }
        }

        return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back'));
    }

    /**
     * @Route("/editer-message/{slug}", name="BNSAppLiaisonBookBundle_back_edit", options={"expose"=true})
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function editMessageAction($slug)
    {
        $liaisonBook = LiaisonBookQuery::create()->filterBySlug($slug)->findOne();
        if (null == $liaisonBook) {
            throw new NotFoundHttpException('LiaisonBook not found for slug : ' . $slug . ' !');
        }

        $right_manager = $this->get('bns.right_manager');
        $right_manager->forbidIfHasNotRight('LIAISONBOOK_ACCESS_BACK', $liaisonBook->getGroupId());

        if ($right_manager->getCurrentGroupId() != $liaisonBook->getGroupId()) {
            throw new NotFoundHttpException("Mmmmh, ça c'est de la triche petit malin !");

        }

        $isEditionMode = true;
        $form = $this->createForm(new LiaisonBookType($isEditionMode), $liaisonBook);

        $this->initDateSessionIfNotSet();
        $sessionMonth = $this->getRequest()->getSession()->get("liaisonbook-archive-date-month");
        $sessionYear = $this->getRequest()->getSession()->get("liaisonbook-archive-date-year");

        $date = mktime(0, 0, 0, $sessionMonth, 1, $sessionYear);
        if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());
            $this->get('bns.media.manager')->bindAttachments($liaisonBook,$this->getRequest());
            if ($form->isValid()) {
                $liaisonBook = $form->getData();

                // Finally
                $liaisonBook->save();
                //Gestion des PJ
                $this->get('bns.media.manager')->saveAttachments($liaisonBook, $this->getRequest());

                /*
                 * Pour les Flash : notice, notice_warning, notice_success, notice_error
                 */
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_MESSAGE_MODIFIED_SUCCESS', array(), 'LIAISONBOOK'));

                return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back_detail', array('slug' => $liaisonBook->getSlug()) ));
            }
            else
            {
                $this->get('session')->getFlashBag()->add('error',  $this->get('translator')->trans('FLASH_ERROR_OCCURED', array(), 'LIAISONBOOK'));

            }
        }

        return $this->render('BNSAppLiaisonBookBundle:Back:new_message.html.twig', array(
            'form' => $form->createView(),
            'isEditionMode' => $isEditionMode,
            'newsDate' => $date,
            'errors' => $this->get('validator')->validate($form->getData())
        ));
    }

    /**
     * @Route("/supprimer-message/{slug}", name="BNSAppLiaisonBookBundle_back_delete", options={"expose"=true})
     * @Rights("LIAISONBOOK_ACCESS_BACK")
     */
    public function deleteMessageAction($slug)
    {
        $right_manager = $this->get('bns.right_manager');

        $liaisonBook = LiaisonBookQuery::create()->filterBySlug($slug)->findOne();

        if (null == $liaisonBook) {
            throw new NotFoundHttpException('LiaisonBook not found for slug : ' . $slug . ' !');
        }

        $right_manager->forbidIfHasNotRight('LIAISONBOOK_ACCESS_BACK', $liaisonBook->getGroupId());

        if ($right_manager->getCurrentGroupId() != $liaisonBook->getGroupId()) {
            throw new NotFoundHttpException("Mmmmh, ça c'est de la triche petit malin !");

        }

        $liaisonBook->delete();

        /*
        * Pour les Flash : notice, notice_warning, notice_success, notice_error
        */
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_MESSAGE_DELETE_SUCCESS', array(), 'LIAISONBOOK'));

        return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_back'));
    }

    public function initDateSessionIfNotSet()
    {
        //Récupération de la liste des liaisons books du groupe avec une selection de date (mois/année)
        if ($this->getRequest()->getSession()->get("liaisonbook-archive-date-month") == null || $this->getRequest()->getSession()->get("liaisonbook-archive-date-year") == null) {
            $month = date("n"); //mois courant
            $year = date("Y"); //année courante
            $this->getRequest()->getSession()->set("liaisonbook-archive-date-month", $month);
            $this->getRequest()->getSession()->set("liaisonbook-archive-date-year", $year);
        }
    }
}

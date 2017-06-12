<?php

namespace BNS\App\HomeworkBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\HomeworkBundle\Form\Type\HomeworkType;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;

class BackAjaxController extends Controller
{

    /**
     * Gestion des occurences de devoirs d'une semaine donnee.
     * (recupere la liste des occurences et prepare leur gestion)
     * @Route("/gestion-semaine/{day}/{firstLoad}", name="BNSAppHomeworkBundle_backajax_manage_week", defaults={"firstLoad" = false}, options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function manageForWeekBlockAction($day, $firstLoad)
    {
        if ($this->getRequest()->isXmlHttpRequest() || $firstLoad) {
			$subjects_ids = $this->getRequest()->getSession()->get("subjects-session");
			$groups_ids = $this->getRequest()->getSession()->get("groups-session");
			$days_ids = $this->getRequest()->getSession()->get("weekdays-session");

			// vérification des droits d'accès
			$right_manager = $this->get('bns.right_manager');

			// calcul des dates de: début et fin de semaine, semaines suivante et précédente
			$start_day = strtotime($day);
			$end_day = strtotime("+7 days", $start_day);
			$previous_week = date("d-m-Y", strtotime("monday last week", $start_day));
			$next_week = date("d-m-Y", strtotime("monday next week", $start_day));

			// récupération des données travaux, sujets, matières
			$groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
			$homeworkdues = HomeworkDueQuery::create()->findForRangeForGroups($start_day, $end_day, $groups, $subjects_ids, $groups_ids, $days_ids);
			$due_sorted = $this->groupHomeworkDuesByDate($homeworkdues);

			// Récupérer les préférences pour afficher les jours et les travaux passés
			$preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

			return $this->render('BNSAppHomeworkBundle:Block:back_block_manageForWeek.html.twig', array(
				'day' => $day,
				'due_sorted' => $due_sorted,
				'start_day' => $start_day,
				'end_day' => $end_day,
				'previous_week' => $previous_week,
				'next_week' => $next_week,
				'preferences' => $preferences
			));
        }

		return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

    /**
     * Creation d'un formulaire d'ajout rapide de devoir pour un jour donne.
     * @Route("/devoirs/formulaire-rapide/{day}", name="BNSAppHomeworkBundle_backajax_quick_form", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function createQuickForm($day)
    {
        $right_manager = $this->get('bns.right_manager');
        $current_group_id = $right_manager->getCurrentGroupId();
        $groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

        $subjects = HomeworkSubjectQuery::create()
			->orderByTreeLeft()
		->findByGroupId($current_group_id);

        // set default values (given day, no recurrence)
        $homework = new Homework();
        $homework->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
        $homework->setDate($day);
        $homework->setRecurrenceEndDate($day);

        $homeworkform = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework)->createView();

        return $this->render('BNSAppHomeworkBundle:Block:back_block_quick_create.html.twig', array(
			'day' => $day,
			'homework_form' => $homeworkform
		));
    }


    /**
     * Ajout rapide d'un nouveau travail pour un/des groupes
     * @param Request $request
     * @Route("/devoirs/ajout-rapide", name="BNSAppHomeworkBundle_backajax_quick_add", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function quickAddHomeworkAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $right_manager = $this->get('bns.right_manager');
            $current_group_id = $right_manager->getCurrentGroupId();
            $groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

            $subjects = HomeworkSubjectQuery::create()
                ->orderByTreeLeft()
			->findByGroupId($current_group_id);

            $homework = new Homework();
            $form = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework);
            $form->bind($request);

            if ($form->isValid() && $homework->getGroups()->count() > 0) {

                $homework->save();

                // process this homework to create related dues and tasks
                $this->get('bns.homework_manager')->processHomework($homework);

                return new Response();
            }
			else {
                throw new HttpException(400, "Homework is not valid");
            }
        }

        $response = $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));

        return $response;
    }

    /**
     * Ajout d'une matiere
     * @Route("/matiere/ajouter", name="BNSAppHomeworkBundle_backajax_subject_add", options={"expose"=true})
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function addSubjectAction($isManage = false)
    {
        if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
            if ($this->getRequest()->get('subject_title', false) === false) {
                throw new \InvalidArgumentException('There is one missing mandatory field !');
            }

            $right_manager = $this->get('bns.right_manager');
            $current_group_id = $right_manager->getCurrentGroupId();

            $subject = new HomeworkSubject();

            $rootSubject = HomeworkSubject::fetchRoot($current_group_id);
            $subject->insertAsFirstChildOf($rootSubject);
            $subject->setGroupId($current_group_id);
            $subject->setName($this->getRequest()->get('subject_title', false));

            $errors = $this->get('validator')->validate($subject);
            if (isset($errors[0])) {
                throw new \InvalidArgumentException($errors[0]->getMessage());
            }

            $subject->save();

	    $view = 'BNSAppHomeworkBundle:Block:back_block_subject_row.html.twig';
	    if ($isManage) {
		    $view = 'BNSAppHomeworkBundle:Subject:back_subject_management_row.html.twig';
	    }
	    if ($this->getRequest()->get('quick_add') == true) {
		    $view = 'BNSAppHomeworkBundle:Subject:back_subject_management_row_form.html.twig';
            }

            return $this->render($view, array(
				'subject' => $subject
			));
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

	/**
	 * @Route("/matiere/ajouter/management", name="homework_manager_subject_add_management")
     * @Rights("HOMEWORK_ACCESS_BACK")
	 */
	public function addSubjectManageAction()
	{
		return $this->addSubjectAction(true);
	}

    /**
     * Edition d'une matiere existante
     * @Route("/matiere/edited", name="BNSAppHomeworkBundle_backajax_subject_edit", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function editSubjectAction(Request $request)
    {
        if ('POST' == $request->getMethod() && $request->isXmlHttpRequest()) {

            if ($request->get('subject_title', false) === false || $request->get('id', false) === false) {
                throw new \InvalidArgumentException('There is one missing mandatory field !');
            }

			$subjectId = $request->get('id');
            $subject = HomeworkSubjectQuery::create()->findPk($subjectId);

            if (null == $subject) {
                throw new NotFoundHttpException('The subject with id : ' . $subjectId . ' is not found !');
            }

            if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $subject->getGroupId())) {
                throw $this->createAccessDeniedException();
            }

            $subject->setName($request->get('subject_title'));
            $subject->save();

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
    }

    /**
     * Suppression d'une matiere
     * @Route("/matiere/supprimer", name="BNSAppHomeworkBundle_backajax_subject_delete", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function deleteSubjectAction()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
			if ($this->getRequest()->get('id', false) === false) {
                throw new \InvalidArgumentException('There is one missing mandatory field !');
            }

			$subjectId = $this->getRequest()->get('id');
            $subject = HomeworkSubjectQuery::create()->findPk($subjectId);

            if (null == $subject) {
                throw new NotFoundHttpException('The subject with id : ' . $subjectId . ' is not found !');
            }

            $right_manager = $this->get('bns.right_manager');
            $is_allowed = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $subject->getGroupId());
            $right_manager->forbidIf(!$is_allowed);

            $subject->delete();

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

    /**
     * Ajoute une matiere en session pour l'utiliser comme filtre d'affichage sur les devoirs
     * @Route("/matiere/session/{subjectId}/{add}", name="BNSAppHomeworkBundle_backajax_subject_session", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function sessionSubjectAction($subjectId, $add)
    {
        if ($this->getRequest()->isXmlHttpRequest()) {

            $subject = HomeworkSubjectQuery::create()->findPk($subjectId);
            if (null == $subject) {
                throw new NotFoundHttpException('The subject with id : ' . $subjectId . ' is not found !');
            }

            $right_manager = $this->get('bns.right_manager');
            $is_allowed = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $subject->getGroupId());
            $right_manager->forbidIf(!$is_allowed);

            $subjects_ids = $this->getRequest()->getSession()->get("subjects-session");

            if ($subjects_ids == null) {
                $subjects_ids = array();
            }

            if ($add == 1) {
                $subjects_ids[] = $subjectId;
            } else {
                $new_ids = array();
                foreach ($subjects_ids as $id) {
                    if ($id != $subjectId) {
                        $new_ids[] = $id;
                    }
                }
                $subjects_ids = $new_ids;
            }

            //Add subject in session
            $this->getRequest()->getSession()->set("subjects-session", $subjects_ids);

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

    /**
     * Ajoute un groupe en session pour l'utiliser comme filtre d'affichage sur les devoirs
     * @Route("/matiere/group/{groupId}/{add}", name="BNSAppHomeworkBundle_backajax_group_session", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function sessionGroupAction($groupId, $add)
    {
        if ($this->getRequest()->isXmlHttpRequest()) {

            $subject = GroupQuery::create()->findById($groupId);
            if (null == $subject) {
                throw new NotFoundHttpException('The group with id : ' . $groupId . ' is not found !');
            }

            $right_manager = $this->get('bns.right_manager');
            $is_allowed = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $groupId);
            $right_manager->forbidIf(!$is_allowed);

            $groups_ids = $this->getRequest()->getSession()->get("groups-session");

            if ($groups_ids == null) {
                $groups_ids = array();
            }

            if ($add == 1) {
                $groups_ids[] = $groupId;
            } else {
                $new_ids = array();
                foreach ($groups_ids as $id) {
                    if ($id != $groupId) {
                        $new_ids[] = $id;
                    }
                }
                $groups_ids = $new_ids;
            }

            //Add subject in session
            $this->getRequest()->getSession()->set("groups-session", $groups_ids);

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }


    /**
     * Ajoute un groupe en session pour l'utiliser comme filtre d'affichage sur les devoirs
     * @Route("/matiere/day/{dayId}/{add}", name="BNSAppHomeworkBundle_backajax_day_session", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function sessionDayAction($dayId, $add)
    {
        if ($this->getRequest()->isXmlHttpRequest()) {

            $right_manager = $this->get('bns.right_manager');
            $current_group_id = $right_manager->getCurrentGroupId();

            $is_allowed = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $current_group_id);
            $right_manager->forbidIf(!$is_allowed);

            $day_ids = $this->getRequest()->getSession()->get("weekdays-session");

            if ($day_ids == null) {
                $day_ids = array();
            }

            if ($add == 1) {
                $day_ids[] = $dayId;
            } else {
                $new_ids = array();
                foreach ($day_ids as $id) {
                    if ($id != $dayId) {
                        $new_ids[] = $id;
                    }
                }
                $day_ids = $new_ids;
            }

            //Add subject in session
            $this->getRequest()->getSession()->set("weekdays-session", $day_ids);

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

    /**
     *
     * @Route("/matiere/sauvegarder", name="homework_manager_subject_save", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function saveSubjectAction()
    {
        if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
            if ($this->getRequest()->get('left_id', false) === false
                    || $this->getRequest()->get('right_id', false) === false
                    || $this->getRequest()->get('subject_id', false) === false
                    || $this->getRequest()->get('parent_id', false) === false) {
                throw new \InvalidArgumentException('There is one missing mandatory field !');
            }

            $right_manager = $this->get('bns.right_manager');
            $current_group_id = $right_manager->getCurrentGroupId();
            $right_manager->forbidIfHasNotRight('HOMEWORK_ACCESS_BACK', $current_group_id);

            $subjectToSaveId = $this->getRequest()->get('subject_id');
            $leftId = $this->getRequest()->get('left_id');
            $rightId = $this->getRequest()->get('right_id');
            $parentId = $this->getRequest()->get('parent_id');

            //Save the changed subject
            $homeworkSubject = HomeworkSubjectQuery::create()->findOneById($subjectToSaveId);
            if ($homeworkSubject != null) {
                //Move next prev or first child
                if ($leftId != 'null') {
                    $homeworkSubject->moveToNextSiblingOf(HomeworkSubjectQuery::create()->findOneById($leftId));
                } else if ($rightId != 'null') {
                    $homeworkSubject->moveToPrevSiblingOf(HomeworkSubjectQuery::create()->findOneById($rightId));
                } else if ($parentId != 'null') {
                    $homeworkSubject->moveToFirstChildOf(HomeworkSubjectQuery::create()->findOneById($parentId));
                }
            }

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
    }

    /**
     * Regroupe les homeworkdues par jour pour l'affichage front
     * @param type $homeworkdues les homeworkdues à regrouper
     * @return type un array associatif (date -> array de homeworkdues)
     */
    protected function groupHomeworkDuesByDate($homeworkdues)
    {

        // regrouper les devoirs par jours pour l'affichage
        $homeworkdues_sorted = array();
        foreach ($homeworkdues as $hd) {
            if (array_key_exists($hd->getDueDate("Y-m-d"), $homeworkdues_sorted)) {
                array_push($homeworkdues_sorted[$hd->getDueDate("Y-m-d")], $hd);
            } else {
                $homeworkdues_sorted[$hd->getDueDate("Y-m-d")] = array($hd);
            }
        }

        return $homeworkdues_sorted;
    }

}


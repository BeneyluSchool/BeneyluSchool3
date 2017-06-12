<?php

namespace BNS\App\HomeworkBundle\Controller;

use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;
use BNS\App\HomeworkBundle\Form\Type\HomeworkPreferencesType;
use BNS\App\HomeworkBundle\Form\Type\SubjectType;
use BNS\App\HomeworkBundle\Form\Type\HomeworkType;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkQuery;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \DateTime;

class BackController extends Controller
{
    /**
     * Accueil de l'admin homework
     * @Route("/")
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function manageAction()
    {
        $this->get('stat.homework')->visit();

		$this_monday = new DateTime("monday this week");

		$response = $this->forward('BNSAppHomeworkBundle:Back:manageForWeek', array(
			'day' => $this_monday->format("d-m-Y"),
			));
		return $response;
    }

    /**
     * Affichage de la liste des travaux pour un/des groupes sur une semaine
     * @Route("/semaine/{day}", name="BNSAppHomeworkBundle_back_manage_for_week", options={"expose"=true})
     * @Template()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function manageForWeekAction($day)
    {
        $session = $this->get('session');
        $session->remove("subjects-session");
        $session->remove("groups-session");
        $session->remove("weekdays-session");

		// vérification des droits d'accès
		$right_manager = $this->get('bns.right_manager');
		$current_group_id = $right_manager->getCurrentGroupId();

		// récupération des données travaux, sujets, matières
		$groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);

		//Récupérer les préférences pour afficher les jours et les travaux passés
		$preferences = HomeworkPreferencesQuery::create()->findOrInit($current_group_id);

		return array(
			'subjects' => $subjects,
			'day' => $day,
			'groups' => $groups,
			'preferences' => $preferences
		);
    }

    /**
     * Ajout d'un nouveau travail pour un/des groupes
     * @param Request $request
     * @Route("ajouter-travail", name="BNSAppHomeworkBundle_back_add_homework")
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function addHomeworkAction(Request $request)
    {
		if ($request->getMethod() == 'POST') {
			$right_manager = $this->get('bns.right_manager');
			$current_group_id = $right_manager->getCurrentGroupId();
			$groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

			$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);

			//Récupérer les préférences pour afficher les jours et les travaux passés
			$preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

			$homework = new Homework();
			$form = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework);
			$form->bind($request);
			$this->get('bns.media.manager')->bindAttachments($homework,$this->getRequest());

            //statistic action
            $this->get("stat.homework")->newWork();

			if ($form->isValid() && $form->getData()->getGroups()->count() != 0) {
                $translator = $this->get('translator');
				$this->get('session')->getFlashBag()->add('notice_success_msg_only',$this->get('translator')->trans('FLASH_WORK_ADD_SUCCESS', array(), 'HOMEWORK'));
				$homework->save();
				$this->get('bns.media.manager')->saveAttachments($homework, $request);

				if ($homework->getHasLocker()) {
					$this->get('bns.media_folder.locker_manager')->createForHomework($homework);
				}

				// process this homework to create related dues and tasks
				$this->get('bns.homework_manager')->processHomework($homework);

				if ($form->get('createAnother')->getData() == 'false') {
					// renvoyer l'utilisateur vers la page contenant le premier due
					// pour ce devoir

					$next_homework_due = HomeworkDueQuery::create()->findNextOccuringHomeworkDue($homework->getId());

					if (null == $next_homework_due) {
					$redirect_to_day = new DateTime("monday this week");
					$redirect_to_day = $redirect_to_day->format("d-m-Y");
					} else {
					$redirect_to_day = $next_homework_due->getDueDate()->format("d-m-Y");
					}

					return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back_manage_for_week', array('day' => $redirect_to_day)));
				}else{
					// l'utilisateur souhaite creer plus de devoirs
					return $this->redirect(
                                                    $this->generateUrl('BNSAppHomeworkBundle_back_new_homework_date', array('day' => $homework->getDate()->format("d-m-Y")))
                                                );
				}
			}else{
				return $this->render('BNSAppHomeworkBundle:Back:editHomework.html.twig', array(
						'homework_form' => $form->createView(),
						'subjects' => $subjects,
						'groups' => $groups,
						'homework' => $homework,
						'preferences' => $preferences
					));
			}
		}

		$response = $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));

		return $response;
    }

    /**
     * Sauvegarde d'un travail existant pour un/des groupes
     * @param Request $request
     * @Route("sauvegarder-travail/{slug}", name="BNSAppHomeworkBundle_back_save_homework")
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function saveHomeworkAction($slug, Request $request)
    {
		$right_manager = $this->get('bns.right_manager');

		$current_group_id = $right_manager->getCurrentGroupId();
		$groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

		$homework = HomeworkQuery::create()->findOneBySlug($slug);
		if ($homework == null) {
			throw new NotFoundHttpException("Homework " . $slug . " not found");
		}

		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);

		//Récupérer les préférences pour afficher les jours et les travaux passés
		$preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());
		$form = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework);

		if ($request->getMethod() == 'POST') {

			$form->bind($request);
			$this->get('bns.media.manager')->bindAttachments($homework,$this->getRequest());

			if ($form->isValid() && $form->getData()->getGroups()->count() != 0) {

			if ($homework->isColumnModified(HomeworkPeer::RECURRENCE_TYPE) || $homework->isColumnModified(HomeworkPeer::DATE) || $homework->isColumnModified(HomeworkPeer::RECURRENCE_END_DATE)) {
				$homework->getHomeworkDues()->delete();
			}

			$this->get('session')->getFlashBag()->add('notice_success_msg_only', $this->get('translator')->trans('FLASH_WORK_MODIFIED', array(), 'HOMEWORK'));

			$homework->save();

			//Gestion des PJ
			$this->get('bns.media.manager')->saveAttachments($homework, $this->getRequest());

			if ($homework->getHasLocker()) {
				$this->get('bns.media_folder.locker_manager')->createForHomework($homework);
			}
			$this->get('bns.homework_manager')->processHomework($homework);
			} else {

			return $this->render('BNSAppHomeworkBundle:Back:editHomework.html.twig', array(
					'homework_form' => $form->createView(),
					'subjects' => $subjects,
					'groups' => $groups,
					'homework' => $homework,
					'preferences' => $preferences,
				));
			}
		}

		$response = $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));

		return $response;
    }

    /**
     * Edition d'un travail pour un/des groupes
     * @param Request $request
     * @Route("/travail/{slug}", name="BNSAppHomeworkBundle_back_edit_homework")
     * @Template()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function editHomeworkAction($slug, $day = null)
    {
		$right_manager = $this->get('bns.right_manager');
		$available_groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
		$current_group_id = $right_manager->getCurrentGroupId();

		//Récupérer les préférences pour afficher les jours et les travaux passés
		$preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

        if ($slug) {
            // si le homework existe
            // on verifie si l'utilisateur a les droits sur un des groupes concernes
            $homework = HomeworkQuery::create()->findOneBySlug($slug);
            $is_allowed = $right_manager->hasRightInSomeGroups('HOMEWORK_ACCESS_BACK', $homework->getGroups()->getPrimaryKeys());
            if (!$is_allowed) {
                throw $this->createAccessDeniedException();
            }
        } else {
            // si c'est un nouveau homework,
            // on l'initialise avec le groupe courant
            $homework = new Homework();
            if ($day) {
                $homework->setDate($day);
            } else {
                $homework->setDate(new DateTime("tomorrow"));
            }
            $homework->setRecurrenceEndDate(strtotime("next month"));
        }

		if($available_groups->count() == 1)
		{
		    $homework->setGroups($available_groups);
		}

		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);

		$subjectform = $this->createForm(new SubjectType(), new HomeworkSubject())->createView();
		$homeworkform = $this->createForm(new HomeworkType($subjects, $available_groups, $right_manager->getLocale()), $homework)->createView();

		return array(
			'subject_form' => $subjectform,
			'homework' => $homework,
			'homework_form' => $homeworkform,
			'subjects' => $subjects,
			'groups' => $available_groups,
			'preferences' => $preferences
		);
    }

    /**
     * Création d'un nouveau travail pour un/des groupes
     * @param Request $request
     * @Route("nouveau-travail", name="BNSAppHomeworkBundle_back_new_homework")
     * @Route("nouveau-travail/{day}", name="BNSAppHomeworkBundle_back_new_homework_date")
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function newHomeworkAction($day = null)
    {
		$response = $this->forward('BNSAppHomeworkBundle:Back:editHomework', array(
			'slug' => null,
                        'day' => $day,
		));

		return $response;
    }

    /**
     * Gestion des préférences du cahier de textes
     * @Route("/preferences", name="BNSAppHomeworkBundle_back_preferences")
     * @Template()
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function preferencesAction(Request $request)
    {
		$right_manager = $this->get('bns.right_manager');
		$current_group_id = $right_manager->getCurrentGroupId();

		// récupération des données travaux, sujets, matières
		$groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);
		$preferences = HomeworkPreferencesQuery::create()->findOrInit($current_group_id);
		$form = $this->createForm(new HomeworkPreferencesType($this->get('translator')), $preferences);

		if ($request->getMethod() == 'POST') {

			$form->bind($request);

			if ($form->isValid()) {
				if(count($preferences->getDays()) == 0) {
					$this->get('session')->getFlashBag()->add('notice_error_msg_only', $this->get('translator')->trans('FLASH_SELECT_MIN_ONE_DAY', array(), 'HOMEWORK'));
				}
				else {
					$this->get('session')->getFlashBag()->add('notice_success_msg_only', $this->get('translator')->trans('FLASH_PREFERENCE_SAVE_SUCCESS', array(), 'HOMEWORK'));
					$preferences->save();
				}

				$this->getRequest()->getSession()->remove("weekdays-session");
			}
		}

		return array(
			'preferences_form' => $form->createView(),
			'subjects' => $subjects,
			'groups' => $groups,
			'preferences' => $preferences
		);
    }

    /**
     * @Route("/categories", name="homework_manager_categories")
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function categoriesAction()
    {
		$context = $this->get('bns.right_manager')->getContext();
		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($context['id']);

		return $this->render('BNSAppHomeworkBundle:Back:subjects.html.twig', array(
		    'subjects' => $subjects
		));
    }

    /**
     * Visualisation d'une occurence de devoir
     * @Route("/devoirs/occurences/{dueId}/detail", name="BNSAppHomeworkBundle_backajax_homeworkdue_detail", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function detailHomeworkDueAction($dueId)
    {
		$right_manager = $this->get('bns.right_manager');
		$hd = HomeworkDueQuery::create()
			->findPk($dueId);

		$current_group_id = $right_manager->getCurrentGroupId();
		$preferences = HomeworkPreferencesQuery::create()->findOrInit($current_group_id);

		if (null == $hd) {
			throw new NotFoundHttpException('The homework due with id : ' . $dueId . ' is not found !');
		}

		$right_manager->forbidIf(
			!$right_manager->hasRightInSomeGroups('HOMEWORK_ACCESS_BACK',$hd->getHomework()->getGroupsIds())
		);

		return $this->render('BNSAppHomeworkBundle:Back:detail_homeworkdue.html.twig', array(
		    'homeworkdue' => $hd,
		    'preferences' => $preferences
		));
    }

    /**
     * Suppresssion d'une occurence de devoir
     * @Route("/devoirs/occurences/{dueId}/supprimer", name="BNSAppHomeworkBundle_back_homeworkdue_delete", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function deleteHomeworkDueAction($dueId)
    {
		$right_manager = $this->get('bns.right_manager');
		$hd = HomeworkDueQuery::create()
			->findPk($dueId);

		if (null == $hd) {
			throw new NotFoundHttpException('The homework due with id : ' . $dueId . ' is not found !');
		}

		// si l'utilisateur n'est pas membre d'un groupe concerne par le devoir
		// on renvoie une erreur forbidden
		$is_allowed = $right_manager->hasRightInSomeGroups('HOMEWORK_ACCESS_BACK', $hd->getHomework()->getGroupsIds());
		$right_manager->forbidIf(!$is_allowed);

		// suppression d'une occurence de devoir
		$hd->delete();

		$this->get('session')->getFlashBag()->add('notice_success_msg_only', $this->get('translator')->trans('FLASH_OCCURENCE_DELETE_SUCCESS', array(), 'HOMEWORK'));

		if ($this->getRequest()->isXmlHttpRequest()) {
			return new Response();
		} else {
			return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
		}
    }

    /**
     * Suppresssion d'un devoir (toutes les occurences)
     * @Route("/devoirs/{slug}/supprimer", name="BNSAppHomeworkBundle_back_homework_delete", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     */
    public function deleteHomeworkAction($slug)
    {
		$right_manager = $this->get('bns.right_manager');
		$homework = HomeworkQuery::create()->filterBySlug($slug)->findOne();

		if (null == $homework) {
			throw new NotFoundHttpException('The homework with slug : ' . $slug . ' is not found !');
		}

		// si l'utilisateur n'est pas membre d'un groupe concerne par le devoir
		// on renvoie une erreur forbidden
		$is_allowed = $right_manager->hasRightInSomeGroups('HOMEWORK_ACCESS_BACK', $homework->getGroups()->getPrimaryKeys());
		$right_manager->forbidIf(!$is_allowed);

		// suppression d'une occurence de devoir
		$homework->delete();

		$this->get('session')->getFlashBag()->add('notice_success_msg_only', $this->get('translator')->trans('FLASH_WORK_DELETE_SUCCESS', array(), 'HOMEWORK'));

		if ($this->getRequest()->isXmlHttpRequest()) {
			return new Response();
		} else {
			return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back'));
		}
    }

}

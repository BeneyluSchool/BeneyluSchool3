<?php

namespace BNS\App\HomeworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \DateTime;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;

class BackController extends Controller
{

    /**
     * Accueil de l'admin homework
     * @Route("/", name="BNSAppHomeworkBundle_back")
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function manageAction()
    {
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
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function manageForWeekAction($day)
    {
		$this->getRequest()->getSession()->remove("subjects-session");
        $this->getRequest()->getSession()->remove("groups-session");
        $this->getRequest()->getSession()->remove("weekdays-session");
		
        // vérification des droits d'accès
        $right_manager = $this->get('bns.right_manager');
        $current_group_id = $right_manager->getCurrentGroupId();

        // récupération des données travaux, sujets, matières 
        $groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

        $subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($current_group_id);
        
        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());
        
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
     * @Rights("HOMEWORK_ACCESS_BACK")
     */
    public function addHomeworkAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $right_manager = $this->get('bns.right_manager');
            $current_group_id = $right_manager->getCurrentGroupId();
            $groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

            $subjects = HomeworkSubjectQuery::create()
                ->orderByTreeLeft()
                ->findByGroupId($current_group_id);

            //Récupérer les préférences pour afficher les jours et les travaux passés
            $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());
            
            $homework = new Homework();
            $form = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework);
            $form->bindRequest($request);

            if ($form->isValid() && $homework->getGroups()->count() > 0) {

                $attachmentIds = $this->get('bns.resource_manager')->getAttachmentsId($request);
                if ($attachmentIds != null) {
                    foreach ($attachmentIds as $idAtt) {
                        $homework->addResourceAttachment($idAtt);
                    }
                }
                $homework->save();

                // process this homework to create related dues and tasks
                $this->get('bns.homework_manager')->processHomework($homework);

                if ($form->get('createAnother')->getData() == 'false') {
                    // renvoyer l'utilisateur vers la page contenant le premier due
                    // pour ce devoir

                    $next_homework_due = HomeworkDueQuery::create()->findNextOccuringHomeworkDue($homework->getId());
					
					if (null == $next_homework_due) {
						$redirect_to_day = new DateTime("monday this week");
						$redirect_to_day = $redirect_to_day->format("d-m-Y");
					}
					else {
						$redirect_to_day = $next_homework_due->getDueDate()->format("d-m-Y");
					}

                    return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_back_manage_for_week', array('day' => $redirect_to_day)));
                } else {
                    
                    // l'utilisateur souhaite creer plus de devoirs
                    return $this->redirect(
                                    $this->generateUrl('BNSAppHomeworkBundle_back_new_homework'));
                }
            } else {
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
     * @Rights("HOMEWORK_ACCESS_BACK")
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

        $subjects = HomeworkSubjectQuery::create()
                ->orderByTreeLeft()
                ->findByGroupId($current_group_id);
        
        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());
        $form = $this->createForm(new HomeworkType($subjects, $groups, $right_manager->getLocale()), $homework);

        if ($request->getMethod() == 'POST') {

            $form->bindRequest($request);

            if ($form->isValid() && $homework->getGroups()->count() > 0) {

                $attachmentIds = $this->get('bns.resource_manager')->getAttachmentsId($request);
                if ($attachmentIds != null) {
                    foreach ($attachmentIds as $idAtt) {
                        $homework->addResourceAttachment($idAtt);
                    }
                }    
                
                if($homework->isColumnModified(HomeworkPeer::RECURRENCE_TYPE) || $homework->isColumnModified(HomeworkPeer::DATE) || $homework->isColumnModified(HomeworkPeer::RECURRENCE_END_DATE))
                {
                    $homework->getHomeworkDues()->delete();
                }
                
                
                $homework->save();
                
                $this->get('bns.homework_manager')->processHomework($homework);
            }
            else
            {
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
     * Edition d'un travail pour un/des groupes
     * @param Request $request
     * @Route("/travail/{slug}", name="BNSAppHomeworkBundle_back_edit_homework")
     * @Template()
     */
    public function editHomeworkAction($slug)
    {
        $right_manager = $this->get('bns.right_manager');
        $available_groups = $right_manager->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
        $current_group_id = $right_manager->getCurrentGroupId();
        
        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());
        
        $is_allowed = false;

        if ($slug) {
            // si le homework existe
            // on verifie si l'utilisateur a les droits sur un des groupes concernes
            $homework = HomeworkQuery::create()->findOneBySlug($slug);
            $is_allowed = $right_manager->hasRightInSomeGroups('HOMEWORK_ACCESS_BACK', $homework->getGroups()->getPrimaryKeys());
        } else {
            // si c'est un nouveau homework,
            // on l'initialise avec le groupe courant
            $homework = new Homework();
            $homework->setDate(new DateTime("tomorrow"));
            $homework->setRecurrenceEndDate(strtotime("next month"));
            $is_allowed = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $current_group_id);
        }

        // vérification des droits d'accès
        $right_manager->forbidIf(!$is_allowed);

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
     */
    public function newHomeworkAction()
    {

        $response = $this->forward('BNSAppHomeworkBundle:Back:editHomework', array(
            'slug' => null,
                ));

        return $response;
    }

    /**
     * Gestion des préférences du cahier de texte
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

        $form = $this->createForm(new HomeworkPreferencesType(), $preferences);

        if ($request->getMethod() == 'POST') {

            $form->bindRequest($request);

            if ($form->isValid()) {
                $preferences->save();
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
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function categoriesAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($context['id']);
		
		return $this->render('BNSAppHomeworkBundle:Back:subjects.html.twig', array(
			'subjects' => $subjects
		));
	}
}

<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\ClassroomBundle\Form\Model\ProfileFormModel;
use BNS\App\ClassroomBundle\Form\Type\ProfileType;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use BNS\App\ClassroomBundle\Form\Model\NewUserInClassroomFormModel;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\SchoolBundle\Form\Type\EditSchoolType;
use BNS\App\SchoolBundle\Form\Model\EditSchoolFormModel;
use BNS\App\SchoolBundle\Model\ClassroomSignal;
use BNS\App\ClassroomBundle\Form\Type\ImportPupilFromCSVType;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/gestion")
 */

class BackController extends CommonController
{
	/**
     * Page d'accueil du module école en gestioon : tableau de bord + menu
     * @Route("/", name="BNSAppSchoolBundle_back")
	 * @Template()
	 * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function indexAction()
    {
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        if ($this->get('bns_core.application_manager')->isEnabled()) {
            $activationRoles = null;
            $lastUserConnected = $this->get('bns.group_manager')->setGroup($currentGroup)->getLastUsersConnected(20);
        } else {
            $activationRoles = GroupTypeQuery::create()->filterBySimulateRole(true)->orderByType(\Criteria::DESC)->findByType(array('TEACHER','PUPIL','PARENT'));
            $lastUserConnected = array();
        }

        return $this->render('BNSAppSchoolBundle:Back:index.html.twig', array(
            'activationRoles'      => $activationRoles,
            'last_users_connected' => $lastUserConnected,
            'uid'                  => $currentGroup->hasAttribute('UAI') ? $currentGroup->getAttribute('UAI') : '-',
            'hasGroupBoard'        => $hasGroupBoard
        ));
    }

    /**
     * Liste des classes de l'école
     * @Route("/classes", name="BNSAppSchoolBundle_back_classrooms")
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function classroomsAction()
    {
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');
        return array(
            'classrooms' => $this->get('bns.right_manager')->getCurrentGroupManager()->getSubgroupsByGroupType('CLASSROOM',true),
            'hasGroupBoard'        => $hasGroupBoard
        );
    }

    /**
     * Fiche d'une classe
     * @Route("/classe/{groupSlug}", name="BNSAppSchoolBundle_back_classroom")
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function classroomAction($groupSlug)
    {
        $classroom = $this->get('bns.group_manager')->findGroupBySlug($groupSlug);
        $this->get('bns.group_manager')->setGroup($classroom);
        $this->getRequest()->getSession()->set('school_last_visited_listing','classroom_' . $groupSlug);

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $groupManager = $this->get('bns.group_manager')->setGroup($classroom);

        return array(
            'classroom' => $classroom,
            'teachers'   => $groupManager->getUsersByRoleUniqueName('TEACHER', true),
            'pupils'     => $groupManager->getUsersByRoleUniqueName('PUPIL', true),
            'assistants' => $groupManager->getUsersByRoleUniqueName('ASSISTANT', true),
            'hasGroupBoard' => $hasGroupBoard
        );
    }

    /**
     * Ajout d'une classe
     * @Route("/classe-ajouter", name="BNSAppSchoolBundle_back_add_classroom", options={"expose"=true})
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function addClassroomAction()
    {
        $this->checkAjax();
        //récupération
        $label = $this->getRequest()->get('label');
        if(trim($label) == '')
        {
            return new Response('erreur','500');
        }else{
            /* @var $gm \BNS\App\CoreBundle\Group\BNSGroupManager */
            $gm = $this->get('bns.right_manager')->getCurrentGroupManager();
            $schoolId = $gm->getGroup()->getId();
            $classroom = $gm->createGroup(array(
                'type' => 'CLASSROOM',
                'country' => $gm->getGroup()->getCountry(),
                'label' => $label
            ));
            $gm->addParent($gm->getGroup()->getId(),$schoolId);
            return array(
                'continue' => $this->getRequest()->get('continue'),
                'classroom' => $classroom
            );
        }
    }

    /**
     * Signalement d'une classe
     * @Route("/classe-signaler", name="BNSAppSchoolBundle_back_signal_classroom", options={"expose"=true})
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function signalClassroomAction()
    {
        $classroomId = $this->getRequest()->get('classroomId');
        $this->checkClassroom($classroomId);
        //récupération
        $reasonLabel = $this->getRequest()->get('reason');
        $signal = new ClassroomSignal();
        $signal->init($reasonLabel, $classroomId, $this->get('bns.right_manager'), $this->get('bns.mailer'));

        $classroom = GroupQuery::create()->findOneById($classroomId);
        $classroom->refuse();
        $this->get('bns.group_manager')->setGroupById($classroomId)->clearGroupCache();

        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_REPORTING_SUCCESS', array(), "SCHOOL"));

        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_classrooms'));
    }

    /**
     * Signalement d'une classe
     * @Route("/classe-supprimer/{classroomId}", name="BNSAppSchoolBundle_back_delete_classroom")
     * @Template()
     * @RightsSomeWhere("SCHOOL_CREATE_CLASSROOM")
     */
    public function deleteClassroomAction()
    {
        $classroomId = $this->getRequest()->get('classroomId');
        $this->checkClassroom($classroomId);
        //récupération
        $classroom = GroupQuery::create()->findOneById($classroomId);
        $classroom->archive();
        $this->get('bns.group_manager')->setGroupById($classroomId)->clearGroupCache();

        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_DELETING_SUCCESS', array(), "SCHOOL"));

        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_classrooms'));
    }

    /**
     * Validation d'une classe
     * @Route("/classe-valider/{classroomId}", name="BNSAppSchoolBundle_back_validate_classroom")
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function validateClassroomAction($classroomId)
    {
        $this->checkClassroom($classroomId);
        $classroom = GroupQuery::create()->findOneById($classroomId);
        $cm = $this->get('bns.classroom_manager');
        $cm->setClassroom($classroom);
        $cm->confirmClassRoom();
        $this->get('bns.group_manager')->setGroupById($classroomId)->clearGroupCache();
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_CLASSROOM_VALIDATED', array(), "SCHOOL"));
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_classroom',array('groupSlug' => $classroom->getSlug())));
    }

    /**
     * Toggle d'activation d'une classe
     * @Route("/classe-activer/{classroomId}", name="BNSAppSchoolBundle_back_enable_toggle_classroom")
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function toggleEnableClassroomAction($classroomId)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->container->hasParameter('check_group_enabled') && $this->container->getParameter('check_group_enabled') != true);
        $this->checkClassroom($classroomId);
        $classroom = GroupQuery::create()->findOneById($classroomId);
        $classroom->toggleEnabled();
        $this->get('session')->getFlashBag()->add(
            'success',
            $classroom->isEnabled() ? $this->get('translator')->trans('FLASH_CLASSROOM_ALLOWED', array(), "SCHOOL") : $this->get('translator')->trans('FLASH_CLASSROOM_ENABLED', array(), "SCHOOL")
        );
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_classroom',array('groupSlug' => $classroom->getSlug())));
    }





    /**
     * Import CSV dans une classe
     * @Route("/classe-import/{groupSlug}", name="BNSAppSchoolBundle_back_import_classroom")
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function importClassroomAction($groupSlug)
    {
        $classroom = GroupQuery::create()->findOneBySlug($groupSlug);
        $this->checkClassroom($classroom->getId());
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');
        return $this->render('BNSAppSchoolBundle:Back:pupilsImport.html.twig', array(
            'form' => $this->createForm(new ImportPupilFromCSVType())->createView(),
            'classroom_id' => $classroom->getId(),
            'group' => $classroom,
            'hasGroupBoard' => $hasGroupBoard
        ));
    }

    /**
     * Action appelé lorsque l'utilisateur clique sur le bouton "J'ai terminé" de la page d'importation d'élèves grâce à un fichier CSV
     *
     * @Route("/classe-lancer-import/{groupSlug}", name="BNSAppSchoolBundle_back_do_import_classroom")
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     */
    public function doImportAction()
    {
        $request = $this->getRequest();
        if (!$request->isMethod('POST')) {
            throw new HttpException(500, 'Request must be `POST`\'s method!');
        }

        $form = $this->createForm(new ImportPupilFromCSVType());
        $form->bind($request);
        if (null !== $form['file']->getData() && null !== $form['format']->getData() && $form->isValid() && $request->get('classroom_id') != null) {
            $classroom = GroupQuery::create()->findOneById(intval($request->get('classroom_id')));
            $this->checkClassroom($classroom->getId());
            try {
                $result = $this->get('bns.classroom_manager')
                    ->setClassroom($classroom)
                    ->importPupilFromCSVFile($form['file']->getData(), $form['format']->getData());

                if ($result['success_insertion_count'] == $result['user_count']) {
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        $this->get('translator')->trans('FLASH_PROCESS_IMPORT_SUCCESS', array('%userCount%' => $result['user_count']), "SCHOOL")
                    );
                }else{
                    $this->get('session')->getFlashBag()->add(
                        'error',
                        $this->get('translator')->trans('FLASH_PROCESS_IMPORT_ERROR', array(
                            '%resultSuccess%' => $result['success_insertion_count'],
                            '%skiped%' => $result['skiped_count'],
                            '%failed%' => ($result['user_count'] - $result['success_insertion_count'] - $result['skiped_count']),
                            ), "SCHOOL"));

                }
            }catch(UploadException $e) {
                if ($e->getCode() == 1) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT', array(), "SCHOOL");
                }
                elseif ($e->getCode() == 2) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_PUPIL_FORMAT', array(), "SCHOOL");
                }
                elseif ($e->getCode() == 3) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_BENEYLU_FORMAT', array(), "SCHOOL");
                }
                else {
                    $msg = $this->get('translator')->trans('FLASH_ERROR_CONTACT_BENEYLU', array('%beneylu_brand_name%' => $this->container->getParameter('beneylu_brand_name')), "SCHOOL");
                }
                $this->get('session')->getFlashBag()->add('error', $msg);
                return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_import_classroom', array('groupSlug' => $classroom->getSlug())));
            }
            return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_import_classroom', array('groupSlug' => $classroom->getSlug())));
        }
        $this->get('session')->getFlashBag()->add('submit_import_form_error', '');
        return $this->render('BNSAppSchoolBundle:Back:pupilsImport.html.twig', array('form' => $form->createView()));
    }


    /**
     * @Route("/classe-ajouter-un-enseignant/{classroomId}", name="BNSAppSchoolBundle_back_add_teacher", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function addTeacherAction($classroomId)
    {
        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $form = $this->createForm(new NewUserInClassroomType(true,false), new NewUserInClassroomFormModel(true,false));
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid() && $this->get('bns.right_manager')->hasRight('SCHOOL_CREATE_TEACHER')) {
                $userProxy = $form->getData();
                $userProxy->save();
                $this->get('bns.classroom_manager')->setClassroom(GroupQuery::create()->findOneById($classroomId));
                $this->get('bns.classroom_manager')->assignTeacher($userProxy->getObjectUser());
                $user = UserQuery::create()->findOneBySlug($userProxy->getObjectUser()->getSlug());
                $user->setIsEnabled(true);
                $user->save();
                //Décache de l'école
                $this->get('bns.right_manager')->getCurrentGroupManager()->clearGroupCache();
                return $this->render('BNSAppSchoolBundle:Block:teacherFormRowSuccess.html.twig', array('teacher' => $user));
            }else{
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('ERROR_EMAIL_ALREADY_USED', array(), 'SCHOOL'));
                return $this->render('BNSAppSchoolBundle:Block:teacherFormRow.html.twig', array(
                    'form'				=> $form->createView(),
                    'classroomId'       => $classroomId
                    )
                );
            }
        }

        //Les directeurs peuvent également être affectés en tant qu'enseignant
        $teachers = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('TEACHER', true);
        $directors = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('DIRECTOR', true);

        $users = array();
        foreach($teachers as $user)
        {
            $users[$user->getId()] = $user;
        }
        foreach($directors as $user)
        {
            $users[$user->getId()] = $user;
        }
        return $this->render('BNSAppSchoolBundle:Block:teacherForm.html.twig', array(
            'form' => $form->createView(),
            'classroomId' => $classroomId,
            'teachers' => $users
        ));
    }

    /**
     * @Route("/classe-affecter-un-enseignant/{classroomId}", name="BNSAppSchoolBundle_back_affect_teacher", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function affectTeacherAction($classroomId)
    {
        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $form = $this->createForm(new NewUserInClassroomType(true,false), new NewUserInClassroomFormModel(true,false));
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid() && $this->get('bns.right_manager')->hasRight('SCHOOL_CREATE_TEACHER')) {
                $userProxy = $form->getData();
                $userProxy->save();
                $this->get('bns.classroom_manager')->setClassroom(GroupQuery::create()->findOneById($classroomId));
                $this->get('bns.classroom_manager')->assignTeacher($userProxy->getObjectUser());
                $user = UserQuery::create()->findOneBySlug($userProxy->getObjectUser()->getSlug());
                $user->setIsEnabled(true);
                $user->save();
                //Décache de l'école
                $this->get('bns.right_manager')->getCurrentGroupManager()->clearGroupCache();
                return $this->render('BNSAppSchoolBundle:Block:teacherFormRowSuccess.html.twig', array('teacher' => $user));
            }else{
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('ERROR_FORM_TRY_AGAIN', array(), 'SCHOOL'));
                return $this->render('BNSAppSchoolBundle:Block:teacherFormRow.html.twig', array(
                        'form'				=> $form->createView(),
                        'classroomId'       => $classroomId
                    )
                );
            }
        }

        //Les directeurs peuvent également être affectés en tant qu'enseignant
        $teachers = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('TEACHER', true);
        $directors = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('DIRECTOR', true);

        $users = array();
        foreach($teachers as $user)
        {
            $users[] = $user;
        }
        foreach($directors as $user)
        {
            $users[] = $user;
        }


        return $this->render('BNSAppSchoolBundle:Block:teacherForm.html.twig', array(
            'form' => $form->createView(),
            'classroomId' => $classroomId,
            'teachers' => $users
        ));
    }

    /**
     * @Route("/classe-ajouter-un-auxiliaire/{classroomId}", name="BNSAppSchoolBundle_back_add_assistant", options={"expose"=true})
     * @Rights("SCHOOL_CREATE_ASSISTANT")
     */
    public function addAssistantAction(Request $request, $classroomId)
    {
        $this->checkAjax();
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $isSchool = false;
        if ($group->getId() === (int)$classroomId) {
            if ($group->getType() !== 'SCHOOL') {
                throw new AccessDeniedException();
            }
            $isSchool = true;
        } else {
        $this->checkClassroom($classroomId);
        }
        $form = $this->createForm(new NewUserInClassroomType(true, false), new NewUserInClassroomFormModel(true, false));
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $userProxy = $form->getData();
                $userProxy->save();
                $user = $userProxy->getObjectUser();

                $this->get('bns.role_manager')->setGroupTypeRoleFromType('ASSISTANT')->assignRole($user, $classroomId);
                //Décache de l'école
                $this->get('bns.right_manager')->getCurrentGroupManager()->clearGroupCache();

                return $this->render('BNSAppSchoolBundle:Block:assistantFormRowSuccess.html.twig', array('assistant' => $user));
            } else {
                $this->get('session')->getFlashBag()->add('error', "Il y a une erreur dans votre formulaire, veuillez réessayer. Cet email est sans doute déjà enregistré.");

                return $this->render('BNSAppSchoolBundle:Block:AssistantFormRow.html.twig', array(
                    'form'          => $form->createView(),
                    'classroomId'   => $classroomId
                ));
            }
        }

        $users = array();
        if (!$isSchool) {
        $assistants = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('ASSISTANT', true);
        foreach ($assistants as $user) {
            $users[$user->getId()] = $user;
        }
        }


        return $this->render('BNSAppSchoolBundle:Block:assistantForm.html.twig', array(
            'form' => $form->createView(),
            'classroomId' => $classroomId,
            'assistants' => $users
        ));
    }

    /**
     * @Route("/classe-ajouter-un-eleve/{classroomId}", name="BNSAppSchoolBundle_back_add_pupil", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function addPupilAction($classroomId)
    {
        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $form = $this->createForm(new NewUserInClassroomType(false,true), new NewUserInClassroomFormModel(false,true));
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid() && $this->get('bns.right_manager')->hasRight('SCHOOL_CREATE_PUPIL')) {
                $userProxy = $form->getData();
                $userProxy->save();
                $this->get('bns.classroom_manager')->setClassroom(GroupQuery::create()->findOneById($classroomId));
                $this->get('bns.classroom_manager')->assignPupil($userProxy->getObjectUser());
                $user = UserQuery::create()->findOneBySlug($userProxy->getObjectUser()->getSlug());
                $user->setIsEnabled(true);
                $user->save();
                //Décache de l'école
                $this->get('bns.right_manager')->getCurrentGroupManager()->clearGroupCache();
                return $this->render('BNSAppSchoolBundle:Block:pupilForm.html.twig', array(
                        'form'				=> $form->createView(),
                        'classroomId'       => $classroomId,
                        'success'           => true,
                        'user'              => $user
                    )
                );
            }else{
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('ERROR_FORM_TRY_AGAIN', array(), 'SCHOOL'));
                return $this->render('BNSAppSchoolBundle:Block:pupilForm.html.twig', array(
                        'form'				=> $form->createView(),
                        'classroomId'       => $classroomId
                    )
                );
            }
        }

        return $this->render('BNSAppSchoolBundle:Block:pupilForm.html.twig', array(
            'form' => $form->createView(),
            'classroomId' => $classroomId
        ));
    }




    /**
     * @Route("/classe-ajouter-un-enseignant-existant/{classroomId}", name="BNSAppSchoolBundle_back_add_existing_teacher", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function addExistingTeacherAction($classroomId)
    {
        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $userId = $this->getRequest()->get('teacherId');
        $this->checkUser($userId,array('DIRECTOR','TEACHER'));
        $user = UserQuery::create()->findOneById($userId);
        $rm = $this->get('bns.role_manager');
        $rm->setGroupTypeRoleFromType('TEACHER');
        $rm->assignRole($user, $classroomId);

        return $this->render('BNSAppSchoolBundle:Block:teacherRow.html.twig', array(
            'teacher' => $user
        ));
    }

    /**
     * @Route("/classe-ajouter-un-auxiliaire-existant/{classroomId}", name="BNSAppSchoolBundle_back_add_existing_assistant", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function addExistingAssistantAction($classroomId)
    {
        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $userId = $this->getRequest()->get('assistantId');
        $this->checkUser($userId, array('ASSISTANT'));
        $user = UserQuery::create()->findOneById($userId);
        $rm = $this->get('bns.role_manager');
        $rm->setGroupTypeRoleFromType('ASSISTANT');
        $rm->assignRole($user, $classroomId);

        return $this->render('BNSAppSchoolBundle:Block:assistantRow.html.twig', array(
            'assistant' => $user
        ));
    }

    /**
     * @Route("/verifier-nom-utilisateur/{classroomId}/{type}", name="BNSAppSchoolBundle_back_verify_username", options={"expose"=true}, requirements={"type"="TEACHER|ASSISTANT"})
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function verifyUsernameAction(Request $request, $classroomId, $type = "TEACHER")
    {
        if (!in_array($type, array('TEACHER', 'ASSISTANT'))) {
            throw $this->createNotFoundException();
        }

        $this->checkAjax();
        $this->checkClassroom($classroomId);
        $username = $request->get('username');
        if (!$username) {
        // FIX Me do not throw 500 error            return new Response($this->get('translator')->trans('ENTER_LOGIN', array(), 'SCHOOL'), 500);
        }

        $user = $this->get('bns.user_manager')->findUserByLogin($username, true);
        if (!$user){
        // FIX Me do not throw 500 error
            return new Response($this->get('translator')->trans('USER_WITH_LOGIN_NOT_FOUND', array('%username%' => $username), 'SCHOOL'), 500);
        }
        $rm = $this->get('bns.role_manager');

        $rm->setGroupTypeRoleFromType($type);
        $rm->assignRole($user, $classroomId);

        return $this->render('BNSAppSchoolBundle:Block:' . strtolower($type) . 'Row.html.twig', array(
            strtolower($type) => $user
        ));
    }

    /**
     * @Route("/utilisateur/{userSlug}", name="BNSAppSchoolBundle_back_user_sheet", options={"expose"=true})
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function userSheetAction($userSlug)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $user = $um->findUserBySlug($userSlug);
        $this->checkUser($user->getId(),array('TEACHER', 'PUPIL', 'ASSISTANT'));
        //""Calcul" du lien de retour depuis attribut en session : soit la liste des enseignants, soit la fiche de la classe
        $isTeacher  = $um->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(), 'TEACHER');
        $isReferent = $um->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(), 'ENT_REFERENT');
        $isDirector = $um->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(), 'DIRECTOR');
        if ($isTeacher) {
            $returnLink =  $this->generateUrl('BNSAppSchoolBundle_back_teachers');
            if ($this->get('session')->get('school_last_visited_listing') != 'teachers') {
                if (false !== ($group = $this->getClassroomForUser($user->getId()))) {
                    $returnLink = $this->generateUrl('BNSAppSchoolBundle_back_classroom', array('groupSlug' => $group->getSlug()));
        }
            }
        } else {
            if (false !== ($group = $this->getClassroomForUser($user->getId()))) {
                $returnLink = $this->generateUrl('BNSAppSchoolBundle_back_classroom', array('groupSlug' => $group->getSlug()));
            } else {
                $returnLink = $this->generateUrl('BNSAppSchoolBundle_back');
            }
        }


        return array(
            'user'      => $user,
            'classroom' => $this->getClassroomForUser($user->getId()),
            'isValidated' => $this->isUserValidated($user->getId()),
            'isTeacher'  => $isTeacher,
            'isReferent' => $isReferent,
            'isDirector' => $isDirector,
            'returnLink' => $returnLink
        );
    }

    /**
     * @Route("/utilisateur-edition/{userSlug}", name="BNSAppSchoolBundle_back_user_sheet_edit", options={"expose"=true})
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function userSheetEditAction($userSlug)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $user = $um->findUserBySlug($userSlug);
        $this->checkUser($user->getId(),array('TEACHER','PUPIL', 'ASSISTANT'));
        $isTeacher = $um->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(),'TEACHER');

        $form = $this->createForm(new ProfileType($user, $this->container->getParameter('available_languages')), new ProfileFormModel($user));
        if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());
            if ($form->isValid()) {
                $data = $form->getData();
                if ($data->lang != null) {
                    $this->get('bns.right_manager')->setLocale($form->getData()->lang);
                }
                $form->getData()->save();
                return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_user_sheet',array('userSlug' => $user->getSlug())));
            }
        }

        return array(
            'user'      => $user,
            'form'      => $form->createView(),
            'isTeacher' => $isTeacher
        );
    }

    /**
     * @Route("/utilisateur-supprimer/{userSlug}", name="BNSAppSchoolBundle_back_user_delete", options={"expose"=true})
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function userDeleteAction($userSlug)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $groupId = $this->get('bns.right_manager')->getCurrentGroupId();

        $user = $um->findUserBySlug($userSlug);
        $classroom = $this->getClassroomForUser($user->getId());
        if($classroom)
        {
            $classroomSlug = $classroom->getSlug();
        }
        $this->checkUser($user->getId(),array('TEACHER','PUPIL', 'ASSISTANT'));
        $this->get('bns.right_manager')->forbidIf($um->hasRoleInGroup($groupId,'DIRECTOR'));
        if($um->hasRoleInGroup($groupId,'TEACHER'))
        {
            $this->get('bns.role_manager')->unassignRole($user->getId(), $groupId, 'TEACHER');
        }else{
            $this->get('bns.role_manager')->unassignRole($user->getId(), $groupId, 'PUPIL');
            //On désactive les élèves
            $um->changeStatus($user,false,false);
            foreach($um->getUserParent($user) as $parent)
            {
                $this->get('bns.role_manager')->unassignRole($parent->getId(), $groupId, 'PARENT');
            }
        }
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_USER_REMOVED', array(), 'SCHOOL'));
        if(isset($classroomSlug))
        {
            return $this->redirect(
                $this->generateUrl('BNSAppSchoolBundle_back_classroom',array('groupSlug' => $classroomSlug))
            );
        }else{
            return $this->redirect(
                $this->generateUrl('BNSAppSchoolBundle_back_teachers')
            );
        }

    }

    /**
     * @Route("/utilisateur-promouvoir/{userSlug}", name="BNSAppSchoolBundle_back_user_promote")
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function userPromoteAction($userSlug)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $groupId = $this->get('bns.right_manager')->getCurrentGroupId();
        $user = $um->findUserBySlug($userSlug);
        $this->checkUser($user->getId(),'TEACHER');
        $roleManager = $this->get('bns.role_manager');
        $roleManager->setGroupTypeRoleFromType('ENT_REFERENT');
        $roleManager->assignRole($user, $groupId);
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_USER_PROMOTED', array(), 'SCHOOL'));
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_user_sheet',array('userSlug' => $user->getSlug())));
    }

    /**
     * @Route("/utilisateur-revoquer/{userSlug}", name="BNSAppSchoolBundle_back_user_revoke")
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function userRevokeAction($userSlug)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $groupId = $this->get('bns.right_manager')->getCurrentGroupId();
        $user = $um->findUserBySlug($userSlug);
        $this->checkUser($user->getId(),'TEACHER', 'ASSISTANT');
        $this->get('bns.role_manager')->unassignRole($user->getId(), $groupId, 'ENT_REFERENT');
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_USER_UNPROMOTED', array(), 'SCHOOL'));
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_user_sheet',array('userSlug' => $user->getSlug())));
    }

    /**
     * @Route("/enseignants", name="BNSAppSchoolBundle_back_teachers")
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function teachersAction()
    {
        $school = $this->get('bns.right_manager')->getCurrentGroup();
        $invitedTeachers = $this->get('bns.school_manager')->setGroup($school)->getInvitedTeachers();
        $this->getRequest()->getSession()->set('school_last_visited_listing','teachers');

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        return array(
            'teachers' => $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName('TEACHER',true),
            'invited_teachers' => $invitedTeachers,
            'hasGroupBoard' => $hasGroupBoard
        );
    }

    /**
     * Invite a teacher in the current school
     *
     * @Route("/enseignant-inviter", name="BNSAppSchoolBundle_back_invite_teacher", options={"expose"=true})
     * @Template()
     * @RightsSomeWhere("SCHOOL_ACCESS_BACK")
     *
     * @param Request $request
     * @return Response
     */
    public function inviteTeacherAction(Request $request)
    {
        $this->checkAjax();
        $translator = $this->get('translator');

        $username = $request->get('username');
        if (!$username) {
            return new Response($translator->trans('ENTER_LOGIN', array(), 'SCHOOL'), 400);
        }

        $userManager = $this->get('bns.user_manager');
        $user = $userManager->findUserByLogin($username, true);
        if (!$user) {
            return new Response($translator->trans('USER_WITH_LOGIN_NOT_FOUND', array('%username%' => $username), 'SCHOOL'), 404);
        }

        $school = $this->get('bns.right_manager')->getCurrentGroup();
        $userManager->setUser($user);
        if ($userManager->hasRoleInGroup($school->getId(), 'TEACHER')) {
            return new Response($translator->trans('USER_ALREADY_TEACHER_IN_SCHOOL', array(), 'SCHOOL'), 400);
        }

        $schoolManager = $this->get('bns.school_manager')->setGroup($school);
        if ($schoolManager->isInvitedInSchool($user)) {
            return new Response($translator->trans('USER_ALREADY_INVITED_IN_SCHOOL', array(), 'SCHOOL'), 400);
        }

        // user is valid, send invitation
        $schoolManager->inviteTeacherInSchool($user, $this->getUser());

        return $this->render('BNSAppSchoolBundle:Block:teacherRow.html.twig', array(
            'teacher' => $user,
            'invited' => true,
        ));
    }

    /**
     * @Route("/assistants", name="BNSAppSchoolBundle_back_assistants")
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function assistantsAction()
    {
        $groupManager = $this->get('bns.right_manager')->getCurrentGroupManager();
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        return array(
            'assistants'    =>  $groupManager->getUsersByRoleUniqueName('ASSISTANT', true),
            'school'        =>  $groupManager->getGroup(),
            'hasGroupBoard' => $hasGroupBoard
        );
    }

    /**
     * @Route("/personnalisation", name="BNSAppSchoolBundle_back_custom")
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function customAction(Request $request)
    {
        $school = $this->get('bns.right_manager')->getCurrentGroup();
        $form = $this->createForm(new EditSchoolType(), new EditSchoolFormModel($school, $this->get('bns.group_manager')));

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $form->getData()->save();
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_INFORMATIONS_SAVED', array(), 'SCHOOL'));

                return $this->redirect($this->generateUrl('BNSAppSchoolBundle_back_custom'));
            }
        }

        return array(
            'school'  => $school,
            'form'    => $form->createView(),
            'hasGroupBoard' => $hasGroupBoard
        );
    }


    /**
     * Action affichant la page d'assignation des utilisateurs
     *
     * @Route("/affectation", name="BNSAppSchoolBundle_back_assignment")
     * @Rights("SCHOOL_ACCESS_BACK")
     * @Template()
     */
    public function assignmentAction()
    {
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasRight('SCHOOL_CREATE_CLASSROOM'));
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');
        $gm = $this->get('bns.group_manager');
        $message = null;
        $uaiTarget = null;
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $gm->setGroup($group);
        $schoolTarget = $group;

        $formAssignment = $this->createFormBuilder()
            ->getForm();

        if($this->getRequest()->isMethod('POST'))
        {
            //Est on en affectation ?
            $teachers = $this->getRequest()->get('teachers');
            $pupils = $this->getRequest()->get('pupils');
            if(count($teachers) > 0 || count($pupils) > 0)
            {
                $envId = $this->get('bns.right_manager')->getCurrentEnvironment()->getId();
                $rom = $this->get('bns.role_manager');

                if($this->getRequest()->get('assignmentType') == 'delete')
                {
                    if(count($teachers) > 0)
                    {
                        $rom->setGroupTypeRoleFromType('TEACHER');
                        foreach($teachers as $teacherId)
                        {
                            $rom->unassignRole($teacherId, $schoolTarget->getId(),'TEACHER');
                        }
                    }
                    if(count($pupils) > 0)
                    {
                        foreach($pupils as $pupilId)
                        {
                            $rom->unassignRole($pupilId, $schoolTarget->getId(),'PUPIL');
                            $pupil = UserQuery::create()->findOneById($pupilId);
                            foreach($pupil->getParents() as $parent)
                            {
                                $rom->unassignRole($parent->getId(), $schoolTarget->getId(),'PARENT');
                            }
                        }
                    }

                    $message = $this->get('translator')->trans('DELETING_OF_TEACHER_PUPIL_DONE', array(
                        '%teacherCount%' => count($teachers),
                        '%pupilCount%' => count($pupils)
                         ),
                        'SCHOOL');

                }else{
                    if($this->getRequest()->get('assignmentType') == 'newClassroom')
                    {
                        if($this->getRequest()->get('newClassroomId') != null && $this->getRequest()->get('newClassroomId') != "false")
                        {
                            $target = GroupQuery::create()->findOneById($this->getRequest()->get('newClassroomId'));
                            //TODO : check sécu
                        }else{

                            if($this->getRequest()->get('newClassroomLabel') != null && $this->getRequest()->get('newClassroomLabel') != "")
                            {
                                $classroomLabel = $this->getRequest()->get('newClassroomLabel');
                            }else{
                                $classroomLabel = $this->get('translator')->trans('LABEL_NEW_CLASS', array(), 'SCHOOL');
                            }
                            $target = $gm->createSubgroupForGroup(array('type' => "CLASSROOM",'label' => $classroomLabel), $schoolTarget->getId());
                            $target->validateStatus();
                            $target->save();
                        }
                    }elseif($this->getRequest()->get('assignmentType') == 'link'){
                        $target = $schoolTarget;
                    }

                    //le GM est setté sur la classe
                    if(count($teachers) > 0)
                    {
                        $rom->setGroupTypeRoleFromType('TEACHER');
                        //On les déssassigne de l'ancienne classe
                        foreach($teachers as $teacherId)
                        {
                            $rom->unassignRole($teacherId, $envId,'TEACHER');
                        }
                        $rom->setGroupTypeRoleFromType('TEACHER');
                        $rom->assignRoleForUsers(UserQuery::create()->findById($teachers), $target->getId());
                    }
                    if(count($pupils) > 0)
                    {
                        $rom->setGroupTypeRoleFromType('PUPIL');
                        foreach($pupils as $pupilId)
                        {
                            $rom->unassignRole($pupilId, $envId,'PUPIL');
                        }
                        $rom->setGroupTypeRoleFromType('PUPIL');
                        $rom->assignRoleForUsers(UserQuery::create()->findById($pupils), $target->getId());

                        //On fait pour leurs parents

                        $parents = UserQuery::create()
                            ->parentsFilter($pupils)
                            ->find();
                        $parentsIds = array();
                        foreach($parents as $parent)
                        {
                            $parentsIds[] = $parent->getId();
                        }

                        foreach($parentsIds as $parentId)
                        {
                            $rom->setGroupTypeRoleFromType('PARENT');
                            $rom->unassignRole($parentId, $envId,'PARENT');
                        }

                        $rom->setGroupTypeRoleFromType('PARENT');
                        $rom->assignRoleForUsers(UserQuery::create()->findById($parentsIds), $target->getId());

                    }

                    $gm->setGroup($group);
                    $gm->clearGroupCache();

                    if($group->getId() != $schoolTarget->getId())
                    {
                        $gm->setGroup($schoolTarget);
                        $gm->clearGroupCache();
                    }

                    if($this->getRequest()->get('assignmentType') == 'newClassroom')
                    {
                        $message = $this->get('translator')->trans('NOW_HAS_TEACHERS_AND_PUPIL', array(
                                '%teacherCount%' => count($teachers),
                                '%pupilCount%' => count($pupils),
                                '%label%' => $target->getLabel(),
                            ),
                                'SCHOOL');

                    }elseif($this->getRequest()->get('assignmentType') == 'link'){
                        $message = $this->get('translator')->trans('SCHOOL_ASSIGNMENT_TEACHER_PUPIL', array(
                            '%teacherCount%' => count($teachers),
                            '%pupilCount%' => count($pupils),
                            '%label%' => $target->getLabel(),
                        ),
                            'SCHOOL');
                    }
                }
            }
        }

        $gm->setGroup($schoolTarget);
        $classroomTargets = $gm->getSubgroupsByGroupType('CLASSROOM',true);
        $classrooms = $gm->getSubgroupsByGroupType('CLASSROOM',true);
        $teachers = $gm->getUsersByRoleUniqueName('TEACHER',true);

        //Calcul des élèves dans classe
        foreach($classrooms as $classroom)
        {
            $gm->setGroup($classroom);
            foreach($gm->getUsersByRoleUniqueNameIds('PUPIL') as $pupilId)
            {
                $pupilWithClassroomId[] = $pupilId;
            }
        }
        $gm->setGroup($group);
        $pupilWithClassroom = array();
        foreach($gm->getUsersByRoleUniqueName('PUPIL',true) as $pupil)
        {
            if(!in_array($pupil->getId(),$pupilWithClassroomId))
            {
                $pupilWithClassroom[] = $pupil;
            }
        }

        return array(
            'school' => $group,
            'schoolTarget' => $schoolTarget,
            'classrooms' => $classrooms,
            'classroomTargets' => $classroomTargets,
            'teachers' => $teachers,
            'gm' => $gm,
            'message' => $message,
            'pupilWithClassroom' => $pupilWithClassroom,
            'formAssignment' => $formAssignment->createView(),
            'hasGroupBoard' => $hasGroupBoard
        );

    }

    /**
     * @Route("/generer-nouvelle-fiche-enseignant/{userSlug}", name="back_classrooms_users_generate_teacher_pwd", options={"expose"=true})
     * @Rights("SCHOOL_ACCESS_BACK")
     *
     * @param type $userSlug
     */
    public function generateTeacherPasswordAction($userSlug)
    {
        $userManager = $this->get('bns.user_manager');
        $teacher = $userManager->findUserBySlug($userSlug);

        $this->canViewProfile($teacher);

        $teacher = $userManager->resetUserPassword($teacher);


        return $this->renderTeacherCard($teacher, true);
    }

    /**
     * @Route("/generer-fiche-ecole", name="classroom_manager_generate_school_card")
     *
     * @Rights("SCHOOL_ACCESS_BACK")
     * @param array $liste
     */
    public function generateSchoolCardAction($liste = null)
    {
        //S'il n'y a qu'un utilisateur, on appelle la méthode de génération de fiche unique
        if (count($liste) == 1) {
            $user=$liste[0];
            return $this->generateTeacherPasswordAction($user->getSlug());
        }

        $userManager = $this->get('bns.user_manager');
        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $this->get('bns.right_manager')->getCurrentGroup();
        $classroomManager->setClassroom($classroom);


        if ($liste == null) {
            $liste = $classroomManager->getTeachers();
        }

        $users_list = $liste;


        $authorisedGroups = $this->get('bns.right_manager')->getGroupsWherePermission('SCHOOL_ACCESS_BACK');

        foreach ($authorisedGroups as $group) {


            if($classroom == $group){
                $responses = $userManager->resetUsersPassword($users_list, false);


                // Populate array pupil/parents
                foreach ($responses as $response) {
                    foreach ($users_list as $usr) {
                        if ($response['id'] == $usr->getId()) {
                            $usr->setPassword($response['plain_password']);
                            break 1;
                        }
                    }
                }

                $cardsPerPage = 8;

                //Calcul du nombre de pages à la génération du pdf
                $nb = (count($users_list) % $cardsPerPage) == 0 ? (count($users_list) / $cardsPerPage) : floor(count($users_list) / $cardsPerPage) + 1;


                $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_teachers.html.twig', array(
                    'teachers' => $users_list,
                    'nbPages' => $nb - 1
                ));

                return new Response(
                    $this->get('knp_snappy.pdf')->getOutputFromHtml($html, array(
                        'margin-left' => '5',
                        'margin-right' => '4',
                    )),
                    200,
                    array(
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' .
                            $this->get('translator')->trans('TEACHERS_FILENAME_BENEYLU', array('%group%' => $classroom->getSlug()), 'SCHOOL') . '"'
                    )
                );
            }
        }$this->get('bns.right_manager')->forbidIf(true);
    }

    private function renderTeacherCard(User $user, $isTeacher)
    {
        $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_user.html.twig', array(
            'user'  => $user,
            'role'	=> $this->get('bns.role_manager')->getGroupTypeRoleFromId($user->getHighRoleId()),
            'base_url' => $this->container->getParameter('application_base_url')
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html, array(
                'margin-left' => '5',
                'margin-right' => '4',
                'margin-top' => '4',
                'margin-bottom' => '5'
            )),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="'.$this->get('translator')->trans('TEACHER_FILENAME_BENEYLU', array('%beneylu_brand_name%' => $this->getParameter('beneylu_brand_name'), '%user%' => $user->getFullName()), 'SCHOOL').'"'
            )
        );
    }

    /**
     * @param User $user
     *
     * @return boolean
     */
    protected function canViewProfile($user)
    {
        if ($this->getUser()->getId() == $user->getId()) {
            return true;
        } else {
            $gm = $this->get('bns.group_manager');
            $authorisedGroups = $this->get('bns.right_manager')->getGroupsWherePermission('SCHOOL_ACCESS_BACK');

            foreach ($authorisedGroups as $group) {
                $gm->setGroup($group);


                if (in_array($user->getId(), $gm->getUsersIds())) {
                    return true;
                }
            }
        }

        $this->get('bns.right_manager')->forbidIf(true);
    }
}

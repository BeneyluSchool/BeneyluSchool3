<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\ClassroomBundle\Form\Model\ProfileFormModel;
use BNS\App\ClassroomBundle\Form\Type\ProfileType;
use BNS\App\ClassroomBundle\Form\Type\SelectUsersCardType;
use BNS\App\ClassroomBundle\Form\Model\NewUserInClassroomFormModel;
use BNS\App\ClassroomBundle\Form\Type\ImportPupilFromCSVType;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\PupilAssistantLinkQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * @author Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 * @author Eric Chau       <eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackClassroomController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_classroom", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * Page récapitulant la classe, et ses utilisateurs
     */
    public function indexAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($rightManager->getCurrentGroup());

        $listeEleves = $classroomManager->getPupils();
        $listeParents = $classroomManager->getPupilsParents();

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $form = $this->createForm(new SelectUsersCardType($listeEleves, $listeParents));
        $form->handleRequest($request);

        // Ajout des utilisateurs séléctionnés à la liste
        if ($this->getRequest()->getMethod() == 'POST') {
            if ($form->isValid()) {
                $elevesSelection = array();
                for ($i = 0; $i < count($listeEleves); $i++) {
                    if ($form->getData()[$i]) {
                        array_push($elevesSelection, $listeEleves[$i]);
                    }
                }
                for ($i = count($listeEleves); $i < count($listeEleves) + count($listeParents); $i++) {
                    if ($form->getData()[$i]) {
                        array_push($elevesSelection, $listeParents[$i - count($listeEleves)]);
                    }
                }
                if (empty($elevesSelection)) {
                    $elevesSelection = null;
                }
                return $this->generateClassroomCardAction($elevesSelection);
            }
        }

        return $this->render('BNSAppClassroomBundle:BackClassroom:classroom_users_index.html.twig', array(
            'teachers' => $classroomManager->getTeachers(),
            'pupils' => $classroomManager->getPupils(),
            'classroom' => $rightManager->getCurrentGroup(),
            'form' => $form->createView(),
            'assistants' => $classroomManager->getAssistants(),
            'hasGroupBoard' => $hasGroupBoard
        ));
    }

    /**
     * @Route("/ajouter-un-enseignant", name="classroom_manager_add_teacher", defaults={"isTeacher"=true}, options={"expose"=true})
     * @Route("/ajouter-un-eleve", name="classroom_manager_add_pupil", defaults={"isTeacher"=false}, options={"expose"=true})
     * @Rights("CLASSROOM_CREATE_USER")
     */
    public function renderAddUserAction($isTeacher = false)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
        }

        $form = $this->createForm(new NewUserInClassroomType($isTeacher, true), new NewUserInClassroomFormModel($isTeacher, true));
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid()) {
                $userProxy = $form->getData();
                $userProxy->save();

                $this->get('bns.classroom_manager')->setClassroom($this->get('bns.right_manager')->getCurrentGroup());

                if ($isTeacher) {
                    $this->get('bns.classroom_manager')->assignTeacher($userProxy->getObjectUser());
                    $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('TEACHER_CREATED_SUCCESS', array(), 'CLASSROOM'));
                } else {
                    $this->get('bns.classroom_manager')->assignPupil($userProxy->getObjectUser());
                    $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('PUPIL_CREATED_SUCCESS', array(), 'CLASSROOM'));
                    //statistic action
                    $this->get("stat.classroom")->createStudentAccount();
                }

                $user = UserQuery::create()->findOneBySlug($userProxy->getObjectUser()->getSlug());
                $user->setIsEnabled(true);

                $form = $this->createForm(new NewUserInClassroomType($isTeacher), new NewUserInClassroomFormModel($isTeacher));
                return $this->render('BNSAppClassroomBundle:BackClassroomModal:add_user_' . ($isTeacher ? 'teacher' : 'pupil') . '_form.html.twig', array(
                        'form' => $form->createView(),
                        'lastRegistedUser' => ($isTeacher ? $this->render('BNSAppClassroomBundle:BackClassroom:row_teacher.html.twig', array(
                            'teacher' => $user))->getContent() : $this->render('BNSAppClassroomBundle:BackClassroom:row_pupil.html.twig', array(
                            'pupil' => $user))->getContent())
                    )
                );
            }
        }

        return $this->render('BNSAppClassroomBundle:BackClassroomModal:add_user_' . ($isTeacher ? 'teacher' : 'pupil') . '_form.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/ajouter-un-assistant", name="classroom_manager_add_assistant", options={"expose"=true})
     * @Rights("CLASSROOM_CREATE_ASSISTANT")
     */
    public function renderAddAssistantAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
        }

        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());

        $addAssistantToPupil = false;
        $pupil = UserQuery::create()->findPk($request->get('userId'));
        if ($pupil) {
            if ($classroomManager->isOneOfMyPupils($pupil)) {
                $addAssistantToPupil = true;
            } else {
                throw $this->createAccessDeniedException('You cannot link an assistant to this pupil');
            }
        }

        $form = $this->createForm(new NewUserInClassroomType(true), new NewUserInClassroomFormModel(true, false));
        $form->handleRequest($request);

        if ($form->isValid()) {
            $userProxy = $form->getData();
            $userProxy->save();

            if (!$addAssistantToPupil) {
                $classroomManager->assignAssistant($userProxy->getObjectUser());
            }

            $user = UserQuery::create()->findOneBySlug($userProxy->getObjectUser()->getSlug());
            $user->setIsEnabled(true);
            if ($addAssistantToPupil) {
                $userLink = PupilAssistantLinkQuery::create()
                    ->filterByPupilId($pupil->getId())
                    ->filterByAssistantId($user->getId())
                    ->findOneOrCreate();
                $userLink->save();

                if (!$user->getHighRoleId()) {
                    // Set the right highRoleId for pupil's assistant without role
                    $assistantRole = GroupTypeQuery::create()
                        ->filterByType('ASSISTANT')
                        ->findOne();
                    if ($assistantRole) {
                        $user->setHighRoleId($assistantRole->getId());
                        $user->save();
                    }
                }
            }


            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('ASSISTANT_CREATED_SUCCESS', array(), 'CLASSROOM'));

            $form = $this->createForm(new NewUserInClassroomType(true), new NewUserInClassroomFormModel(true, false));
            return $this->render('BNSAppClassroomBundle:BackClassroomModal:add_user_assistant_form.html.twig', array(
                    'form' => $form->createView(),
                    'lastRegistedUser' => $this->render('BNSAppClassroomBundle:BackClassroom:row_teacher.html.twig', array('teacher' => $user))->getContent()
                )
            );
        }

        return $this->render('BNSAppClassroomBundle:BackClassroomModal:add_user_assistant_form.html.twig', array(
            'form' => $form->createView(),
            'userId' => $request->get('userId')
        ));
    }



    /**
     * @Route("/supprimer-utilisateur/{userSlug}", name="BNSAppClassroomBundle_back_delete_pupil")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $userSlug
     */
    /* INUTILISE
    public function deletePupilAction($userSlug)
    {
        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $user = $this->getClassroomUser($userSlug);
        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($rightManager->getCurrentGroup());
        $classroomManager->removeUser($user);

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
    }
     */

    /**
     * @Route("/quitter-classe/{teacherLogin}", name="back_classroom_teacher_leave_classroom")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $teacherSlug
     */
    public function teacherLeaveClassroomAction($teacherLogin)
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $teacher = $this->get('bns.user_manager')->findUserByLogin($teacherLogin);

        // Check rights
        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
        if (!$classroomManager->isOneOfMyPupils($teacher) && !$classroomManager->isOneOfMyTeachers($teacher) && !$classroomManager->isOneOfMyAssistants($teacher)) {
            $this->get('bns.right_manager')->forbidIf(true);
        }

        $this->get('bns.classroom_manager')
            ->setClassroom($rightManager->getCurrentGroup())
            ->removeUser($teacher);

        if ($teacherLogin == $this->getUser()->getLogin()) {
            // Réinitialisation des droits et du contexte
            $rightManager->initContext();
            $rightManager->reloadRights();

            return $this->redirect($this->generateUrl('home'));
        }

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
    }

    /**
     * @Route("/nouveau-compte-parent/{pupilSlug}", name="back_classroom_add_new_pupil_parent")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function addNewParentForPupilAction($pupilSlug)
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $user = UserQuery::create('u')
            ->where('u.Slug = ?', $pupilSlug)
            ->findOne();

        if (null == $user) {
            throw new NotFoundHttpException('The user with slug : ' . $pupilSlug . ' is NOT found !');
        }
        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
        $classroomManager->createParentAccount($user);

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array(
            'userSlug' => $pupilSlug
        )));
    }

    /**
     * @Route("/desactiver-compte/{userSlug}", name="back_classroom_disable_user_account")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function disableUserAction($userSlug)
    {
        $user = UserQuery::create('u')
            ->where('u.Slug = ?', $userSlug)
            ->findOne();

        if (null == $user) {
            throw new NotFoundHttpException('The user with slug : ' . $userSlug . ' is NOT found !');
        }

        $userManager = $this->get('bns.user_manager');

        $userIds = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersIds();

        if (in_array($user->getId(), $userIds)) {
            $userManager->disableUser($user);

            /* on verifie si les parents de l'élève ne sont pas associé à un autre élève
              Si ce la cas et que au moins un un de ces élèves sont activé, on ne fais rien
              Sinon on désactive les comptes^parents associés
             */
            $parents = $userManager->getUserParent($user);

            foreach ($parents as $parent) {
                $parentChildrens = $userManager->getUserChildren($parent);
                if ((null == $parentChildrens || sizeof($parentChildrens) <= 1)
                    && $this->getUser()->getId() != $parent->getId()
                ) {
                    $userManager->disableUser($parent);
                }
            }

            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array(
                'userSlug' => $userSlug
            )));

        } else {
            $this->get('bns.right_manager')->forbidIf(true);
        }
    }

    /**
     * @Route("/reactiver-compte/{userSlug}", name="back_classroom_enable_user_account")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function enableUserAction($userSlug)
    {
        $user = UserQuery::create('u')
            ->where('u.Slug = ?', $userSlug)
            ->findOne();

        if (null == $user) {
            throw new NotFoundHttpException('The user with slug : ' . $userSlug . ' is NOT found !');
        }

        $userIds = $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersIds();

        if (in_array($user->getId(), $userIds)) {
            $this->get('bns.user_manager')->enableUser($user, true);

            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array(
                'userSlug' => $userSlug
            )));

        } else {
            $this->get('bns.right_manager')->forbidIf(true);
        }
    }

    /**
     * @Route("/verifier-nom-utilisateur", name="classroom_manager_verify_username", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function verifyUsernameAction()
    {
        $request = $this->getRequest();
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $username = $request->get('username', null);
        if (null == $username) {
            throw new \InvalidArgumentException('The parameter "username" is missing !');
        }

        $user = $this->get('bns.user_manager')->findUserByLogin($username, true);
        $isAlreadyInvited = $isAlreadyTeacher = false;

        if (null != $user) {
            $classroomManager = $this->get('bns.classroom_manager');
            $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
            $isAlreadyInvited = $classroomManager->isInvitedInClassroom($user);

            if (!$isAlreadyInvited) {
                $isAlreadyTeacher = $classroomManager->isOneOfMyTeachers($user);
            }
        }

        return $this->render('BNSAppClassroomBundle:BackClassroomModal:check_username_result_block.html.twig', array(
            'user' => $user,
            'username' => $username,
            'is_already_invited' => $isAlreadyInvited,
            'is_already_teacher' => $isAlreadyTeacher
        ));
    }

    /**
     * @Route("/inviter-enseignant", name="classroom_manager_invite_teacher", options={"expose"=true})
     * @Rights("CLASSROOM_CREATE_USER")
     * @return Response
     * @throws HttpException
     */
    public function inviteTeacherInClassroomAction()
    {
        $request = $this->getRequest();
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $username = $request->get('username', null);
        if (null == $username) {
            throw new \InvalidArgumentException('The parameter "username" is missing !');
        }

        $currentClassroom = $this->get('bns.right_manager')->getCurrentGroup();
        $userToInvite = $this->get('bns.user_manager')->findUserByLogin($username);
        $this->get('bns.classroom_manager')
            ->setClassroom($currentClassroom)
            ->inviteTeacherInClassroom($userToInvite, $this->getUser());

        return new Response();
    }

    /**
     * @Route("/verifier-nom-assistant", name="classroom_manager_verify_assistant", options={"expose"=true})
     * @Rights("CLASSROOM_CREATE_ASSISTANT")
     */
    public function verifyAssistantUsernameAction(Request $request)
    {
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $username = $request->get('username', null);
        if (null == $username) {
            throw new \InvalidArgumentException('The parameter "username" is missing !');
        }

        $user = $this->get('bns.user_manager')->findUserByLogin($username, true);
        $isAlreadyInvited = $isAlreadyAssistant = false;

        if (null != $user) {
            $classroomManager = $this->get('bns.classroom_manager');
            $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
            $pupil = UserQuery::create()->findPk($request->get('userId'));
            $assignToPupil = false;
            if ($pupil) {
                if ($classroomManager->isOneOfMyPupils($pupil)) {
                    $assignToPupil = true;
                } else {
                    throw $this->createAccessDeniedException('pupil should be in your classroom');
                }
            }

            if (!$assignToPupil) {
                $isAlreadyInvited = $classroomManager->isInvitedAsAssistantInClassroom($user);

                if (!$isAlreadyInvited) {
                    $isAlreadyAssistant = $classroomManager->isOneOfMyAssistants($user);
                }
            }
        }

        return $this->render('BNSAppClassroomBundle:BackClassroomModal:check_assistant_result_block.html.twig', array(
            'user' => $user,
            'username' => $username,
            'is_already_invited' => $isAlreadyInvited,
            'is_already_assistant' => $isAlreadyAssistant,
            'userId' => $request->get('userId')
        ));
    }

    /**
     * @Route("/inviter-assistant", name="classroom_manager_invite_assistant", options={"expose"=true})
     * @Rights("CLASSROOM_CREATE_ASSISTANT")
     * @return Response
     * @throws HttpException
     */
    public function inviteAssistantInClassroomAction(Request $request)
    {
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $username = $request->get('username', null);
        $userToInvite = $this->get('bns.user_manager')->findUserByLogin($username);
        if (!$userToInvite) {
            throw new \InvalidArgumentException('The parameter "username" is missing !');
        }

        $classroomManager = $this->get('bns.classroom_manager')->setClassroom($this->get('bns.right_manager')->getCurrentGroup());

        $pupil = UserQuery::create()->findPk($request->get('userId'));
        if ($pupil) {
            if ($classroomManager->isOneOfMyPupils($pupil)) {
                $assignToPupil = true;
                $link = PupilAssistantLinkQuery::create()
                    ->filterByAssistantId($userToInvite->getId())
                    ->filterByPupilId($pupil->getId())
                    ->findOneOrCreate();
                $link->save();

                $this->get('bns.user_manager')->setUser($userToInvite)->resetRights();

                return new Response();
            } else {
                throw $this->createAccessDeniedException('pupil should be in your classroom');
            }
        }

        $classroomManager->inviteAssistantInClassroom($userToInvite, $this->getUser());

        return new Response();
    }

    /**
     * @Route("/generer-nouvelle-fiche-eleve/{pupilSlug}", name="back_classrooms_users_generate_pupil_pwd", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param string $pupilSlug
     */
    public function generatePupilPasswordAction($pupilSlug)
    {
        $userManager = $this->get('bns.user_manager');
        $pupil = $userManager->findUserBySlug($pupilSlug);

        $this->canViewProfile($pupil);

        $pupil = $userManager->resetUserPassword($pupil);

        return $this->renderPupilOrParentCard($pupil, true);
    }

    /**
     * @Route("/generer-nouvelle-fiche-auxiliere/{userSlug}", name="back_classrooms_users_generate_auxiliere_pwd", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param string $userSlug
     */
    public function generatePupilAssistantPasswordAction($userSlug)
    {
        $userManager = $this->get('bns.user_manager');
        $pupilAssistant = $userManager->findUserBySlug($userSlug);

        $pupilAssistant = $userManager->resetUserPassword($pupilAssistant);

        $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_user.html.twig', array(
            'user' => $pupilAssistant,
            'role' => $this->get('bns.role_manager')->getGroupTypeRoleFromId($pupilAssistant->getHighRoleId()),
            'base_url' => $this->container->getParameter('application_base_url')
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Beneylu School - Fiche - ' . $pupilAssistant->getFullName() . '.pdf"'
            )
        );
    }

    /**
     * @Route("/generer-nouvelle-fiche-parent/{parentSlug}", name="back_classrooms_users_generate_parent_pwd", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param type $parentSlug
     */
    public function generateParentPasswordAction($parentSlug)
    {
        $userManager = $this->get('bns.user_manager');
        $parent = $userManager->findUserBySlug($parentSlug);

        $this->canViewProfile($parent);

        $parent = $userManager->resetUserPassword($parent);

        return $this->renderPupilOrParentCard($parent, false);
    }

    /**
     * @Route("/generer-fiche-classe", name="classroom_manager_generate_classroom_card")
     *
     * @param array $users
     */
    public function generateClassroomCardAction($users = null)
    {
        //S'il n'y a qu'un utilisateur, on appelle la méthode de génération de fiche unique
        if (1 === count($users)) {
            /** @var User $user */
            $user = $users[0];
            if ($user->isChild()) {
                return $this->generatePupilPasswordAction($user->getSlug());
            }

            return $this->generateParentPasswordAction($user->getSlug());
        }
        if (!$this->get('bns.right_manager')->hasRight('CLASSROOM_ACCESS_BACK')) {
            throw $this->createAccessDeniedException();
        }

        $userManager = $this->get('bns.user_manager');
        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $this->get('bns.right_manager')->getCurrentGroup();
        $classroomManager->setClassroom($classroom);

        if (!$users) {
            $users = $classroomManager->getPupils();
        }


        $userManager->resetUsersPassword($users, true);

        $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_group.html.twig', array(
            'users' => $users,
            'classroom' => $classroom,
            'pupilIds' => $classroomManager->getUsersByRoleUniqueNameIds('PUPIL')
        ));
//        return new Response($html);

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html, array(
                'margin-left' => '5',
                'margin-right' => '4',
            )),
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' .
                    $this->get('translator')->trans('GROUP_FILENAME_BENEYLU', array('%group%' => $classroom->getSlug()), 'CLASSROOM') . '"'
            )
        );
    }

    private function renderPupilOrParentCard(User $user, $isPupil)
    {
        $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_user.html.twig', array(
            'user' => $user,
            'role' => $this->get('bns.role_manager')->getGroupTypeRoleFromId($user->getHighRoleId()),
            'base_url' => $this->container->getParameter('application_base_url')
        ));

        if ($this->container->hasParameter('graphic_chart')) {
            $chart = $this->container->getParameter('graphic_chart');
            $title = $chart['label'];
        } else {
            $title = $this->getParameter('beneylu_brand_name');
        }

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html, array(
                'margin-left' => '5',
                'margin-right' => '4',
                'margin-top' => '4',
                'margin-bottom' => '5'
            )),
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . ($isPupil ?
                        $this->get('translator')->trans('CHILD_FILENAME_BENEYLU', array('%beneylu_brand_name%' => $title, '%user%' => $user->getFullName()), 'CLASSROOM')
                        :
                        $this->get('translator')->trans('ADULT_FILENAME_BENEYLU', array('%beneylu_brand_name%' => $title, '%user%' => $user->getFullName()), 'CLASSROOM')
                    ) . '"'
            )
        );
    }


    /**
     * @Route("/fiche-utilisateur/{userSlug}", name="BNSAppClassroomBundle_back_pupil_detail")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $login
     */
    public function userDetailAction($userSlug)
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());


        $user = $this->getClassroomUser($userSlug);

        $userManager = $this->get('bns.user_manager');
        $centralUser = $userManager->getUserFromCentral($user->getLogin());

        $userManager->setUser($user);
        $rightManager->forbidIf($userManager->getMainRole() === 'parent');

        if ($userManager->isAdult()) {
            return $this->render('BNSAppClassroomBundle:BackClassroom:user_details.html.twig', array(
                'user' => $user,
                'isEnabled' => $centralUser['enabled'],
                'classroom' => $rightManager->getCurrentGroup()
            ));
        } else {
            $parents = $userManager->getUserParent($user);
            $assistants = $user->getAssistants();

            return $this->render('BNSAppClassroomBundle:BackClassroom:user_details.html.twig', [
                'user' => $user,
                'isEnabled' => $centralUser['enabled'],
                'classroom' => $rightManager->getCurrentGroup(),
                'parents' => $parents,
                'assistants' => $assistants
            ]);
        }
    }

    /**
     * @Route("/fiche-utilisateur/{userSlug}/editer", name="BNSAppClassroomBundle_back_pupil_detail_edit")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $login
     */
    public function userDetailEditAction($userSlug)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());
        $user = $this->getClassroomUser($userSlug);
        $userManager->setUser($user);

        $form = $this->createForm(new ProfileType($user, $this->container->getParameter('available_languages')), new ProfileFormModel($user));

        if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());
            if ($form->isValid()) {
                $data = $form->getData();
                if ($form->getData()->lang != null) {
                    $this->get('bns.right_manager')->setLocale($form->getData()->lang);
                }
                if (isset($data->parentsIdsToDissociate) && $data->parentsIdsToDissociate != null) {
                    //Récupération des parents à dissocier
                    $parentsIdsToDissociate = array_unique(explode(',', $data->parentsIdsToDissociate));
                    if (!is_array($parentsIdsToDissociate)) {
                        $parentsIdsToDissociate = array($data->parentsIdsToDissociate);
                    }
                    $parents = UserQuery::create()->findById($parentsIdsToDissociate);
                    foreach ($parents as $parent) {
                        $userManager->unlinkPupilFromParent($user, $parent);
                    }
                }

                if ($user->isChild() && $assistantIds = $form->get('assistantsIdsToDissociate')->getData()) {
                    $assistantIds = array_unique(explode(',', $assistantIds));
                    foreach ($assistantIds as $assistantId) {
                        PupilAssistantLinkQuery::create()
                            ->filterByPupilId($user->getId())
                            ->filterByAssistantId($assistantId)
                            ->delete();

                        // reset user right
                        $userManager->setUser($user)->resetRights();
                    }
                }

                $form->getData()->save();
                if (!$user->isAdult() || !$userManager->getUserChildren($user)) {
                    return new RedirectResponse($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array('userSlug' => $user->getSlug())));
                } else {
                    return new RedirectResponse($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array('userSlug' => $userManager->getUserChildren($user)[0]->getSlug())));
                }
            }
        }

        if ($userManager->isAdult()) {
            $children = $userManager->getUserChildren($user);
            return $this->render('BNSAppClassroomBundle:BackClassroom:user_details_edit.html.twig', array(
                'user' => $user,
                'isAdult' => $user->isAdult(),
                'child' => isset($children[0]) ? $children[0] : null,
                'classroom' => $rightManager->getCurrentGroup(),
                'form' => $form->createView()
            ));
        } else {
            $parents = $userManager->getUserParent($user);
            $assistants = $user->getAssistants();

            return $this->render('BNSAppClassroomBundle:BackClassroom:user_details_edit.html.twig', array(
                'user' => $user,
                'classroom' => $rightManager->getCurrentGroup(),
                'parents' => $parents,
                'assistants' => $assistants,
                'isAdult' => $user->isAdult(),
                'form' => $form->createView()
            ));
        }
    }

    /**
     * @Route("/importer-eleve-depuis-ficher-csv", name="back_classroom_users_import_csv_pupil")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function importPupilFromCSVIndexAction()
    {
        return $this->render('BNSAppClassroomBundle:BackClassroom:pupil_import.html.twig', array(
            'form' => $this->createForm(new ImportPupilFromCSVType())->createView()
        ));
    }

    /**
     * Action appelé lorsque l'utilisateur clique sur le bouton "J'ai terminé" de la page d'importation d'élèves grâce à un fichier CSV
     *
     * @Route("/importer-eleve", name="back_classroom_users_do_import_pupil_from_csv")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function doImportPupilFromCSVAction()
    {
        $request = $this->getRequest();
        if (!$request->isMethod('POST')) {
            throw new HttpException(500, 'Request must be `POST`\'s method!');
        }

        $form = $this->createForm(new ImportPupilFromCSVType());
        $form->bind($request);
        if (null !== $form['file']->getData() && null !== $form['format']->getData() && $form->isValid()) {
            try {
                $result = $this->get('bns.classroom_manager')
                    ->setClassroom($this->get('bns.right_manager')->getCurrentGroup())
                    ->importPupilFromCSVFile($form['file']->getData(), $form['format']->getData());

                if ($result['success_insertion_count'] == $result['user_count']) {
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        $this->get('translator')->trans('FLASH_PROCESS_CSV_IMPORT', array('%userCount%' => $result['user_count']), "CLASSROOM"));
                } else {
                    $this->get('session')->getFlashBag()->add(
                        'error',
                        $this->get('translator')->trans('FLASH_PROCESS_CSV_IMPORT_ERROR', array(
                            '%resultSuccess%' => $result['success_insertion_count'],
                            '%skiped%' => $result['skiped_count'],
                            '%failed%' => ($result['user_count'] - $result['success_insertion_count'] - $result['skiped_count']),
                        ), "CLASSROOM"));
                    return $this->redirect($this->generateUrl('back_classroom_users_import_csv_pupil'));
                }
            } catch (UploadException $e) {
                if ($e->getCode() == 1) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT', array(), "CLASSROOM");
                } elseif ($e->getCode() == 2) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_PUPIL_FORMAT', array(), "CLASSROOM");
                } elseif ($e->getCode() == 3) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_BENEYLU_FORMAT', array(), "CLASSROOM");
                } else {
                    $msg = $this->get('translator')->trans('FLASH_ERROR_CONTACT_BENEYLU', array('%beneylu_brand_name%' => $this->container->getParameter('beneylu_brand_name')), "CLASSROOM");
                }

                $this->get('session')->getFlashBag()->add('error', $msg);

                return $this->redirect($this->generateUrl('back_classroom_users_import_csv_pupil'));
            }

            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
        }

        $this->get('session')->getFlashBag()->add('submit_import_form_error', '');

        return $this->render('BNSAppClassroomBundle:BackClassroom:pupil_import.html.twig', array('form' => $form->createView()));
    }

    /**
     * @param string $userSlug
     *
     * @return User
     *
     * @throws HttpException
     */
    private function getClassroomUser($userSlug)
    {
        $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
        if (null == $user) {
            throw new NotFoundHttpException('Pupil slug (' . $userSlug . ') given does NOT exist !');
        }

        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
        if (!$classroomManager->isOneOfMyPupils($user) && !$classroomManager->isOneOfMyTeachers($user) && !$classroomManager->isOneOfMyPupilsParents($user) && !$classroomManager->isOneOfMyAssistants($user)) {
            $this->get('bns.right_manager')->forbidIf(true);
        }

        return $user;
    }

    /**
     * @Route("{userSlug}/mot-de-passe/generer", name="classroom_manager_generate_user_password")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function generateNewUserPasswordAction($userSlug)
    {
        $user = UserQuery::create('u')
            ->where('u.Slug = ?', $userSlug)
            ->findOne();

        if (null == $user) {
            throw new NotFoundHttpException('The user with slug : ' . $userSlug . ' is NOT found !');
        }

        // Check rights
        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
        if (!$classroomManager->isOneOfMyPupils($user) && !$classroomManager->isOneOfMyTeachers($user) && !$classroomManager->isOneOfMyAssistants($user)) {
            $this->get('bns.right_manager')->forbidIf(true);
        }

        // L'utilisateur courant tente de régénérer son mot de passe, on le redirige vers la page directement
        if ($user->getId() == $this->getUser()->getId()) {
            $this->getRequest()->getSession()->set('regeneration_process', 'true');
            $this->get('bns.user_manager')->flagChangePassword($this->getUser());
            return $this->redirect($this->generateUrl('user_password'));
        }

        // Finally
        $this->get('bns.user_manager')->resetUserPassword($user);
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_RESET_PASSWORD_SUCCESS', array(), "CLASSROOM"));

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_pupil_detail', array(
            'userSlug' => $userSlug
        )));
    }

    /**
     * @Route("/exporter-eleve", name="classroom_manager_export_pupils_csv")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function exportPupilsToCSVAction()
    {
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $csv = $this->get('bns.classroom_manager')->setGroup($currentGroup)->exportPupilsToCSV();

        $response = new Response();
        $response->headers->set('Content-Encoding', 'UTF-8');
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->get('translator')->trans('FILENAME_EXPORT', array('%beneylu_brand_name%' => $this->getParameter('beneylu_brand_name'), '%group%' => $currentGroup->getSlug()), 'CLASSROOM') . '"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Expires', '0');
        $response->headers->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'Public');

        $response->setContent("\xEF\xBB\xBF" . $csv);

        return $response;
    }

    /**
     * @Route("/importer-eleve-textarea", name="back_classroom_users_import_pupil_from_textarea")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function importPupilsFromTextareaAction(Request $request)
    {
        $pupils = $request->get('data');
        $pupilsFinaleList = $this->get('bns.user_manager')->textToUserArray($pupils);

        return $this->render('BNSAppClassroomBundle:BackClassroomModal:list_pupils.html.twig', array('pupilsList' => $pupilsFinaleList));

    }

    /**
     * @Route("/valider-importer-eleve-textarea", name="back_classroom_users_import_pupil_from_textarea_validation")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function doImportPupilsFromTextareaAction(Request $request)
    {
        $pupils = $request->get('data');
        $pupilsList = $this->get('bns.user_manager')->textToUserArray($pupils);

        if (null != $pupilsList) {
            $result = $this->get('bns.classroom_manager')
                ->setClassroom($this->get('bns.right_manager')->getCurrentGroup())
                ->importPupilFromTextarea($pupilsList);

            if ($result['success_insertion_count'] == $result['user_count']) {
                $msgType = 'success';
                $msg = $this->get('translator')->trans('FLASH_PROCESS_IMPORT_SUCCESS', array('%user%' => $result['user_count']), "CLASSROOM");
            } else {
                $msgType = 'error';
                $msg = $this->get('translator')->trans('FLASH_PROCESS_CSV_IMPORT_ERROR', array(
                    '%resultSuccess%' => $result['success_insertion_count'],
                    '%skiped%' => $result['skiped_count'],
                ), "CLASSROOM");
            }
        }
        $this->get('session')->getFlashBag()->add($msgType, $msg);

        return new Response($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
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
            $authorisedGroups = $this->get('bns.right_manager')->getGroupsWherePermission('CLASSROOM_ACCESS_BACK');

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

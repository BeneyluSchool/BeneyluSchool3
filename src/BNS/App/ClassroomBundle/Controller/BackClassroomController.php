<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;

use Criteria;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\ProfileQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ProfilePreferenceQuery;
use BNS\App\CoreBundle\Model\ProfilePeer;
use BNS\App\CoreBundle\Model\ProfilePreferencePeer;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use BNS\App\ClassroomBundle\Form\Type\ImportPupilFromCSVType;

/**
 * @author Eymeric & Eric
 */
class BackClassroomController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_classroom")
     * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
     * Page récapitulant la classe, est ses utilisateurs
     */
    public function indexAction()
    {
        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($rightManager->getCurrentGroup());

        return $this->render('BNSAppClassroomBundle:BackClassroom:classroom_users_index.html.twig', array(
            'teachers'	=> $classroomManager->getTeachers(),
            'pupils'	=> $classroomManager->getPupils(),
			'classroom'	=> $rightManager->getCurrentGroup()
        ));	
    }
    
	/**
	 * @Route("/generer-formulaire-ajout-utilisateur", name="classroom_users_render_add_user_modal_body", options={"expose"=true})
	 */
	public function renderAddUserModalBodyAction()
	{
		$request = $this->getRequest();
		if (false === $request->isXmlHttpRequest()) {
			throw new HttpException(500, 'Must be XmlHttpRequest!');
		}
		
		$role = $request->get('user_role_requested', null);
		if ('POST' != $request->getMethod() || null == $role) {
			throw new HttpException(500, 'You must provide `user_role_requested` with `POST`\'s method!');
		}
		
		return $this->render('BNSAppClassroomBundle:BackClassroomModal:add_user_modal_form.html.twig', array(
			'role' => $role,
			'form' => $this->createForm(new NewUserInClassroomType(strcmp($role, 'teacher') == 0))->createView()
		));
	}
	
	/**
	 * @Route("/ajouter-utilisateur", name="classroom_users_add_user", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 */
	public function addUserAction()
	{
		$request = $this->getRequest();
		if (false === $request->isXmlHttpRequest()) {
			throw new HttpException(500, 'Must be XmlHttpRequest!');
		}
		
		$role = $request->get('user_role', null);
		if ('POST' != $request->getMethod() || null == $role) {
			throw new HttpException(500, 'You must provide `user_role_requested` with `POST`\'s method!');
		}
		
		$form =  $this->createForm(new NewUserInClassroomType(strcmp($role, 'teacher') == 0));
		$form->bindRequest($this->getRequest());
		if ($form['last_name']->getData() != null && $form['first_name'] != null && $form->isValid()) {
			$userProxy = $form->getData();
			$userProxy->save();
			$this->get('bns.classroom_manager')->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
			$view = 'BNSAppClassroomBundle:BackClassroom:row_pupil.html.twig';
			$params = array();
			if (0 == strcmp('teacher', $role)) {
				$this->get('bns.classroom_manager')->assignTeacher($userProxy->getObjectUser());
				$view = 'BNSAppClassroomBundle:BackClassroom:row_teacher.html.twig';
				$params['teacher'] = $userProxy->getObjectUser();
                $params['teacher_count'] = 2; // On met un chiffre supérieur au hasard pour éviter de faire un accès en BDD inutile
			}
			else {
				$this->get('bns.classroom_manager')->assignPupil($userProxy->getObjectUser());
				$params['pupil'] = $userProxy->getObjectUser();
			}
			
			return $this->render($view, $params);
		}
		
		return new Response(json_encode(false));
	}
	
    /**
     * @Route("/supprimer-eleve/{userSlug}", name="BNSAppClassroomBundle_back_delete_pupil")
     * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
     * @param String $userSlug
     */
    public function deletePupilAction($userSlug)
    {
        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());
        
        $user = $this->isMyPupils($userSlug);
		$this->get('bns.classroom_manager')->setClassroom($rightManager->getCurrentGroup());
        $this->get('bns.classroom_manager')->removeUser($user);
        
        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
    }
	
	    /**
     * @Route("/quitter-classe/{teacherLogin}", name="back_classroom_teacher_leave_classroom")
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
		$this->get('bns.classroom_manager')
			->setClassroom($rightManager->getCurrentGroup())
			->removeUser($teacher);
		
		// Réinitialisation des droits et du contexte
		$rightManager->reloadRights();
		$rightManager->initContext();
        
        return $this->redirect($this->generateUrl('home'));
    }
    
	/**
	 * @Route("/verifier-utilisateur", name="back_classrooms_users_check_username", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 */
	public function checkUserAction()
	{
		$request = $this->getRequest();
		if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
			throw new HttpException(500, 'Must be XmlHttpRequest and send by POST\'s method.');
		}
		
		$usernameToCheck = $request->get('username_to_check', null);
		if (null == $usernameToCheck) {
			throw new HttpException(500, 'username_to_check parameter is missing!');
		}
		
		$user = $this->get('bns.user_manager')->findUserByLogin($usernameToCheck, true);
		
		$isAlreadyInvited = $isAlreadyTeacher = false;
		if (null != $user) {
			$classroomManager = $this->get('bns.classroom_manager')->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
			$isAlreadyInvited = $classroomManager->isInvitedInClassroom($user);
			if (false === $isAlreadyInvited) {
				$isAlreadyTeacher = $classroomManager->isOneOfMyTeachers($user);
			}
		}
		
		return $this->render('BNSAppClassroomBundle:BackClassroomModal:check_username_result_block.html.twig', array(
			'user'					=> $user,
			'username'				=> $usernameToCheck,
			'is_already_invited'	=> $isAlreadyInvited,
			'is_already_teacher'	=> $isAlreadyTeacher
		));
	}
	
	/**
	 * @Route("/inviter-professeur", name="back_classrooms_users_invite_teacher", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @throws HttpException
	 */
	public function inviteTeacherInClassroomAction()
	{
		$request = $this->getRequest();
		if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
			throw new HttpException(500, 'Must be XmlHttpRequest and send by POST\'s method.');
		}
		
		$usernameToInvite = $request->get('username_to_invite', null);
		if (null == $usernameToInvite) {
			throw new HttpException(500, 'username_to_invite parameter is missing!');
		}
		
		$currentClassroom = $this->get('bns.right_manager')->getCurrentGroup();
		$userToInvite = $this->get('bns.user_manager')->findUserByLogin($usernameToInvite);
		$this->get('bns.classroom_manager')
			->setClassroom($currentClassroom)
			->inviteTeacherInClassroom($userToInvite, $this->getUser());
		
		return new Response(json_encode(true));
	}
	
	/**
	 * @Route("/generer-nouvelle-fiche-eleve/{pupilSlug}", name="back_classrooms_users_generate_pupil_pwd", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
	 * @param type $pupilSlug
	 */
	public function generatePupilPasswordAction($pupilSlug)
	{
		$userManager = $this->get('bns.user_manager');
		$pupil = $userManager->findUserBySlug($pupilSlug);
		$pupil = $userManager->resetUserPassword($pupil);
		
		return $this->renderPupilOrParentCard($pupil, true);
	}
	
	/**
	 * @Route("/generer-nouvel-fiche-parent/{pupilSlug}", name="back_classrooms_users_generate_parent_pwd", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
	 * @param type $parentSlug
	 */
	public function generateParentPasswordAction($pupilSlug)
	{
		$userManager = $this->get('bns.user_manager');
		$pupil = $userManager->findUserBySlug($pupilSlug);
		$parent = $userManager->getUserParent($pupil);
		
		if (null == $parent) {
			throw new HttpException(500, 'Pupil with slug '.$pupilSlug. ' is not link with any parent.');
		}
		
		$parent = $userManager->resetUserPassword($parent);
		
		return $this->renderPupilOrParentCard($parent, false);
	}
	
		
	private function renderPupilOrParentCard(User $user, $isPupil) 
	{
		$html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:user_card.html.twig', array(
			'user'  => $user,
			'role'	=> $this->get('bns.role_manager')->getGroupTypeRoleFromId($user->getHighRoleId())
		));
		
		return new Response(
			$this->get('knp_snappy.pdf')->getOutputFromHtml($html),
			200,
			array(
				'Content-Type'          => 'application/pdf',
				'Content-Disposition'   => 'attachment; filename="fiche-'.($isPupil? 'eleve' : 'parent').'-'.$user->getUsername().'.pdf"'
			)
		);
	}
	
	
    /**
     * @Route("/fiche-eleve/{userSlug}", name="BNSAppClassroomBundle_back_pupil_detail")
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 * 
     * @param String $login 
     */
    public function pupilDetailAction($userSlug)
    {
        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());
        
        $pupil = $this->isMyPupils($userSlug);
        
        $pupilProfile = ProfileQuery::create()
            ->joinWith('ProfilePreference', Criteria::LEFT_JOIN)
            ->add(ProfilePeer::ID, $pupil->getProfileId())
        ->findOne();
        
        $profilePreferences = ProfilePreferenceQuery::create()
            ->add(ProfilePreferencePeer::PROFILE_ID, $pupilProfile->getId())
        ->find();
        
        $pupilProfile->setProfilePreferences($profilePreferences);
        
        $pupil->setProfile($pupilProfile);
        
        return $this->render('BNSAppClassroomBundle:BackClassroom:pupil_detail.html.twig', array(
			'pupil'			=> $pupil,
        ));
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
		$form->bindRequest($request);
		if (null !== $form['file']->getData() && null !== $form['format']->getData() && $form->isValid()) {
			try {
				$result = $this->get('bns.classroom_manager')
					->setClassroom($this->get('bns.right_manager')->getCurrentGroup())
					->importPupilFromCSVFile($form['file']->getData(), $form['format']->getData());

				if ($result['success_insertion_count'] == $result['user_count']) {
					$this->get('session')->setFlash(
						'success', 
						'Le processus d\'importation d\'élève ('.$result['user_count'].') depuis votre fichier CSV s\'est déroulé avec succès.'
					);
				}
				else {
					$this->get('session')->setFlash(
						'error', 
						'Une erreur est survenu lors du processus d\'importation d\'élève depuis votre fichier CSV : '.
						$result['success_insertion_count'].' succès, '.($result['user_count'] - $result['success_insertion_count']).' échec(s).');
					
					return $this->redirect($this->generateUrl('back_classroom_users_import_csv_pupil'));
				}
			}
			catch (UploadException $e) {
				if ($e->getCode() == 1) {
					$msg = 'Le fichier CSV que vous avez choisi est incorrect, veuillez réessayer.';
				}
				elseif ($e->getCode() == 2) {
					$msg = 'Le fichier CSV que vous avez choisi n\'est pas conforme au format élève, veuillez vérifier votre fichier.';
				}
				elseif ($e->getCode() == 3) {
					$msg = 'Le fichier CSV que vous avez choisi n\'est pas conforme au format Beneyluschool, veuillez vérifier votre fichier.';
				}
				else {
					$msg = 'Une erreur est survenue, veuillez réessayer. Si le problème persiste, n\'hésitez pas à contacter l\'équipe Beneyluschool.';
				}
				
				$this->get('session')->getFlashBag()->add('error', $msg);
				
				return $this->redirect($this->generateUrl('back_classroom_users_import_csv_pupil'));
			}
			
			return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_classroom'));
		}
		
		$this->get('session')->setFlash('submit_import_form_error', '');
		
		return $this->render('BNSAppClassroomBundle:BackClassroom:pupil_import.html.twig', array('form' => $form->createView()));
	}
    
    private function isMyPupils($userSlug)
    {
        $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
        
        if (null == $user)
        {
            throw new HttpException(500, 'Pupil slug ('.$userSlug.') given does not exist!');
        }
        
        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());
        if (!$classroomManager->isOneOfMyPupils($user))
        {
            $this->get('bns.right_manager')->forbidIf(true);
        }
        
        return $user;
    }
}
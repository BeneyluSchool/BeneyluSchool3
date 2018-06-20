<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\AdminBundle\Form\Type\AddToGroupType;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Form\Type\UserType;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\GroupBundle\Controller\CommonController;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileAssistanceStartedNotification;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/gestion/utilisateur")
 */

class BackUserController extends CommonController
{


    /**
     * Redirige vers la fiche d'un utilisateur
     * @param User $user
     * @return Redirection
     */
    protected function redirectSheet(User $user)
    {
        return $this->redirect(
            $this->generateUrl(
                'BNSAppGroupBundle_back_user_sheet',
                array(
                    'userId'	=> $user->getId()
                )
            )
        );
    }

    /**
     * Fiche d'un utilisateur
	 * @Route("/fiche/{userId}", name="BNSAppGroupBundle_back_user_sheet")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function sheetAction($userId)
	{
        $session = $this->get('session');
        $user = UserQuery::create()->findOneById($userId);
        $user = $this->getAskedUser($user->getLogin(),'VIEW',false);
        if($user == false)
        {
            $session->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_READ_USER_CARD_ERROR', array(), 'GROUP'));
            return $this->redirect(
                $this->generateUrl(
                    'BNSAppGroupBundle_back'
                )
            );
        }
        $um = $this->get('bns.user_manager');
        $rm = $this->get('bns.right_manager');
        $groupManager = $rm->getCurrentGroupManager();
        $um->setUser($user);
        $mainRole = $um->getMainRole();
        $groupRoles = $um->getGroupsAndRolesUserBelongs();
        $um->setUser($rm->getUserSession());
        $centralUser = $um->getUserFromCentral($user->getLogin());
        $user->setIsEnabled($centralUser['enabled']);
        $form = $this->createForm(
            new AddToGroupType(
                $rm->getManageableGroupTypes(true,'CREATE'),
                null,
                false
            )
        );

        if($this->getRequest()->isMethod('POST'))
        {
            $form->bind($this->getRequest());
            if($form->isValid())
            {
                $datas = $form->getData();
                //2 possibilités : juste ajout groupe ou avec Rôle
                if(isset($datas['group_id']))
                {
                    $group = GroupQuery::create()->findPk($datas['group_id']);
                    if($datas['group_type_role_id'] != null)
                    {
                        //Vérification des droits
                        $ok = false;
                        foreach($rm->getManageableGroupTypes(true) as $mRole)
                        {
                            if($mRole->getId() == $datas['group_type_role_id'])
                            {
                                foreach($rm->getManageableGroupTypes(false) as $mGroupType)
                                {
                                    if($mGroupType->getId() == $group->getGroupTypeId())
                                    {
                                        $ok = true;
                                    }
                                }
                            }
                        }
                        if($ok)
                        {
                            $this->get('bns.role_manager')->setGroupTypeRoleFromId($datas['group_type_role_id'])->assignRole($user, $datas['group_id']);
                        } else {
                            $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_ADD_USER_TO_GROUP_ERROR', array(), 'GROUP'));
                        }
                    }
                }
                return $this->redirectSheet($user);
            }
        }

        // can the user assist a teacher / director
        $assistanceEnable = false;
        if (!$session->has('bns.assistance_for')
            && in_array($mainRole, array('teacher', 'director'))
            && $groupManager->getProjectInfoCurrentFirst('has_assistance')
        ) {
            if ($user->getProfile()->getAssistanceEnabled()) {
                $assistanceEnable = true;
            }
        }


        return array(
            'user' => $user,
            'form' => $form->createView(),
            'groupManager' => $this->get('bns.group_manager'),
            'groupsRoles' => $groupRoles,
            'canEdit' => $this->canManageUser(
                $user,
                false,
                'EDIT'
            ),
            'canViewAs' => $this->canManageUser(
                $user,
                false,
                'VIEW_AS'
            ),
            'canDelete' => $this->canManageUser(
                $user,
                false,
                'DELETE'
            ),
            'canView' => $this->canManageUser(
                $user,
                false,
                'VIEW'
            ),
            'canAssist' => $this->canManageUser(
                $user,
                false,
                'ASSIST'
            ) && $assistanceEnable
        );

	}

    /**
     * @Route("/activer/{login}", name="BNSAppGroupBundle_back_user_enable")
     * @Rights("GROUP_ACCESS_BACK")
    */
    public function enableAction($login)
    {
        $user = $this->getAskedUser($login,'EDIT');
		$this->get('bns.user_manager')->enableUser(
            $user,
            true
        );
		return $this->redirectSheet($user);
    }

    /**
     * @Route("/desactiver/{login}", name="BNSAppGroupBundle_back_user_desactivate")
     * @Rights("GROUP_ACCESS_BACK")
    */
    public function desactivateAction($login)
    {
        $user = $this->getAskedUser($login,'EDIT');
		$this->get('bns.user_manager')->disableUser(
            $user,
            false
        );
		return $this->redirectSheet($user);
    }

    /**
	 * @Route("/supprimer/{login}", name="BNSAppGroupBundle_back_user_delete")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function userDeleteAction($login)
	{
        $user = $this->getAskedUser($login,'DELETE');
        $this->get('bns.user_manager')->deleteUser($user);
        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('FLASH_USER_DELETE_SUCCESS', array('%name%' => $user->getFullName() ), 'GROUP')
        );
        return $this->redirectSheet($user);
	}

    /**
     * @Route("/restaurer/{login}", name="BNSAppGroupBundle_back_user_restore")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function userRestoreAction($login)
    {
        $user = $this->getAskedUser($login,'DELETE');
        $this->get('bns.user_manager')->restoreUser($user, true);
        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('FLASH_USER_RESTORE_SUCCESS', array('%name%' => $user->getFullName() ), 'GROUP')
        );
        return $this->redirectSheet($user);
    }




    /**
     * Page d'édition d'un utilisateur
     * @Route("/edition/{login}", name="BNSAppGroupBundle_back_user_edit_sheet")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function sheetEditAction($login)
    {
		$user = $this->getAskedUser($login,'EDIT');
        $um = $this->get('bns.user_manager');
        $form = $this->createForm(
            new UserType(true),
            $user
        );
        $user->setOldLogin($login);
        $request = $this->getRequest();
        if ($request->isMethod('POST')){
            $form->bind($request);
            if($form->isValid())
            {
                $um->updateUser($user,$login);
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $this->get('translator')->trans('FLASH_USER_UPDATE_SUCCESS', array('%name%' => $user->getFullName() ), 'GROUP')
				);
				return $this->redirectSheet($user);
            }else{
                $this->get('session')->getFlashBag()->add(
                    'error',
                    $this->get('translator')->trans('FLASH_FILL_FIELD', array('%name%' => $user->getFullName() ), 'GROUP')
                );
            }
        }
        return array(
            'form' => $form->createView(),
            'user' => $user,
        );
    }

    /**
     * Récupération des droits de l'utilisateurs
     * @Route("/droits-utilisateurs/{login}", name="BNSAppGroupBundle_back_user_get_rights")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function getUserRightsAction($login)
    {
        $user = $this->getAskedUser($login,'VIEW_AS');
        $rm = $this->get('bns.right_manager');
        $rm->getUserRights($user);
        return $this->redirectSheet($user);
    }

    /**
     * Assist a teacher or a director
     * @Route("/assistance-utilisateur/{login}", name="BNSAppGroupBundle_back_user_assistance")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function getAssistanceAction($login)
    {
        /** @var User $user */
        $user = $this->getAskedUser($login, 'VIEW');
        $session = $this->get('session');

        if ($session->has('bns.assistance_for_user_id')) {
            return $this->redirectSheet($user);
        }

        $userManager = $this->get('bns.user_manager')->setUser($user);
        $rightManager = $this->get('bns.right_manager');
        $mainRole = $userManager->getMainRole();
        $groupManager = $rightManager->getCurrentGroupManager();

        // can the user assist a teacher / director
        if (in_array($mainRole, array('teacher', 'director')) && $groupManager->getProjectInfoCurrentFirst('has_assistance')) {
            if ($user->getProfile()->getAssistanceEnabled() && $this->getAskedUser($login, 'ASSIST')) {
                $rightManager->getUserRights($user);
                $session->set('bns.assistance_for', $user->getFullName());
                $session->set('bns.assistance_for_user_id', $user->getId());
                $session->set('bns.assistance_from_group_id', $groupManager->getId());
                $session->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_RETRIEVE_RIGHT_FOR_HELP', array('%fullname%' => $user->getFullName()),'GROUP'));

                // Notify the user
                $this->get('notification_manager')->send(array($user), new ProfileAssistanceStartedNotification($this->container, $this->getUser()->getId()));
            }
        }

        return $this->redirectSheet($user);
    }

    /**
     * @Route("/assistance-utilisateur-fin", name="BNSAppGroupBundle_back_user_assistance_quit")
     */
    public function getAssistanceQuitAction()
    {
        $rightManager = $this->get('bns.right_manager');
        $session = $this->get('session');
        $groupId = $session->get('bns.assistance_from_group_id');

        // Security
        $rightManager->forbidIfHasNotRight('GROUP_ACCESS_BACK', $groupId);

        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());
        $userManager->resetRights();

        $group = GroupQuery::create()->findPk($groupId);
        $user = UserQuery::create()->findPk($session->get('bns.assistance_for_user_id'));

        $session->remove('bns.assistance_from_group_id');
        $session->remove('bns.assistance_for_user_id');
        $session->remove('bns.assistance_for');

        if ($group) {
            $rightManager->switchContext($group);
            if ($user = $this->getAskedUser($user->getLogin(), 'VIEW', false)) {
                return $this->redirectSheet($user);
            }

            return $this->redirect($rightManager->getRedirectRouteOfCurrentGroup(true));
        }

        return $this->redirect('home');
    }



	/**
	 * Régénération du mot de passe
     * @Route("/nouveau-mot-de-passe/{login}", name="BNSAppGroupBundle_back_user_refresh_password")
     * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
     */
	public function refreshPasswordAction($login)
	{
        $user = $this->getAskedUser($login,'EDIT');
        $um = $this->get('bns.user_manager');
        $um->setUser($user);
        $um->resetUserPassword($user);
        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('FLASH_RETRIEVE_RIGHT_FOR_HELP', array('%password%' => $user->getPassword()),'GROUP')
        );
		return $this->redirectSheet($user);
	}

    /**
     * Génération d'une fiche de connexion
     * @Route("/nouvelle-fiche/{login}", name="BNSAppGroupBundle_back_user_refresh_sheet")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function refreshSheetAction($login)
    {
        $user = $this->getAskedUser($login,'EDIT');
        $um = $this->get('bns.user_manager');
        $um->setUser($user);
        $um->resetUserPassword($user,false);

        if ($this->container->hasParameter('application_public_base_url')) {
            $url = $this->container->getParameter('application_public_base_url');
        } else {
            $url = $this->container->getParameter('application_base_url');
        }

        $html = $this->renderView('BNSAppClassroomBundle:UserPDFTemplateCard:fiche_user.html.twig', array(
            'user'  => $user,
            'role'	=> $this->get('bns.role_manager')->getGroupTypeRoleFromId($user->getHighRoleId()),
            'base_url' => $url, //On passe le domaine courant qui s'affichera dans la fiche
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="Fiche - '.$user->getFullName().'.pdf"'
            )
        );
    }

    /**
     * Supprime un lien entre un utilisateur et un groupe
     * @Route("/supprimer-lien/{login}/{roleType}/{groupId}", name="BNSAppGroupBundle_user_link_delete")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function linkDeleteAction($login, $roleType = false, $groupId)
    {
        $user = $this->getAskedUser($login,'EDIT');
        $gm = $this->get('bns.group_manager');
        $um = $this->get('bns.user_manager');
        if($roleType)
        {
            $rom = $this->get('bns.role_manager');
            $role = $rom->findGroupTypeRoleByType($roleType);
        }
        $gm->findGroupById($groupId);

        $this->get('bns.right_manager')->forbidIf($gm->getGroup()->getGroupType()->getType() == 'ENVIRONMENT');

        $gm->removeUser($user,isset($role) ? $role->getType() : null);

        return $this->redirectSheet($user);
    }

    /**
	 * @Route("/utilisateur/recherche", name="BNSAppGroupBundle_back_user_search")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function searchAction()
	{
        $users = null;
		$results = array();
		//Création rapide d'un formulaire de recherche
		$form = $this->createFormBuilder()
			->add('id','text',array('required' => false))
			->add('username','text',array('required' => false))
			->add('first_name','text',array('required' => false))
			->add('last_name','text',array('required' => false))
			->add('email','text',array('required' => false))
			->add('with_archived', 'checkbox',array('required' => false))
			->getForm();

		if ($this->getRequest()->isMethod('POST')){

			$form->bind($this->getRequest());
			//Si la donnée n'est pas settée, on la sort du filtre
			$datas = $form->getData();
			foreach($datas as $key => $data){
				if($data == "")
					unset($datas[$key]);
			}
			//On filtre la recherche dans les groupes accessibles
            //TODO : A optimiser
			//$NoneFilteredGroups = $this->get('bns.right_manager')->getManageableGroupIds('VIEW');
            $toFilterGroupTypeIds = array();
            $viewableGroupTypes = array();
            foreach($this->get('bns.right_manager')->getManageableGroupTypes(true,'VIEW') as $mgt)
            {
                $viewableGroupTypes[] = $mgt->getType();
            }
            if(count($viewableGroupTypes) != 0)
            {
                $datas['group_types'] = $viewableGroupTypes;
                $datas['current_group_id'] = $this->get('bns.right_manager')->getCurrentGroupId();
                //On lance la recherche
                $results = $this->get('bns.user_manager')->searchUserInAuth($datas,true);
            }

		}
		return array('form' => $form->createView(),'users' => $results);
    }


    /**
	 * @Route("/utilisateur/ajouter-recherche", name="BNSAppGroupBundle_user_addable_list" , options={"expose"=true})
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
    public function addableListAction()
    {
        $term = $this->getRequest()->get('term');
        $datas['last_name'] = trim($term);
        $datas['current_group_id'] = $this->get('bns.right_manager')->getCurrentEnvironment()->getId();
        $datas['group_types'] = array('PUPIL','DIRECTOR','TEACHER','EXTERNAL');

        $results = array();

        $users = $this->get('bns.user_manager')->searchUserInAuth($datas,true);

        foreach($users as $searchedUser)
        {
            $um = $this->get('bns.user_manager')->setUser($searchedUser);
            $uai = "";
            foreach($um->getGroupsUserBelong() as $group)
            {
                if($group->hasAttribute('UAI'))
                {
                    $uai = ' - ' . $group->getLabel() . ' - ' . $group->getAttribute('UAI');
                }
            }

            $results[] = array(
                'value' => $searchedUser->getFullName() . $uai,
                'label' => $searchedUser->getFullName() . $uai,
                'id' => $searchedUser->getLogin()
            );
        }

        return new Response(json_encode($results));
    }


}

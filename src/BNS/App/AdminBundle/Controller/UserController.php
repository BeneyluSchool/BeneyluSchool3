<?php

namespace BNS\App\AdminBundle\Controller;


//User
use BNS\App\CoreBundle\Model\User,
	BNS\App\CoreBundle\Model\UserQuery,
	BNS\App\CoreBundle\Form\Type\UserType,
	BNS\App\AdminBundle\Form\Type\AddToGroupType;
//SF
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\Rights;


/**
 * @Route("/utilisateurs")
 */

class UserController extends Controller
{
	/**
	 * @Route("", name="BNSAppAdminBundle_user")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		$users = null;
		
		//Création rapide d'un formulaire de recherche
		$form = $this->createFormBuilder()
			->add('id','text',array('required' => false))
			->add('login','text',array('required' => false))
			->add('firstName','text',array('required' => false))
			->add('lastName','text',array('required' => false))
			->add('email','text',array('required' => false))
			->getForm();		
			
		if ($this->getRequest()->isMethod('POST')){
			
			$form->bindRequest($this->getRequest());
			//Si la donnée n'est pas settée, on la sort du filtre
			$datas = $form->getData();
			foreach($datas as $key => $data){
				if($data == "")
					unset($datas[$key]);
			}
			//On lance la recherche
			$users = UserQuery::create()->setLimit(50)->findByArray($datas);
		}
		return array('form' => $form->createView(),'users' => $users);
    }

    /**
     * 
     * Fiche utilisateur
     * @param User $user 
     * @Route("/fiche/index/{id}", name="BNSAppAdminBundle_user_sheet")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetIndexAction($id)
    {
		$userManager = $this->get('bns.user_manager'); 
		$user = $userManager->findUserById($id);
		$userManager->setUser($user);
		
		$form = $this->createForm(new AddToGroupType());
		
		if ($this->getRequest()->isMethod('POST')){
			$form->bindRequest($this->getRequest());
			if($form->isValid()){
				$datas = $form->getData();
				//2 possibilités : juste ajout groupe ou avec Rôle
				if(isset($datas['group_id'])){
					$this->get('bns.group_manager')->setGroupById($datas['group_id'])->addUser($user);
					if($datas['group_type_role_id'] != null){
						$this->get('bns.role_manager')->setGroupTypeRoleFromId($datas['group_type_role_id'])->assignRole($user, $datas['group_id']);
					}
				}
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_user_sheet',array('id' => $id)));
			}
		}
    	return array('userManager' => $userManager,'form' => $form->createView());
    }
	
	/**
     * 
     * Page d'édition / création d'un utilisateur
     * @param User $user 
     * @Route("/fiche/edition/{id}", name="BNSAppAdminBundle_user_sheet_edit", defaults={"id" = "creation" })
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetEditAction($id)
    {
    	if($id != 'creation')
			$user = UserQuery::create()->findPk($id);
		else{
			$user = new User();
			$user->setLang('fr');
			//Setage d'un login temporaire et inutile pour valider le formulaire
			$user->setLogin('temporary');
		}
        $form = $this->createForm(new UserType(), $user);
		$request = $this->getRequest();

        if ($request->isMethod('POST')){
            $form->bindRequest($request);
            if ($form->isValid()){
				if($id != 'creation'){
					$user->save();
				}else{
					//Création
					$um = $this->get('bns.user_manager');
					$params['first_name'] = $user->getFirstName();
					$params['last_name'] = $user->getLastName();
					$params['lang'] = $user->getLang();
					$params['birthday'] = $user->getBirthday();
					$params['email'] = $user->getEmail();
					$user = $um->createUser($params,true);
				}
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_user_sheet',array('id' => $user->getId())));
            }
        }
        return array(
            'form' => $form->createView(),
			'user' => $user,
			'type' => $user->isNew() ? "creation" : "edition"
        );
    }
	
	/**
	 * Fiche des droits de l'utilisateur
     * @Route("/fiche/droits/{id}", name="BNSAppAdminBundle_user_sheet_rights")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetRightsAction($id)
    {
		$user = UserQuery::create()->findOneById($id);
		$um = $this->get('bns.user_manager');
		$um->setUser($user);
		return array('um' => $um,'user' => $user);
	}
	
	/**
	 * Récupération des droits de l'utilisateurs
     * @Route("/fiche/droits-utilisateurs/{id}", name="BNSAppAdminBundle_user_get_rights")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
	public function getRightsAction($id)
	{
		$user = UserQuery::create()->findOneById($id);
		$rm = $this->get('bns.right_manager');
		$rm->getUserRights($user);
		return $this->redirect($this->generateUrl('BNSAppAdminBundle_user_sheet_rights',array('id' => $id)));
	}
	
	/**
	 * Vidage de cache
     * @Route("/fiche/reset-droits-utilisateurs/{id}", name="BNSAppAdminBundle_reset_user_rights")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
	public function resetRightsAction($id)
	{
		$user = UserQuery::create()->findOneById($id);
		$um = $this->get('bns.user_manager');
		$um->resetRights();
		return $this->redirect($this->generateUrl('BNSAppAdminBundle_user_sheet_rights',array('id' => $id)));
	}
}
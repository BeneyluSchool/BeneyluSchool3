<?php

namespace BNS\App\AdminBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\Permission;
use BNS\App\CoreBundle\Form\type\PermissionType;
use BNS\App\CoreBundle\Model\PermissionI18n;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @Route("/permission")
 */

class PermissionController extends Controller
{
	/**
	 * Page d'accueil des permissions : on les liste
	 * 
	 * @Route("/", name="BNSAppAdminBundle_permission")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return array("permissions" => PermissionQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->joinWith('Module')->joinWith('Module.ModuleI18n')->find());
    }
	
	/**
	 * Fiche permission
	 * 
	 * @Route("/fiche/{uniqueName}", name="BNSAppAdminBundle_permission_sheet")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function sheetAction($uniqueName)
    {
		return array("permission" => PermissionQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->joinWith('Module')->joinWith('Module.ModuleI18n')->findOneByUniqueName($uniqueName));
    }
	
	/**
	 * Formulaire d'action sur les permissions
	 * 
	 * @Route("/gestion/{uniqueName}", name="BNSAppAdminBundle_permission_manage" , defaults={"uniqueName" = "creation" })
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function manageAction($uniqueName)
    {
		if($uniqueName == "creation"){
			$permission = new Permission();
			$permissionI18n = new PermissionI18n();
			$permissionI18n->setLang('fr');
			$permission->addPermissionI18n($permissionI18n);
		}else{
			$permission = PermissionQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		}
		
		$form = $this->createForm(new PermissionType(),$permission);
		
		if ($this->getRequest()->isMethod('POST')){
			$form->bindRequest($this->getRequest());
			if ($form->isValid()){
				//Si pas nouveau : update en local des données seulement
				if(!$permission->isNew())
					$permission->save();
				else{
					//Sinon création : check central et création des données en local
					$data = $form->getNormData();
					//formatage des données
					$params = array(
						'unique_name' => $data->getUniqueName(),
						'i18n' => array('fr' => array('label' => $data->getLabel('fr'))),
						'module_id' => $data->getModuleId()
					);
					$this->get('bns.module_manager')->createPermission($params);
				}
				return $this->redirect($this->generateUrl("BNSAppAdminBundle_permission_sheet",array('uniqueName' => $permission->getUniqueName())));
			}
		}		
		return array('form' => $form->createView(),'permission' => $permission);
	}
	/** DESACTIVE POUR L'INSTANT
	 * @Route("/supprimer/{uniqueName}", name="BNSAppAdminBundle_permission_delete")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
	/*
    public function deleteAction($uniqueName)
    {
		$permission = PermissionQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		if($permission)
			$permission->delete();
		return $this->redirect($this->generateUrl("BNSAppAdminBundle_permission"));
	}*/
}
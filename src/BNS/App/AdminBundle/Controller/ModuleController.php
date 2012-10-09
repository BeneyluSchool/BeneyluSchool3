<?php

namespace BNS\App\AdminBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModuleI18n;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Form\Type\ModuleType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @Route("/module")
 */

class ModuleController extends Controller
{
	/**
	 * Page d'accueil des modules : on les liste
	 * 
	 * @Route("/", name="BNSAppAdminBundle_module")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return array("modules" => ModuleQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->find());
    }
	
	/**
	 * Fiche module
	 * 
	 * @Route("/fiche/{uniqueName}", name="BNSAppAdminBundle_module_sheet")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 * @Rights("ADMIN_ACCESS")
	 */
    public function sheetAction($uniqueName)
    {
		return array("module" => ModuleQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName));
    }
	
	/**
	 * Forumulaire d'édition du module
	 * 
	 * @Route("/gestion/{uniqueName}", name="BNSAppAdminBundle_module_manage" , defaults={"uniqueName" = "creation" })
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function manageAction($uniqueName)
    {
		if($uniqueName == "creation"){
			$module = new Module();
			$moduleI18n = new ModuleI18n();
			$moduleI18n->setLang('fr');
			$module->addModuleI18n($moduleI18n);
		}else{
			$module = ModuleQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		}
		$form = $this->createForm(new ModuleType(),$module);
		if ($this->getRequest()->isMethod('POST')){
			$form->bindRequest($this->getRequest());
			if ($form->isValid()){
				//Si pas nouveau : update en local des données seulement
				if(!$module->isNew())
					$module->save();
				else{
					//Sinon création : check central et création des données en local
					$data = $form->getNormData();
					//formatage des données
					$params = array(
						'unique_name' => $data->getUniqueName(),
						'i18n' => array('fr' => array('label' => $data->getLabel('fr'))),
						'is_contextable' => $data->getIsContextable(),
						'bundle_name' => $data->getBundleName()
					);
					$this->get('bns.module_manager')->createModule($params);
				}
				return $this->redirect($this->generateUrl("BNSAppAdminBundle_module_sheet",array('uniqueName' => $module->getUniqueName())));
			}
		}		
		return array('form' => $form->createView(),'module' => $module);
	}
	/**NON ACTIVE POUR L'INSTANT
	 * @Route("/supprimer/{uniqueName}", name="BNSAppAdminBundle_module_delete")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
	/*
    public function deleteAction($uniqueName)
    {
		$module = ModuleQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		if($module)
			$module->delete();
		return $this->redirect($this->generateUrl("BNSAppAdminBundle_module"));
	}
	*/
}
<?php

namespace BNS\App\AdminBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Rank;
use BNS\App\CoreBundle\Form\type\RankType;
use BNS\App\CoreBundle\Model\RankI18n;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Model\PermissionQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @Route("/rang")
 */

class RankController extends Controller
{
	/**
	 * Page d'accueil des rangs
	 * @Route("/", name="BNSAppAdminBundle_rank")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		$locale = $this->get('bns.right_manager')->getLocale();
		return array("ranks" => RankQuery::create()->joinWithI18n($locale)
				->joinWith('Module')
				->joinWith('Module.ModuleI18n')
			->find()
		);
    }
	
	/**
	 * Fiche rang
	 * 
	 * @Route("/fiche/{uniqueName}", name="BNSAppAdminBundle_rank_sheet")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function sheetAction($uniqueName)
    {
		$locale = $this->get('bns.right_manager')->getLocale();
		$usedPermissions = $this->get('bns.module_manager')->getRankPermissions($uniqueName);
		$rank = RankQuery::create()->joinWithI18n($locale)
				->joinWith('Module')
				->joinWith('Module.ModuleI18n')
			->findOneByUniqueName($uniqueName);
		$rankPermissions = PermissionQuery::create()->joinWithI18n($locale)->findByModuleId($rank->getModuleId());
		return array(
			"rank" => $rank,
			"usedPermissions" => $usedPermissions,
			"rankPermissions" => $rankPermissions
		);
    }
	
	/**
	 * Formulaire d'action sur les rangs
	 * 
	 * @Route("/gestion/{uniqueName}", name="BNSAppAdminBundle_rank_manage" , defaults={"uniqueName" = "creation" })
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function manageAction($uniqueName)
    {
		if($uniqueName == "creation"){
			$rank = new Rank();
			$rankI18n = new RankI18n();
			$rankI18n->setLang('fr');
			$rank->addRankI18n($rankI18n);
		}else{
			$rank = RankQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		}
		
		$form = $this->createForm(new RankType(),$rank);
		
		if ($this->getRequest()->getMethod() == 'POST'){
			$form->bindRequest($this->getRequest());
			if ($form->isValid()){
				//Si pas nouveau : update en local des données seulement
				if(!$rank->isNew())
					$rank->save();
				else{
					//Sinon création : check central et création des données en local
					$data = $form->getNormData();
					//formatage des données
					$params = array(
						'unique_name' => $data->getUniqueName(),
						'i18n' => array('fr' => array('label' => $data->getLabel('fr'))),
						'module_id' => $data->getModuleId()
					);
					$this->get('bns.module_manager')->createRank($params);
				}
				return $this->redirect($this->generateUrl("BNSAppAdminBundle_rank_sheet",array('uniqueName' => $rank->getUniqueName())));
			}
		}		
		return array('form' => $form->createView(),'rank' => $rank);
	}
	/** DESACTIVE POUR L'INSTANT
	 * Suppression d'un rang
	 * 
	 * @Route("/supprimer/{uniqueName}", name="BNSAppAdminBundle_rank_delete")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
	/*
    public function deleteAction($uniqueName)
    {
		$rank = RankQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
		if($rank)
			$rank->delete();
		return $this->redirect($this->generateUrl("BNSAppAdminBundle_rank"));
	}*/
	
	/**
	 * Suppression d'une permission pour un rang donné
	 * 
	 * @Route("/supprimer/{rankUniqueName}/{permissionUniqueName}", name="BNSAppAdminBundle_rank_delete_permission")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function deletePermissionAction($rankUniqueName,$permissionUniqueName)
    {
		$this->get('bns.module_manager')->deleteRankPermission($rankUniqueName,$permissionUniqueName);
		return $this->redirect($this->generateUrl("BNSAppAdminBundle_rank_sheet",array('uniqueName' => $rankUniqueName)));
	}
	
	/**
	 * Ajout d'une permission à un rang
	 * 
	 * @Route("/ajouter/{rankUniqueName}", name="BNSAppAdminBundle_rank_add_permission")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function addPermissionAction($rankUniqueName)
    {
		$permissionUniqueName = $this->getRequest()->get("permissionUniqueName");
		$this->get('bns.module_manager')->addRankPermission($rankUniqueName,$permissionUniqueName);
		return $this->redirect($this->generateUrl("BNSAppAdminBundle_rank_sheet",array('uniqueName' => $rankUniqueName)));
	}
}
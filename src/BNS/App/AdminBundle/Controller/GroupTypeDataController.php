<?php

namespace BNS\App\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataChoice;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceI18n;
use BNS\App\CoreBundle\Form\Type\GroupTypeDataTemplateType;
use BNS\App\CoreBundle\Form\Type\GroupTypeDataChoiceType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @Route("/groupes-donnees")
 */

class GroupTypeDataController extends Controller
{
	/**
	 * Page d'accueil de la gestion des groupes;
	 * tous les groupes sont listés dans un tableau 
	 * 
	 * @Route("/", name="BNSAppAdminBundle_group_type_data")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		$groupTypeDatas = GroupTypeDataTemplateQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->find();
		return array('groupTypeDatas' => $groupTypeDatas);
    }
	
	/**
	 * Fiche "données" : edition si SINGLE ou TEXT, gestion de liste si CHOICE
	 * @Route("/fiche/{unique_name}", name="BNSAppAdminBundle_group_type_data_sheet")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function sheetAction($unique_name)
    {
		$groupTypeData = GroupTypeDataTemplateQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($unique_name);
		$choices = $groupTypeData->isChoiceable() ? GroupTypeDataChoiceQuery::create()->filterByGroupTypeDataTemplateUniqueName($groupTypeData->getUniqueName())->joinWithI18n($this->get('bns.right_manager')->getLocale())->find() : null;
		$getUsedGroupTypeDatas = $groupTypeData->getGroupTypeDatas();
		return array(
			'groupTypeData' => $groupTypeData,
			'choices' => $choices,
			'getUsedGroupTypeDatas' => $getUsedGroupTypeDatas
		);
    }
	
	/**
	 * 
	 * @Route("/edition/{unique_name}", name="BNSAppAdminBundle_group_type_data_edit")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function editAction($unique_name)
    {
		
		if($unique_name == "creation"){
			$groupTypeDataChoice = new GroupTypeDataChoice();
			$i18n = new GroupTypeDataChoiceI18n();
			$i18n->setLang('fr');
			$groupTypeDataChoice->addGroupTypeDataChoiceI18n($i18n);
			$groupTypeDataChoice->setGroupTypeDataTemplateUniqueName($template_unique_name);
			$groupTypeDataTemplate = GroupTypeDataTemplateQuery::create()->findOneByUniqueName($template_unique_name);
		}else{
			$groupTypeData = GroupTypeDataTemplateQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($unique_name);
		}
		
		$request = $this->getRequest();
		$form = $this->createForm(new GroupTypeDataTemplateType(),$groupTypeData);
		
		if ($request->getMethod() == 'POST') {
			$form->bindRequest($request);
	        if ($form->isValid()) {
				$groupTypeData->save();
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_group_type_data_sheet',array('unique_name' => $groupTypeData->getUniqueName())));
			}
		}
		
		return array(
			'groupTypeData' => $groupTypeData,
			'form' => $form->createView()
		);
    }
	
	/**
	 * Fiche "données" : edition si SINGLE ou TEXT, gestion de liste si CHOICE
	 * @Route("/choix/edition/{template_unique_name}/{id}", name="BNSAppAdminBundle_group_type_data_choice_edit", defaults={"id" = "creation" }))
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function choiceEditAction($template_unique_name,$id)
    {
		if($id == "creation"){
			$groupTypeDataChoice = new GroupTypeDataChoice();
			$i18n = new GroupTypeDataChoiceI18n();
			$i18n->setLang('fr');
			$groupTypeDataChoice->addGroupTypeDataChoiceI18n($i18n);
			$groupTypeDataChoice->setGroupTypeDataTemplateUniqueName($template_unique_name);
			$groupTypeDataTemplate = GroupTypeDataTemplateQuery::create()->findOneByUniqueName($template_unique_name);
		}else{
			$groupTypeDataChoice = GroupTypeDataChoiceQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneById($id);
			$groupTypeDataTemplate = $groupTypeDataChoice->getGroupTypeDataTemplate();
		}
		
		$request = $this->getRequest();
		$form = $this->createForm(new GroupTypeDataChoiceType(),$groupTypeDataChoice);
		
		if ($request->getMethod() == 'POST') {
			$form->bindRequest($request);
	        if ($form->isValid()) {
				$groupTypeDataChoice->save();
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_group_type_data_sheet',array('unique_name' => $groupTypeDataChoice->getGroupTypeDataTemplate()->getUniqueName())));
			}
		}
		
		return array(
			'groupTypeDataChoice' => $groupTypeDataChoice,
			'groupTypeDataTemplate' => $groupTypeDataTemplate,
			'form' => $form->createView(),
			'template_unique_name' => $template_unique_name
		);
    }
	
}
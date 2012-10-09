<?php

namespace BNS\App\ResourceBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException,
	Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Template,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter,
	Symfony\Component\HttpFoundation\Response;

use BNS\App\ResourceBundle\Model\ResourceLabelGroup,
	BNS\App\ResourceBundle\Model\ResourceLabelUser,
	BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery,
	BNS\App\ResourceBundle\Model\ResourceLabelUserQuery,
	BNS\App\CoreBundle\Model\GroupQuery,
	BNS\App\CoreBundle\Annotation\RightsSomeWhere,
	BNS\App\CoreBundle\Annotation\Rights;


/**
 * Gestion des libellés en front et back
 * @Route("/libelles")
 */

class LabelController extends CommonController
{
	/**
	 * Ajout d'un libellé
	 * @Route("/ajouter", name="BNSAppResourceBundle_label_add")
	 * @Template()
	 * @Rights("RESOURCE_ACCESS_BACK")
	 */
	public function addAction()
	{
		return array();	
	}
	
	/**
	 * Submit de l'ajout d'un libellé en front (dans la sidebar)
	 * @Route("/ajouter/front", name="BNSAppResourceBundle_label_add_front_submit", options={"expose"=true}))
	 * @Rights("RESOURCE_ACCESS_BACK")
	 */
	public function addFrontSubmitAction()
	{
		$rm = $this->get('bns.right_manager');
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceRightManager->setUser($rm->getModelUser());
		$currentLabel = $this->getCurrentLabelFromSession();
		$rm->forbidIf(!$resourceRightManager->canCreateLabel($currentLabel));
		$value = $this->getRequest()->get('value');
		if(trim($value) != ""){
			
			switch($currentLabel->getType()){
				case 'group':
					$label = new ResourceLabelGroup();
					$label->setGroupId($currentLabel->getGroupId());
				break;
				case 'user':
					$label = new ResourceLabelUser();
					$label->setUserId($currentLabel->getUserId());
				break;
			}
			$label->setLabel($value);
			$label->insertAsLastChildOf($currentLabel);
			$label->save();
			
			return new Response();	
		}else{
			return new Response();
		}
	}
	
	/**
    * 
    * @Route("/choisir", name="BNSAppResourceBundle_label_choose")
	* @Template()
    */
	public function chooseAction($show_destination = true)
	{
		//Groupes dans lesquels je peux editer l'arborescence
		$manageable_groups = $this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ADMINISTRATION');
		$current_label = $this->getCurrentLabelFromSession();
		return array(
			"manageable_groups" => $manageable_groups,
			"current_label" => $current_label,
			'show_destination' => $show_destination
		);
	}
	
	/**
    * Sauvegarde d'un libellé
    * @Route("/sauvegarder", name="BNSAppResourceBundle_label_save", options={"expose"=true})
	* @Template("BNSAppResourceBundle:Label:listEdit.html.twig")
    */
	public function saveAction()
	{
		$request = $this->getRequest();
		//parent_id sous la forme unique_id_id
		$parent_id = $request->get('parent_id');
		$rm = $this->get('bns.right_manager');

		//distinction groupe / user
		$user = $rm->getModelUser(); 
		$parent_values = explode('_',$parent_id);
		$type = $parent_values[0];
		$object_id = $parent_values[1];
		$parent_id = $parent_values[2];
		
		$label = $request->get('label');
		if(trim($label) == ""){
			$this->get('session')->getFlashBag()->add('notice_warning', 'Veuillez saisir un libellé');
		}else{
			
			
			$resourceRightManager = $this->get('bns.resource_right_manager');
			$resourceRightManager->setUser($rm->getModelUser());

			if($type == 'user'){
				$parent = ResourceLabelUserQuery::create()->findOneById($parent_id);
				$rm->forbidIf(!$resourceRightManager->canCreateLabel($parent));
				$resource_label = new ResourceLabelUser();
			}elseif($type == 'group'){
				$parent = ResourceLabelGroupQuery::create()->findOneById($parent_id);
				$rm->forbidIf(!$resourceRightManager->canCreateLabel($parent));
				$group = $parent->getGroup();
				$resource_label = new ResourceLabelGroup();	
			}
			$resource_label->insertAsFirstChildOf($parent);
			$resource_label->setLabel($label);
			$resource_label->save();
			$this->get('session')->getFlashBag()->add('notice_success', 'Le libellé a bien été créé');
		}
		
		if($type == 'user'){
			$labels = $user->getRessourceLabels($with_root = false);
			$entity_id = $user->getId();
			$name = "Mon dossier";
		}elseif($type == 'group'){
			$labels = $group->getRessourceLabels($with_root = false);
			$entity_id = $group->getId();
			$name = $group->getLabel();
		}
		return array('labels' => $labels,'unique_id' => $type . '_' . $entity_id,'name' => $name, 'type' => $type);
	}
	
	/**
	* @Route("/liste", name="BNSAppResourceBundle_label_list", options={"expose"=true})
	* @Template()
    */
	public function listAction($type = "user",$user = null,$group=null){
	
		$user = $this->get('bns.right_manager')->getModelUser();
		$this->get('bns.right_manager')->forbidIf(($type == 'user' && $user == null) || ($type == 'group' && $group == null));
		
		//Cas User :
		
		if($type == 'user'){
			$entity_id = $user->getId();
			$labels = $user->getRessourceLabels();
			$for_user = true;
		}elseif($type == 'group'){
			$entity_id = $group->getId();
			$labels = $group->getRessourceLabels();
			$for_user = false;
		}
		return array('labels' => $labels,'unique_id' => $type . '_' . $entity_id,'for_user' => $for_user);
	}
	/**
	* @Route("/liste/editer", name="BNSAppResourceBundle_label_list_edit", options={"expose"=true})
	* @Template()
    */
	public function listEditAction($type = "user",$user = null,$group=null){
	
		$user = $this->get('bns.right_manager')->getModelUser();
		$this->get('bns.right_manager')->forbidIf(($type == 'user' && $user == null) || ($type == 'group' && $group == null));
		
		//Cas User :
		
		if($type == 'user'){
			$entity_id = $user->getId();
			$labels = $user->getRessourceLabels($with_root = false);
			$name = "Mon dossier";
			
		}elseif($type == 'group'){
			$entity_id = $group->getId();
			$labels = $group->getRessourceLabels($with_root = false);
			$name = $group->getLabel();
		}
		return array('labels' => $labels,'unique_id' => $type . '_' . $entity_id,'name' => $name,'type' => $type);
	}
	
	/**
	* @Route("/sauvegarde-arbre", name="BNSAppResourceBundle_label_save_tree", options={"expose"=true})
    */
	public function saveTreeAction()
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			if ($this->getRequest()->get('categories', false) === false || !$this->getRequest()->get('object')) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}
			
			//$unique_id nous permet de savoir sur quoi enregistrer 
			//Type == user ||group
			
			$datas = explode('_',$this->getRequest()->get('object'));
			$type = $datas['0'];
			$user = $this->get('bns.right_manager')->forbidIf(!in_array($type, array('group','user')));
			$id = $datas['1'];
			
			$categories = $this->getRequest()->get('categories');
			if($type == 'user'){
				$user = $this->get('bns.right_manager')->getModelUser();
				$labels = $user->getRessourceLabels(false);
			}else{
				$group = GroupQuery::create()->findOneById($id);
				$labels = $group->getRessourceLabels(false);
			}
			$labelById = array();
			foreach ($labels as $label) {
				$labelById[$label->getId()] = $label;
			}
			
			foreach ($categories as $categorie) {
				//On en bouge pas la root
				if($categorie['item_id'] != 'root'){
					//Vérification que l'on
					var_dump($labelById);
					if(isset($labelById[$categorie['item_id']])){
						$label = $labelById[$categorie['item_id']];
						$label->setTreeLeft($categorie['left']);
						$label->setTreeRight($categorie['right']);
						$label->setLevel($categorie['depth']);
						$label->save();
					}
				}
			}	
			return new Response();
		}
	}
	
	/**
	* Mise à jour du nom d'un libellé
    * @Route("/edition-libelle", name="BNSAppResourceBundle_label_edit", options={"expose"=true})
	* @Template()
    */
	public function editAction()
	{
		$newLabel = $this->getRequest()->get('label');
		$label = $this->getLabelFromRequest($this->getRequest());
		$label->setLabel($newLabel);
		$label->save();
		return new Response($newLabel);
	}
	
	/**
	* Suppression d'un libellé
    * @Route("/suppression-libelle", name="BNSAppResourceBundle_label_delete", options={"expose"=true})
	* @Template()
    */
	public function deleteAction()
	{
		$label = $this->getLabelFromRequest($this->getRequest());
		$label->delete();
		return new Response();
	}	
}

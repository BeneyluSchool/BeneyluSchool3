<?php

namespace BNS\App\ResourceBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Annotation\Rights;


/**
 * Gestion des libellés en front et back
 * @Route("/libelles")
 */

class LabelController extends CommonController
{
	/**
	 * Fonction de protection sur un label
	 */
	protected function secureLabel($label)
	{
		$rrm = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rrm->canManageLabel($label));
	}

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
			$this->setCurrentLabelIntoSession($label);
			
			return $this->forward('BNSAppResourceBundle:FrontNavigation:sidebar',array('new' => true));
		}else{
			return new Response();
		}
	}

	/**
    *
    * @Route("/choisir", name="BNSAppResourceBundle_label_choose")
	* @Template()
    */
	public function chooseAction($show_destination = true,$title = "")
	{
		//Groupes dans lesquels je peux editer l'arborescence
		$manageable_groups = $this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ADMINISTRATION');
		$current_label = $this->getCurrentLabelFromSession();

		$rrm = $this->get('bns.resource_right_manager');

		//On vérifie qu'on a le droit d'écrire dans ce répertoire
		if(!$rrm->canCreateResource($current_label)){
			$current_label = null;
		}

		return array(
			"manageable_groups" => $manageable_groups,
			"current_label" => $current_label,
			'show_destination' => $show_destination,
			"title" => $title
		);
	}

	public function chooseToolbarAction($show_destination = true, $title = "", $isFile = false)
	{
		//Groupes dans lesquels je peux editer l'arborescence
		$manageable_groups = $this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ADMINISTRATION');
		$current_label = $this->getCurrentLabelFromSession();

		$rrm = $this->get('bns.resource_right_manager');

		//On vérifie qu'on a le droit d'écrire dans ce répertoire
		if(!$rrm->canCreateResource($current_label)){
			$current_label = null;
		}

		return $this->render('BNSAppResourceBundle:Label:toolbar_choose.html.twig', array(
			"manageable_groups" => $manageable_groups,
			"current_label" => $current_label,
			'show_destination' => $show_destination,
			"title" => $title,
			'isFile'	=> $isFile
		));
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
	public function listEditAction($type = "user",$user = null,$group=null, $manageable_list_id = null){

		$user = $this->get('bns.right_manager')->getModelUser();
		$this->get('bns.right_manager')->forbidIf(($type == 'user' && $user == null) || ($type == 'group' && $group == null));

		//Cas User :

		if($type == 'user'){
			$entity_id = $user->getId();
			$labels = $user->getResourceLabelByLevel();
			$name = "Mon dossier";

		}elseif($type == 'group'){
			$entity_id = $group->getId();
			$labels = $group->getResourceLabelByLevel();
			$name = $group->getLabel();
		}

		return array('labels' => $labels,'unique_id' => $type . '_' . $entity_id,'name' => $name,'type' => $type, 'manageable_list_id' => $manageable_list_id);
	}

	/**
	* @Route("/sauvegarde-arbre", name="BNSAppResourceBundle_label_save_tree", options={"expose"=true})
    */
	/*
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
	*/
	/**
	* Mise à jour du nom d'un libellé
    * @Route("/edition-libelle", name="BNSAppResourceBundle_label_edit", options={"expose"=true})
	* @Template()
    */
	/*
	public function editAction()
	{
		$newLabel = $this->getRequest()->get('label');
		$label = $this->getLabelFromRequest($this->getRequest());
		$label->setLabel($newLabel);
		$label->save();
		return new Response($newLabel);
	}
	*/
	/**
	* Suppression d'un libellé
    * @Route("/suppression-libelle", name="BNSAppResourceBundle_label_delete", options={"expose"=true})
	* @Template()
    */
	/*
	public function deleteAction()
	{
		$label = $this->getLabelFromRequest($this->getRequest());
		$label->delete();
		return new Response();
	}
	*/
	/**
	* Suppression d'un libellé
	* @Route("/suppression-libelle-avec-id", name="BNSAppResourceBundle_label_delete_by_complexe_id", options={"expose"=true})
	    * @Template()
	*/
	public function deleteByIdAction()
	{
		if($this->getRequest()->isXmlHttpRequest()){
		    $id = $this->getRequest()->get('id');
		    $infos = explode('-', $id);
		    $finalId = $infos[1];
		    $type = $infos[0];
		    $label = null;

		    if($type == 'user'){
				$label = ResourceLabelUserQuery::create()->findOneById($finalId);
		    }elseif ($type == 'group'){
				$label = ResourceLabelGroupQuery::create()->findOneById($finalId);
			}

		    if($label != null){
				$this->secureLabel($label);
				$label->delete();
		    }
		}
		return new Response();
	}

	/**
	* Mise à jour du nom d'un libellé
    * @Route("/edition-libelle-avec-id", name="BNSAppResourceBundle_label_edit_complexe_id", options={"expose"=true})
	* @Template()
    */
	public function editByIdAction()
	{
		if($this->getRequest()->isXmlHttpRequest()){

		    $id = $this->getRequest()->get('id');
		    $title = $this->getRequest()->get('title');
		    $infos = explode('-', $id);
		    $finalId = $infos[1];
		    $type = $infos[0];
		    $label = null;

		    if($type == 'user'){
				$label = ResourceLabelUserQuery::create()->findOneById($finalId);
		    }elseif($type == 'group'){
				$label = ResourceLabelGroupQuery::create()->findOneById($finalId);
		    }

		    if($label != null)
		    {
				$this->secureLabel($label);
				$label->setLabel($title);
				$label->save();
		    }
		}
		return new Response();
	}

    /**
    * Mise à jour de l'ordre
    * @Route("/sauvergarder-arbre-ordonne", name="BNSAppResourceBundle_label_save_sort", options={"expose"=true})
    * @Template()
    */
	public function sortAction()
	{
		if($this->getRequest()->isXmlHttpRequest()){

			$categories = $this->getRequest()->get('categories');

		    $datas = explode('-',$this->getRequest()->get('object'));
		    $type = $datas['0'];
		    $id = $datas['1'];

		    if($type == 'user'){
				$user = $this->get('bns.right_manager')->getModelUser();
				$bddCategories = $user->getResourceLabelUsers();
		    }else{
				$r = ResourceLabelGroupQuery::create()->findOneById($id);
				$group = $r->getGroup();
				$bddCategories = $group->getResourceLabelGroups();
		    }

			$blogCatById = array();

		    $root = null;

			foreach ($bddCategories as $category) {
			    $blogCatById[$category->getId()] = $category;
			    if ($category->isRoot()) {
				    $root = $category;
					$this->secureLabel($root);
			    }
			}

		    if (null == $root) {
			    throw new \RuntimeException('There is not root category');
		    }

		    $dump = array();

		    foreach ($categories as $parentCat) {
			    $pCat = $blogCatById[$parentCat['id']];
			    $pCat->moveToLastChildOf($root);
			    $dump[$parentCat['id']] = null;
			    if (isset($parentCat['children'])){
					$this->loopFolderCategory($parentCat, $blogCatById, $dump, $pCat);
			    }
			    $pCat->save();
		    }
		    return new Response();
		}
		return new Response();
	}

	public function loopFolderCategory($parentCat, $blogCatById, $dump, $pCat)
	{
	    $dump[$parentCat['id']] = null;
	    foreach ($parentCat['children'] as $subCategory){
			$sCat = $blogCatById[$subCategory['id']];
			$sCat->moveToLastChildOf($pCat);
			$sCat->save();
			$dump[$parentCat['id']][$subCategory['id']] = null;
			if (isset($subCategory['children'])){
				$this->loopFolderCategory($subCategory, $blogCatById, $dump, $sCat);
			}
	    }
	}


	/**
	* Sauvegarde d'un libellé
	* @Route("/sauvegarder-ajout", name="BNSAppResourceBundle_label_save_add", options={"expose"=true})
	    * @Template("BNSAppResourceBundle:Label:listEdit.html.twig")
	*/
	public function saveAddAction()
	{
	    if($this->getRequest()->isXmlHttpRequest())
	    {
		$title = $this->getRequest()->get('title');
		$parentId = $this->getRequest()->get('parentId');

		if($parentId == 0)
		{
		    $user = $this->get('bns.right_manager')->getModelUser();
		    $label = new ResourceLabelUser();
		    $label->setLabel($title);
		    $label->insertAsLastChildOf($user->getResourceLabelRoot());
		    $label->save();
		    $type = 'user';
		}
		else
		{
		    $group = GroupQuery::create()->findOneById($parentId);
		    $label = new ResourceLabelGroup();
		    $label->setLabel($title);
		    $label->insertAsLastChildOf($group->getResourceLabelRoot());
		    $label->save();
		    $type = 'group';
		}
		$view = 'BNSAppResourceBundle:Label:listEditRow.html.twig';

		return $this->render($view, array(
			'label' => $label,
			'type'	=> $type
		));

	    }
	}

}

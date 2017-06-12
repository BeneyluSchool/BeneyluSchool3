<?php

namespace BNS\App\ResourceBundle\Controller;

use \BNS\App\ResourceBundle\Model\Resource;
use \BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use \BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use \BNS\App\ResourceBundle\Model\ResourceLinkUser;
use \BNS\App\ResourceBundle\Model\ResourceQuery;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

/**
 * @Route("/selection")
 */
class FrontSelectionController extends CommonController
{
	/**
	 * Show the selection container
	 */
	public function selectionAction($navigationContext)
	{
		// Aucune sélection pour la corbeille
		if ($navigationContext == 'garbage') {
			$resources = array();
		}
		else {
			$resources = $this->getResourcesFromSelection();
		}

		return $this->render('BNSAppResourceBundle:FrontSelection:front_selection_view.html.twig', array(
			'resources'		  => $resources,
			'selectionLength' => count($resources)
		));
	}

	/**
     * Gestion de la selection de resources
	 *
     * @Route("/", name="resource_selection_toggle", options={"expose"=true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function toggleAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('resource_id', false) === false) {
			return $this->redirectHome();
		}

		// Resource is found ?
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		if (null == $resource) {
			return $this->redirectHome();
		}

		$session = $this->get('session');
		$selection = $session->get('resource_selection');
		$currentLabel = $this->getCurrentLabelFromSession();

		// TODO à quoi ça sert ?
		if ($currentLabel) {
			$currentLabelType = $currentLabel->getType();
			$currentLabelId = $currentLabel->getId();
		}
		else {
			$currentLabelType = null;
			$currentLabelId = null;
		}

		if ($selection == null || $this->getActionType() == "select_image") {
			$selection = array();
		}

		// Si pas dedans on ajoute, sinon on enleve
		if (!isset($selection[$resource->getId()])) {
			$selection[$resource->getId()] = array(
				'label'		 => $currentLabelId,
				'label_type' => $currentLabelType
			);
		}
		elseif (isset($selection[$resource->getId()])) {
			unset($selection[$resource->getId()]);
		}

		$session->set('resource_selection', $selection);

		$canManageResource = false;
		$resources = $this->getResourcesFromSelection();
		$selectedResources = $this->get('session')->get('resource_selection');
		
		foreach ($resources as $selectedResource) {
			$label = null;
			
			if ($selectedResources[$selectedResource->getId()]['label_type'] == 'group') {
				$label = ResourceLabelGroupQuery::create('rlag')
					->joinWith('rlag.Group g')
					->where('rlag.Id = ?', $selectedResources[$selectedResource->getId()]['label'])
				->findOne();

				if (null == $label) {
					throw new \RuntimeException('The group label #' . $selectedResources[$selectedResource->getId()]['label'] . ' for selected resource #' . $selectedResource->getId() . ' is NOT found !');
				}
			}
			else {
				if ($selectedResources[$selectedResource->getId()]['label_type'] == 'user') {
					$label = ResourceLabelUserQuery::create('rlau')
						->where('rlau.Id = ?', $selectedResources[$selectedResource->getId()]['label'])
					->findOne();

					if (null == $label) {
						throw new \RuntimeException('The user label #' . $selectedResources[$selectedResource->getId()]['label'] . ' for selected resource #' . $selectedResource->getId() . ' is NOT found !');
					}
				}
			}

			if ($this->get('bns.resource_right_manager')->canManageResourceFromSelection($selectedResource, $this->getUser()->getId(), $label)) {
				$canManageResource = true;

				break;
			}
		}

		return new Response(json_encode(array(
			'canManageResource' => $canManageResource
		)));
	}

	/**
     * Gestion de l'insertion des ressources
	 *
     * @Route("/inserer", name="resource_selection_insert")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function insertAction(Request $request)
	{
		if (!$request->isXmlHttpRequest()) {
			return $this->redirectHome();
		}

		if ($request->get('resource_id', false) !== false) {
			$resource = ResourceQuery::create('r')->findPk($request->get('resource_id'));
			if (null == $resource) {
				return new Response();
			}

			$resources = array($resource);
		}
		else {
			$resources = $this->getResourcesFromSelection();
			$this->clearSelection();
		}

		return $this->render('BNSAppResourceBundle:FrontSelection:resourceSelectionInsert.html.twig', array(
			'resources' => $resources,
			'rm' => $this->get('bns.resource_manager')
		));
	}

	/**
     * Gestion de la jointure des ressources
	 *
     * @Route("/joindre", name="resource_selection_join")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function joinAction(Request $request)
	{
		if (!$request->isXmlHttpRequest()) {
			return $this->redirectHome();
		}

		if ($request->get('resource_id', false) !== false) {
			$resource = ResourceQuery::create('r')->findPk($request->get('resource_id'));
			if (null == $resource) {
				return new Response();
			}

			$resources = array($resource);
		}
		else {
			$resources = $this->getResourcesFromSelection();
			$this->clearSelection();
		}

		return $this->render('BNSAppResourceBundle:FrontSelection:resourceSelectionJoin.html.twig', array(
			'resources' => $resources,
			'editable' => true
		));
	}

	/**
     * Gestion de la jointure des ressources
	 *
     * @Route("/selectionner", name="resource_selection_select")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function selectAction(Request $request)
	{
		if (!$request->isXmlHttpRequest()) {
			return $this->redirectHome();
		}

		if ($request->get('resource_id', false) === false) {
			return $this->redirectHome();
		}

		$resource = ResourceQuery::create('r')->findPk($request->get('resource_id'));
		if (null == $resource) {
			return new Response();
		}

		return $this->render('BNSAppResourceBundle:FrontSelection:resourceSelectionSelect.html.twig', array(
			'rm' => $this->get('bns.resource_manager'),
			'resources' => array($resource),
			'editable' => false
		));
	}

	/**
    * Vidage d'une sélection
	 *
    * @Route("/vider", name="resource_selection_clear")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
    */
	public function emptyAction(Request $request)
	{
		if (!$request->isXmlHttpRequest()) {
			return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
		}

		$this->clearSelection();

		return new Response();
	}

	/**
     * Affichage d'une alerte avant suppression
	 *
     * @Route("/supprimer/confirmation", name="resource_selection_delete_confirm")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function deleteConfirmAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
			return new Response();
		}

		$items = $this->get('session')->get('resource_selection');
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');
		$deletableItems = array();
		$errorItems = array();

		foreach ($items as $resourceId => $item) {
			$resource = $this->getResourceFromSelection($resourceId, $item['label_type'], $item['label'], true);

			// Security
			// Resource not found, continue
			if (null == $resource) {
				continue;
			}

			// Check rights
			if ($item['label_type'] == 'group') {
				$links = $resource->getResourceLinkGroups();
				$label = $links[0]->getResourceLabelGroup();
			}
			else {
				$links = $resource->getResourceLinkUsers();
				$label = $links[0]->getResourceLabelUser();
			}

			$canManage = $resourceRightManager->canManageResource($resource, $label);
			if (!$resourceRightManager->canDeleteResource($resource, $this->getUser()->getId(), $label, $canManage)) {
				$errorItems[] = array(
					'resource' => $resource,
					'message'  => 'RIGHTS',
					'label_id' => $item['label_id']
				);

				continue;
			}

			$hasForeignOccurrence = false;
			if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $item['label'], $canManage)) {
				$hasForeignOccurrence = $resourceManager->hasForeignOccurrence($resource, $this->getUser()->getId(), $canManage);
			}

			// Finally
			$deletableItems[] = array(
				'resource'				 => $resource,
				'label_id'				 => $item['label'],
				'has_foreign_occurrence' => $hasForeignOccurrence
			);
		}

		return $this->render('BNSAppResourceBundle:Modal:selection_delete_body.html.twig', array(
			'errorItems'		 => $errorItems,
			'deletableItems'	 => $deletableItems
		));
	}

	/**
     * Suppression d'une sélection
	 *
     * @Route("/supprimer", name="resource_selection_delete")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function deleteAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
			return new Response();
		}

		$items = $this->get('session')->get('resource_selection');
		$deletedItems = array();
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');

		foreach ($items as $resourceId => $item) {
			$resource = $this->getResourceFromSelection($resourceId, $item['label_type'], $item['label'], true);

			// Security
			// Resource not found, continue
			if (null == $resource) {
				continue;
			}

			// Check
			if ($item['label_type'] == 'group') {
				$links = $resource->getResourceLinkGroups();
				$label = $links[0]->getResourceLabelGroup();
			}
			else {
				$links = $resource->getResourceLinkUsers();
				$label = $links[0]->getResourceLabelUser();
			}

			if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label)) {
				continue;
			}

			// Finally
			$canManage = $resourceRightManager->canManageResource($resource, $label);
			$isLastOccurrence = $resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $item['label'], $canManage);
			$resourceManager->delete($resource, $this->getUser()->getId(), $item['label_type'], $item['label'], $isLastOccurrence);

			// Feedback
			$deletedItems[] = '#item-' . $resourceId;
		}

		$this->clearSelection();

		return new Response(json_encode($deletedItems));
	}

	/**
     * Deplacement d'une sélection
	 *
     * @Route("/deplacer", name="resource_selection_move")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function moveAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('target_label', false) === false) {
			return $this->redirectHome();
		}

		$target = $this->getLabelFromPattern($request->get('target_label'));
		if (null == $target) {
			return $this->redirectHome();
		}

		// Si on clic depuis un fichier
		if ($request->get('resource_id')) {
			$resource = ResourceQuery::create('r')->findPk($request->get('resource_id'));
			if (null == $resource) {
				return $this->redirectHome();
			}

			$from = $this->getLabelFromSelectionKey($resource->getId());
			$resource->move($from, $target);

			return new Response();
		}

		$resources = $this->getResourcesFromSelection();
		foreach ($resources as $resource) {
			$from = $this->getLabelFromSelectionKey($resource->getId());
			$resource->move($from, $target);
		}

		$this->clearSelection();

		$resourceIds = array();
		foreach ($resources as $resource) {
			$resourceIds[] = $resource->getId();
		}

		return new Response(json_encode(array(
			'selection_ids'	=> $resourceIds
		)));
	}

	/**
	 * @Route("/copier", name="resource_selection_copy")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
	 */
	public function copyAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('target_label', false) === false) {
			return $this->redirectHome();
		}

		$target = $this->getLabelFromPattern($request->get('target_label'));
		if (null == $target) {
			return $this->redirectHome();
		}

		// Si on clic depuis un fichier
		if ($request->get('resource_id')) {
            if ($request->get('provider_id')) {
                $resource = ResourceQuery::create('r')
                    ->join('r.ResourceProvider rp')
                    ->where('rp.ProviderId = ?', $request->get('provider_id'))
                    ->where('rp.Reference = ?', $request->get('resource_id'))
                    ->where('rp.Uai = ?', $request->get('uai'))
                ->findOne();

                if (null == $resource) {
                    $providerResource = null;
                    try {
                        $providerResource = $this->get('bns.provider_resource_manager')->getProviderResource($request->get('uai'), $request->get('provider_id'), $request->get('resource_id'));

                        if (null == $providerResource) {
                            return $this->renderError("Cette ressource pédagogique n'existe pas. Veuillez réessayer.");
                        }
                    }
                    catch (WaitingForCacheException $e) {
                         return $this->renderError("Une erreur est survenue, veuillez rafraichir la page.");
                    }

                    $resource = $this->get('bns.resource_creator')->createFromProviderResource($providerResource, $this->get('bns.right_manager')->getCurrentGroupId(), $target);
                }

                return $this->forward('BNSAppResourceBundle:FrontNavigation:navigateFile', array(
                    'labelSlug'     => $target->getSlug(),
                    'resourceSlug'  => $resource->getSlug(),
                    'insertDockBar' => false
                ));
            }
            
            $resource = ResourceQuery::create('r')->findPk($request->get('resource_id'));
            if (null == $resource) {
                return $this->redirectHome();
            }

			$resource->copyTo($target);

			return new Response();
		}

		$resources = $this->getResourcesFromSelection();
		foreach ($resources as $resource) {
			$resource->copyTo($target);
		}

		$this->clearSelection();

		return new Response();
	}

	/**
	 * @param int $key
	 *
	 * @return Label
	 */
	protected function getLabelFromSelectionKey($key)
	{
		$selection = $this->get('session')->get('resource_selection');
		$labelId = $selection[$key]['label'];
		$labelType = $selection[$key]['label_type'];

		if ($labelType == 'user') {
			$query = ResourceLabelUserQuery::create();
		}
		elseif($labelType == 'group') {
			$query = ResourceLabelGroupQuery::create();
		}

		return $query->findOneById($labelId);
	}

	/**
     * @Route("/fichier/supprimer/confirmation", name="resource_file_delete_confirm")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function fileDeleteConfirmAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') ||
			$request->get('label_id', false) === false || $request->get('label_type', false) === false || $request->get('resource_id', false) === false) {
			return new Response();
		}

		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');
		$resource = $this->getResourceFromSelection($request->get('resource_id'), $request->get('label_type'), $request->get('label_id'), true);

		// Security
		// Resource not found, continue
		if (null == $resource) {
			return new Response();
		}

		// Check
		if ($request->get('label_type') == 'group') {
			$links = $resource->getResourceLinkGroups();
			$label = $links[0]->getResourceLabelGroup();
		}
		else {
			$links = $resource->getResourceLinkUsers();
			$label = $links[0]->getResourceLabelUser();
		}

		if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label)) {
			return new Response();
		}

		// isLastOccurrence && hasForeignOccurrence ?
		$canManage = $resourceRightManager->canManageResource($resource, $label);
		if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $request->get('label_id'), $canManage) &&
			$resourceManager->hasForeignOccurrence($resource, $this->getUser()->getId(), $canManage)) {
			return $this->render('BNSAppResourceBundle:Modal:file_label_delete_foreign_occurrence_partial_body.html.twig');
		}

		
		return $this->render('BNSAppResourceBundle:Modal:file_label_delete_partial_body.html.twig');
	}

	/**
     * @Route("/fichier/supprimer", name="resource_file_delete")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function fileDeleteAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') ||
			$request->get('label_id', false) === false || $request->get('label_type', false) === false || $request->get('resource_id', false) === false) {
			return new Response();
		}

		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');
		$resource = $this->getResourceFromSelection($request->get('resource_id'), $request->get('label_type'), $request->get('label_id'), true);

		// Security
		// Resource not found, continue
		if (null == $resource) {
			return new Response();
		}

		// Check
		if ($request->get('label_type') == 'group') {
			$links = $resource->getResourceLinkGroups();
			$label = $links[0]->getResourceLabelGroup();
		}
		else {
			$links = $resource->getResourceLinkUsers();
			$label = $links[0]->getResourceLabelUser();
		}

		if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label)) {
			return new Response();
		}

		// Finally
		$canManage = $resourceRightManager->canManageResource($resource, $label);
		$isLastOccurrence = $resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $request->get('label_id'), $canManage);
		$resourceManager->delete($resource, $this->getUser()->getId(), $request->get('label_type'), $request->get('label_id'), $isLastOccurrence);

		return new Response();
	}

	/**
	 * @Route("/fichier/restaurer", name="resource_file_restore")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
	 */
	public function fileRestoreAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') ||
			$request->get('label_id', false) === false || $request->get('label_type', false) === false || $request->get('resource_id', false) === false) {
			return new Response();
		}

		$resource = $this->getResourceFromSelection($request->get('resource_id'), $request->get('label_type'), $request->get('label_id'), false);

		// Security
		// Resource not found, continue
		if (null == $resource) {
			return new Response();
		}

		// NOT has rights
		if ($request->get('label_type') == 'group') {
			$links = $resource->getResourceLinkGroups();
			if (!$this->get('bns.resource_right_manager')->canManageResource($resource, $links[0]->getResourceLabelGroup())) {
				return new Response();
			}
		}

		// Finally
		if ($request->get('label_type') == 'user') {
			$links = $resource->getResourceLinkUsers();
		}
		else {
			$links = $resource->getResourceLinkGroups();
		}

		$links[0]->setStatus(ResourceLinkUser::STATUS_ACTIVE);
		$links[0]->save();

		// Restore resource
		if ($resource->getStatusDeletion() == Resource::DELETION_STATUS_GARBAGE) {
			$resource->setStatusDeletion(Resource::DELETION_STATUS_ACTIVE);
			$resource->save();
		}

		return new Response();
	}
}

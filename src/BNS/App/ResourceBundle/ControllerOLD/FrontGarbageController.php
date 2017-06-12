<?php

namespace BNS\App\ResourceBundle\Controller;

use \BNS\App\ResourceBundle\Model\Resource;
use \BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use \BNS\App\ResourceBundle\Model\ResourceLinkUser;
use \BNS\App\ResourceBundle\Model\ResourceLinkUserQuery;
use \BNS\App\ResourceBundle\Model\ResourceQuery;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/corbeille")
 */
class FrontGarbageController extends CommonController
{
	/**
	 * @Route("/restaurer", name="resource_garbage_restore")
	 */
	public function restoreFromGarbageAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('items', false) === false) {
			return new Response();
		}
		
		$restoredItems = array();
		$items = $request->get('items');
		
		foreach ($items as $item) {
			$resource = $this->getResourceFromSelection($item['resource_id'], $item['label_type'], $item['label_id']);
			
			// Security
			// Resource not found, continue
			if (null == $resource) {
				continue;
			}

			// NOT has rights
			if ($item['label_type'] == 'group') {
				$links = $resource->getResourceLinkGroups();
				if (!$this->get('bns.resource_right_manager')->canManageResource($resource, $links[0]->getResourceLabelGroup())) {
					continue;
				}
			}

			// Finally
			if ($item['label_type'] == 'user') {
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

			$restoredItems[] = '#item-' . $item['resource_id'] . '-' . $item['label_id'];
		}
		
		return new Response(json_encode($restoredItems));
	}

	/**
	 * @Route("/supprimer/confirmation", name="resource_garbage_delete_confirm")
	 */
	public function deleteConfirmationFromGarbageAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('items', false) === false) {
			return new Response();
		}

		$items = $request->get('items');
		$deletableItems = array();
		$jsonDeletableItems = array();
		$errorItems = array();
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');

		foreach ($items as $item) {
			$resource = $this->getResourceFromSelection($item['resource_id'], $item['label_type'], $item['label_id']);

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
			if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label, $canManage)) {
				$errorItems[] = array(
					'resource' => $resource,
					'message'  => 'RIGHTS',
					'label_id' => $item['label_id']
				);

				continue;
			}

			$hasForeignOccurrence = false;
			if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $item['label_id'], $canManage)) {
				$hasForeignOccurrence = $resourceManager->hasForeignOccurrence($resource, $this->getUser()->getId(), $canManage);
			}

			// Finally
			$deletableItems[] = array(
				'resource'				 => $resource,
				'label_id'				 => $item['label_id'],
				'has_foreign_occurrence' => $hasForeignOccurrence
			);
			$jsonDeletableItems[] = $item;
		}

		return $this->render('BNSAppResourceBundle:Modal:garbage_delete_body.html.twig', array(
			'errorItems'		 => $errorItems,
			'deletableItems'	 => $deletableItems,
			'jsonDeletableItems' => json_encode($jsonDeletableItems)
		));
	}

	/**
	 * @Route("/supprimer", name="resource_garbage_delete")
	 */
	public function deleteFromGarbageAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $request->get('items', false) === false) {
			return new Response();
		}

		$items = $request->get('items');
		$deletedItems = array();
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');

		foreach ($items as $item) {
			$resource = $this->getResourceFromSelection($item['resource_id'], $item['label_type'], $item['label_id']);

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
			if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label, $canManage)) {
				continue;
			}

			// Finally
			if ($item['label_type'] == 'user') {
				$links = $resource->getResourceLinkUsers();
			}
			else {
				$links = $resource->getResourceLinkGroups();
			}

			// Delete link
			$links[0]->delete();

			// Delete document
			if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $item['label_id'], $canManage)) {
				$resource->setStatusDeletion(Resource::DELETION_STATUS_DELETED);
				$resource->save();

                // Delete linked provider resource
                if ($resource->getTypeUniqueName() == 'PROVIDER_RESOURCE') {
                    $resource->getResourceProvider()->delete();
                }
			}

			// Delete foreign links
			if ($resource->getStatusDeletion() == Resource::DELETION_STATUS_DELETED) {
				ResourceLinkUserQuery::create('rl')
					->where('rl.ResourceId = ?', $resource->getId())
				->delete();

				ResourceLinkGroupQuery::create('rl')
					->where('rl.ResourceId = ?', $resource->getId())
				->delete();
			}

			// Recalculate size quota for user or group
			if ($item['label_type'] == 'user') {
				$this->get('bns.resource_manager')->recalculateQuota($item['label_type'], $this->getUser()->getId(), $this->getUser());
			}
			else {
				$currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
				$this->get('bns.resource_manager')->recalculateQuota($item['label_type'], $currentGroup->getId(), $currentGroup);
			}

			// Feedback
			$deletedItems[] = '#item-' . $item['resource_id'] . '-' . $item['label_id'];
		}

		return new Response(json_encode($deletedItems));
	}

	/**
	 * @Route("/vider/confirmation", name="resource_garbage_empty_confirm")
	 */
	public function emptyGarbageConfirmationAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
			return new Response();
		}

		$deletableItems = array();
		$jsonDeletableItems = array();
		$errorItems = array();
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');
		$links = $this->getGarbageResourceLinks();

		foreach ($links as $items) {
			foreach ($items as $item) {
				$resource = $item['resource'];
				$label = $item['link']->getLabel();
				$canManage = $resourceRightManager->canManageResource($resource, $label);
				
				if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label, $canManage)) {
					$errorItems[] = array(
						'resource' => $resource,
						'message'  => 'RIGHTS',
						'label_id' => $label->getId()
					);

					continue;
				}

				$hasForeignOccurrence = false;
				if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $label->getId(), $canManage)) {
					$hasForeignOccurrence = $resourceManager->hasForeignOccurrence($resource, $this->getUser()->getId(), $canManage);
				}

				// Finally
				$deletableItems[] = array(
					'resource'				 => $resource,
					'label_id'				 => $label->getId(),
					'has_foreign_occurrence' => $hasForeignOccurrence
				);
				$jsonDeletableItems[] = array(
					'resource_id'	 => $resource->getId(),
					'label_type'	 => $label->getType(),
					'label_id'		 => $label->getId()
				);
			}
		}

		return $this->render('BNSAppResourceBundle:Modal:garbage_delete_body.html.twig', array(
			'errorItems'		 => $errorItems,
			'deletableItems'	 => $deletableItems,
			'jsonDeletableItems' => json_encode($jsonDeletableItems),
			'disableCross'		 => true
		));
	}

	/**
	 * @Route("/vider", name="resource_garbage_empty")
	 */
	public function emptyGarbageAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
			return new Response();
		}

		$deletedItems = array();
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$resourceManager = $this->get('bns.resource_manager');
		$links = $this->getGarbageResourceLinks();

		foreach ($links as $items) {
			foreach ($items as $item) {
				$resource = $item['resource'];
				$label = $item['link']->getLabel();
				$canManage = $resourceRightManager->canManageResource($resource, $label);
				
				if (!$this->get('bns.resource_right_manager')->canDeleteResource($resource, $this->getUser()->getId(), $label, $canManage)) {
					continue;
				}

				// Delete link
				$item['link']->delete();

				// Delete document
				if ($resourceManager->isLastOccurrence($resource, $this->getUser()->getId(), $label->getId(), $canManage)) {
					$resource->setStatusDeletion(Resource::DELETION_STATUS_DELETED);
					$resource->save();

                    // Delete linked provider resource
                    if ($resource->getTypeUniqueName() == 'PROVIDER_RESOURCE') {
                        $resource->getResourceProvider()->delete();
                    }
				}

				// Delete foreign links
				if ($resource->getStatusDeletion() == Resource::DELETION_STATUS_DELETED) {
					ResourceLinkUserQuery::create('rl')
						->where('rl.ResourceId = ?', $resource->getId())
					->delete();

					ResourceLinkGroupQuery::create('rl')
						->where('rl.ResourceId = ?', $resource->getId())
					->delete();
				}

				// Recalculate size quota for user or group
				if ($label->getType() == 'user') {
					$this->get('bns.resource_manager')->recalculateQuota('user', $this->getUser()->getId(), $this->getUser());
				}
				else {
					$currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
					$this->get('bns.resource_manager')->recalculateQuota('group', $currentGroup->getId(), $currentGroup);
				}

				// Feedback
				$deletedItems[] = '#item-' . $resource->getId() . '-' . $label->getId();
			}
		}

		return new Response(json_encode($deletedItems));
	}

	/**
	 * @return array
	 *  key : label slug
	 *    array (
	 *     - link: ResourceLink
	 *     - resource: Resource
	 *    )
	 */
	private function getGarbageResourceLinks()
	{
		$resources = ResourceQuery::create('r')
			->joinWith('r.ResourceLinkUser rlu', \Criteria::LEFT_JOIN)
			->joinWith('rlu.ResourceLabelUser rlau', \Criteria::LEFT_JOIN)
			->joinWith('r.ResourceLinkGroup rlg', \Criteria::LEFT_JOIN)
			->joinWith('rlg.ResourceLabelGroup rlag', \Criteria::LEFT_JOIN)
			->where('rlau.UserId = ?',$this->getUser()->getId())
			->orWhere('rlag.GroupId = ?', $this->get('bns.right_manager')->getCurrentGroup()->getId())
			->where('rlu.Status = ?', 0)
			->orWhere('rlg.Status = ?', 0)
			->orderByLabel('ASC')
			->groupBy('r.Id')
		->find();

		$links = array();
		foreach ($resources as $resource) {
			foreach ($resource->getAllLinks($this->get('bns.right_manager')->hasRight('RESOURCE_ADMINISTRATION'), false) as $link) {
				$links[$link->getLabel()->getSlug()][] = array(
					'link' => $link,
					'resource' => $resource
				);
			}
		}

		return $links;
	}
}

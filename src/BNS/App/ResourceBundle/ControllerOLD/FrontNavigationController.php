<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Utils\Byte;
use BNS\App\ResourceBundle\Form\Type\ResourceType;
use BNS\App\ResourceBundle\Model\ResourceFavoritesQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkUserQuery;
use BNS\App\ResourceBundle\Model\ResourceProviderQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\StoreBundle\Exception\WaitingForCacheException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontNavigationController extends CommonController
{
	/**
     * Sidebar de la navigation front des ressources
     * 
	 * @Template()
     */
	public function sidebarAction($groupId = null)
	{
		$rightManager = $this->get('bns.right_manager');
        $gm = $this->get('bns.group_manager');
		$userLabel = null;

        if (null == $groupId) {
            $groupId = $rightManager->getCurrentGroup()->getId();
            $currentGroupType = $rightManager->getCurrentGroupType();
            $currentGroup = $rightManager->getCurrentGroup();
            $gm->setGroup($currentGroup);
        }
        else {
            $currentGroup = GroupQuery::create('g')
                ->joinWith('g.GroupType gt')
            ->findPk($groupId);
            
            if (null == $currentGroup) {
                throw new \RuntimeException('The group with id : ' . $groupId . ' is NOT found !');
            }

            $currentGroupType = $currentGroup->getGroupType()->getType();
        }

        $accessGroups = $rightManager->getGroupsWherePermission('RESOURCE_ACCESS');

        if(!$this->get('bns.right_manager')->hasRight('RESOURCE_ACCESS') && isset($accessGroups[0]))
        {
            $groupId = $accessGroups[0]->getId();
            $currentGroupType = $accessGroups[0]->getType();
        }

		if ($this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			$userLabel = $rightManager->getModelUser()->getMediaFolderRoot();
		}

		// Le dossier en cours de mon groupe
		$groupContextLabel = ResourceLabelGroupQuery::create('rlg')
			->where('rlg.GroupId = ?', $groupId)
		->findRoots();

		$currentLabel = $this->getCurrentLabelFromSession();
		$usersFolder = false;

		// Quota ratio calculation
		$userManager = $this->get('bns.right_manager')->getUserManager();
		$quota = array();
		$quota['percent'] = $userManager->getResourceUsageRatio();
        $quota['used'] = Byte::formatBytes($userManager->getUser()->getResourceUsedSize(), 2);
        $quota['total'] = Byte::formatBytes($userManager->getRessourceAllowedSize(), 2);
        
		// Great quota
		if ($quota['percent'] < 50) {
			$quota['status'] = 'good';
		}
		// Middle quota
		elseif ($quota['percent'] < 75) {
			$quota['status'] = 'warning';
		}
		// Danger quota
		elseif ($quota['percent'] < 100) {
			$quota['status'] = 'danger';
		}
		// Full quota
		else {
			$quota['status'] = 'full';
		}

		// Search input examples
		$searchExemples = array('noël', 'vacances', 'printemps', 'montagne', 'fête des mères', 'cuisinier', 'cahier', 'vélo');

		// These params are sent to the view whatever the return statement
		$params = array(
			'userLabel'			=> $userLabel,
			'groupContextLabel'	=> $groupContextLabel[0],
			'currentGroupType'	=> $currentGroupType,
			'currentGroup'		=> $currentGroup,
			'quota'				=> $quota,
			'rrm'				=> $this->get('bns.resource_right_manager')->setUser($this->getUser()),
			'searchExample'		=> $searchExemples[rand(0, count($searchExemples) - 1)],
            'accessGroups'      => $accessGroups,
            'canHaveCatalog'    => $this->canHaveCatalog(),
            'hasUai'            => $gm->getAttribute('UAI') != false,
            'gm'                => $this->get('bns.group_manager')
		);

        if($this->container->hasParameter("has_medialandes"))
        {
            $params['has_medialandes'] = $this->get('bns.right_manager')->hasMedialandes(true,true);
        }

		if (!$currentLabel) {
			return array_merge($params, array(
				'root_level' => true
			));
		}
		else {
			// Recuperation de l'arbre en entier
			switch($currentLabel->getType()) {
				case 'user':
					$query = ResourceLabelUserQuery::create();
					$scopeId = $currentLabel->getUserId();
				break;
				case 'group':
					$query = ResourceLabelGroupQuery::create();
					$scopeId = $currentLabel->getGroupId();
				break;
			}

			$root = $query->findRoot($scopeId);

			// On remonte les "ancêtres"
			$testedLabel = $currentLabel;
			$selection_ids = array();

			while ($testedLabel->hasParent()) {
				$testedLabel = $testedLabel->getParent();
				$selection_ids[] = $testedLabel->getId();
			}

			return array_merge($params, array(
				'type'			=> $currentLabel->getType(),
				'current_label'	=> $currentLabel,
				'selection_ids'	=> $selection_ids,
				'root'			=> $root,
				'users_foler'	=> $usersFolder
			));
		}
	}

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	protected function initContentNavigation($params)
	{
		if (!isset($params['page'])) {
			$params['page'] = 0;
		}

		if ($params['page'] == 0) {
			$params['page'] = 1;
		}

		if (!isset($params['limit'])) {
			$params['limit'] = 25;
		}

		if (isset($params['need_label'])) {
			if ($params['need_label'] == true) {
				$cur_label = $this->getCurrentLabelFromSession();

				if ($cur_label) {
					$params['current_label'] = $cur_label;
					$params['current_label_id'] = $cur_label->getId();
				}
			}
		}

		if (!isset($params['current_label'])) {
			$params['current_label'] = null;
			$params['current_label_id'] = null;
		}

		if (isset($params['force_favorites'])) {
			if ($params['force_favorites'] == true) {
				$params['favorite_filter'] = true;
			}
		}

        $filters = $this->get('session')->get('resource_filters', false);
		if (!isset($params['favorite_filter'])) {
			$params['favorite_filter'] = false !== $filters && $filters['favorites'];
		}

		$params['user_id'] = $this->get('bns.right_manager')->getUserSessionId();

		// Tri
		if (false !== $filters && $filters['chrono']) {
			$params['sort'] = 'chrono';
		}
		else {
			$params['sort'] = "alpha";
		}

		if ($this->getActionType() == "select_image") {
			$params['filters'][] = 'IMAGE';
		}

		$params['resources'] = ResourceQuery::getResources($params);
		$resources_ids = array();

		foreach ($params['resources'] as $resource) {
			$resources_ids[] = $resource->getId();
		}
		
		if ($params['type'] == 'garbage') {
			$params['sorted_links'] = $this->sortLinks($params['resources']->getResults(), $params['type']);
		}

		// Optimisation pour force favorites possible
		$favorites = ResourceFavoritesQuery::create()->filterByResourceId($resources_ids)->find();
		$favs = array();

		foreach ($favorites as $fav) {
			$favs[] = $fav->getResourceId();
		}

		$params['favorites_ids'] = $favs;
		$params['selection_ids'] = $this->getResourcesIdsFromSelection();

		// Affichage
		$display = $this->get('session')->get('resource_display');

		if ($display == null || $display == "block") {
			$params['display'] = "block";
		}
		else {
			$params['display'] = "list";
		}

		return $this->renderHistory('BNSAppResourceBundle:Content:front_' . $params['type'] . '.html.twig', $params);
	}

	/**
     * Contenu de la navigation front des ressources
	 *
     * @Route("/dossier/{slug}", name="resource_navigate", defaults={"navigationContext"="folder"}, options={"expose": true})
     * @Route("/dossier/{slug}/page/{page}", name="resource_navigate_page", defaults={"navigationContext"="folder"})
     * @Route("/corbeille", name="resource_garbage", defaults={"navigationContext"="garbage"})
     * @Route("/corbeille/page/{page}", name="resource_garbage_page", defaults={"navigationContext"="garbage"})
     * @Route("/mes-favoris", name="resource_favorites", defaults={"navigationContext"="favorites"})
     * @Route("/mes-favoris/page/{page}", name="resource_favorites_page", defaults={"navigationContext"="favorites"})
     * @Route("/rechercher", name="resource_search", defaults={"navigationContext"="search"}, options={"expose": true})
     * @Route("/rechercher/page/{page}", name="resource_search_page", defaults={"navigationContext"="search"}, options={"expose": true})
     * @Route("/rechercher/ressources-pedagogiques", name="resource_search_provider_resource", defaults={"navigationContext"="search"}, options={"expose": true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function navigateAction($navigationContext, $_route, $slug = null, $page = 0)
	{
		$rm = $this->get('bns.right_manager');
		$rrm = $this->get('bns.resource_right_manager');
        $request = $this->getRequest();

		switch ($navigationContext) {
			case 'folder':
				// On set le label courrant à partir de la request
				$label = $this->getLabelFromRequest();

				if ($page == 0) {
					$this->clearFilters();
				}
			case 'filters':
				$canManage = false;

				// Filters call
				if (!isset($label)) {
					$label = $this->getCurrentLabelFromSession();
					$navigationContext = 'folder';
				}

				if ($label) {
					$this->setCurrentLabelIntoSession($label);
					$canManage = $rrm->canManageLabel($label);

					if ($label->getType() == 'group') {
						if ($label->isUserFolder()) {
							$this->get('session')->set('resource_current_user_folder_id', $label->getId());
						}
					}
				}

				// No label found ?
				if ($label === false) {
					if ($request->isXmlHttpRequest()) {
						return $this->renderError();
					}

					return $this->redirectHome();
				}

				$canManageSelection = false;
				$selections = $this->getResourcesFromSelection();
				foreach ($selections as $selectedResource) {
					$selectedLabel = $this->getResourceLabelFromSelection($selectedResource);
					if (null == $selectedLabel) {
						continue;
					}
					
					if ($rrm->canManageResourceFromSelection($selectedResource, $this->getUser()->getId(), $selectedLabel)) {
						$canManageSelection = true;

						break;
					}
				}

				$params = array(
					'page'				 => $page,
					'need_label'		 => true,
					'force_favorites'	 => false,
					'can_manage'		 => $canManage,
					'type'				 => $navigationContext,
					'canManageSelection' => $canManageSelection,
					'slug'				 => $label->getSlug()
				);
			break;

			case 'favorites':
				$this->clearFilters();

				$params = array(
					'page'			  => $page,
					'need_label'	  => false,
					'force_favorites' => true,
					'type'			  => $navigationContext
				);
			break;

			case 'garbage':
				$this->clearFilters();
				$this->clearCurrentLabelIntoSession();

				$params = array(
					'page'			  => $page,
					'need_label'	  => false,
					'force_favorites' => false,
					'type'			  => $navigationContext,
					'scope'			 => array(
						'group' => $rm->getCurrentGroup()->getId()
					)
				);
			break;

			case 'search':
                if (!$request->isMethod('POST')) {
                    return $this->redirectHome();
                }

				$canManageSelection = false;
				$selections = $this->getResourcesFromSelection();
                
                foreach ($selections as $selectedResource) {
					$selectedLabel = $this->getResourceLabelFromSelection($selectedResource);
					if (null == $selectedLabel) {
						continue;
					}

					if ($rrm->canManageResource($selectedResource, $selectedLabel)) {
						$canManageSelection = true;

						break;
					}
				}

                /* @var $searchManager \BNS\App\ResourceBundle\Search\SearchManager */
                $searchManager = $this->get('bns.resource.search_manager');

                if (false !== $request->get('terms', false)) {
                    $searchManager->terms($request->get('terms'));
                }

                if (false !== $request->get('filters', false)) {
                    foreach ($request->get('filters') as $uniqueName => $values) {
                        $searchManager->filters($uniqueName, $values);
                    }
                }

                if (false !== $request->get('contexts', false)) {
                    foreach ($request->get('contexts') as $context) {
                        $searchManager->context($context);
                    }
                }

                if (false !== $request->get('groups', false)) {
                    $searchManager->groups($request->get('groups'));
                }

                $results = $searchManager->find($page > 1 ? $page : 1);
                $resourcesPager = isset($results['RESOURCES']) ? $results['RESOURCES'] : array();
                $resources = is_array($resourcesPager) ? array() : $resourcesPager->getResults();
                $resourcesIds = array();
                
                foreach ($resources as $resource) {
                    $resourcesIds[] = $resource->getId();
                }

                $links = array();
                if (isset($resources[0])) {
                    $links = $this->sortLinks($resources, $navigationContext);
                }

                $favProviderResourceIds = array();
                if (isset($results['PROVIDER_RESOURCES']) && false !== $results['PROVIDER_RESOURCES']) {
                    $favProviderResources = ResourceProviderQuery::create('rp')
                        ->join('rp.Resource r')
                        ->join('r.ResourceFavorites rf')
                    ->find();

                    foreach ($favProviderResources as $resource) {
                        $favProviderResourceIds[$resource->getUai()][$resource->getProviderId()][] = $resource->getReference();
                    }
                }

				$params = array(
					'page'                   => $page,
					'need_label'             => false,
					'type'                   => $navigationContext,
					'white_list_url'	     => $this->getSearchWhiteListUrl(),
					'canManageSelection'     => $canManageSelection,
                    'results'                => $results,
                    'resources'              => $resourcesPager,
                    'favorites_ids'          => ResourceFavoritesQuery::create()->filterByResourceId($resourcesIds)->find()->getPrimaryKeys(),
                    'provider_favorites_ids' => $favProviderResourceIds,
                    'selection_ids'          => $this->getResourcesIdsFromSelection(),
                    'sorted_links'           => $links,
                    'query'                  => $request->get('terms', false) === false ? '' : join(' ', $request->get('terms'))
				);


                if ('resource_search_provider_resource' == $_route) {
                    if (isset($results['PROVIDER_RESOURCES']) && false === $results['PROVIDER_RESOURCES']) {
                        $response = new Response();
                        $response->setStatusCode(206);
                        
                        return $response;
                    }

                    return $this->renderHistory('BNSAppResourceBundle:ContentBlock:front_block_search_provider_resources_list.html.twig', $params);
                }

            return $this->renderHistory('BNSAppResourceBundle:Content:front_' . $navigationContext . '.html.twig', $params);
		}

		return $this->initContentNavigation($params);
	}

    /**
     * @Route("/ressource-pedagogique/{uai}/{providerId}/{id}", name="resource_navigate_provider_resource")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function navigateProviderResourceFileAction($uai, $providerId, $id)
    {
        $resource = ResourceQuery::create('r')
            ->join('r.ResourceProvider rp')
            ->where('rp.Uai = ?', $uai)
            ->where('rp.ProviderId = ?', $providerId)
            ->where('rp.Reference = ?', $id)
        ->findOne();

        if (null != $resource) {
            $label = $resource->getStrongLinkedLabel();

            return $this->redirect($this->generateUrl('resource_navigate_file', array(
                'labelSlug'    => $label->getSlug(),
                'resourceSlug' => $resource->getSlug()
            )));
        }

        $providerResourceManager = $this->get('bns.provider_resource_manager');
        if (!$providerResourceManager->hasUai($uai)) {
            return $this->redirectHome();
        }

        $resource = null;
        try {
            $resource = $providerResourceManager->getProviderResource($uai, $providerId, $id);

            if (null == $resource) {
                if ($this->getRequest()->isXmlHttpRequest()) {
                    return $this->renderError("Cette ressource pédagogique n'existe pas. Veuillez réessayer.");
                }
                
                return $this->redirectHome();
            }
        }
        catch (WaitingForCacheException $e) {
            return $this->renderHistory('BNSAppResourceBundle:Provider:provider_waiting_for_cache.html.twig', array(
                'url' => $this->generateUrl('resource_navigate_provider_resource', array(
                    'uai'        => $uai,
                    'providerId' => $providerId,
                    'id'         => $id,
                ), true)
            ));
        }

        return $this->renderHistory('BNSAppResourceBundle:ContentBlock:front_block_provider_resource.html.twig', array(
			'resource' => $resource
		));
    }

	/**
	 * @Route("/dossier/{labelSlug}/fichier/{resourceSlug}", name="resource_navigate_file", options={"expose": true})
	 * @Route("/fichier/{resourceSlug}/iframe", name="resource_navigate_file_from_iframe", defaults={"insertDockBar": false, "labelSlug": null})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
	 */
	public function navigateFileAction($labelSlug, $resourceSlug, $insertDockBar = true)
	{
		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $resourceSlug)
		->findOne();

		if (null == $resource) {
			// TODO message alert

			return $this->redirectHome();
		}

		$resourceRightManager = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.resource_manager');

		if (!$resourceRightManager->canReadResource($resource)) {
			return $this->redirectHome();
		}

		if (null != $labelSlug) {
			// Le label existe et est bien lié au document ?
			$label = ResourceLabelGroupQuery::create('rlag')
				->joinWith('rlag.Group g')
				->joinWith('rlag.ResourceLinkGroup rlg')
				->join('rlg.Resource r')
				->where('rlag.Slug = ?', $labelSlug)
				->where('r.Slug = ?', $resourceSlug)
			->findOne();

			if (null == $label) {
				$label = ResourceLabelUserQuery::create('rlau')
					->joinWith('rlau.ResourceLinkUser rlu')
					->join('rlu.Resource r')
					->where('rlau.Slug = ?', $labelSlug)
					->where('r.Slug = ?', $resourceSlug)
				->findOne();
			}
		}
		else {
			$label = $resource->getStrongLinkedLabel();
		}

        if (null == $label) {
            return $this->redirectHome();
        }


		if ($label->getType() == 'user') {
			$links = $label->getResourceLinkUsers();
			$isDeletedFile = $links[0]->getStatus() == 0;
		}
		else {
			$links = $label->getResourceLinkGroups();
			$isDeletedFile = $links[0]->getStatus() == 0;
		}

		$rm->setObject($resource);
		$this->setCurrentResource($resource->getId());
		$rightManager = $this->get('bns.right_manager');
        $canAccess = true;

        if ($resource->getTypeUniqueName() == 'PROVIDER_RESOURCE') {
            try {
                $resourceProvider = $resource->getResourceProvider();

                if (null == $this->get('bns.provider_resource_manager')->getProviderResource($resourceProvider->getUai(), $resourceProvider->getProviderId(), $resourceProvider->getReference())) {
                    $canAccess = false;
                }
            }
            catch (WaitingForCacheException $e) {
                return $this->renderHistory('BNSAppResourceBundle:Provider:provider_waiting_for_cache.html.twig', array(
                    'url' => $this->generateUrl('resource_navigate_file', array(
                        'labelSlug'    => $label->getSlug(),
                        'resourceSlug' => $resource->getSlug()
                    ), true)
                ));
            }
        }

		return $this->renderHistory('BNSAppResourceBundle:ContentBlock:front_block_file.html.twig', array(
			'resource'		  => $resource,
			'rm'		 	  => $rm,
			'can_manage'	  => $resourceRightManager->canManageResource($resource, $label),
			'navigationType'  => $this->getResourceNavigationType(),
			'reference'		  => $this->getCallBackReference(),
			'select_img_id'   => $this->get('session')->get('resource_select_image_final_id'),
			'select_callback' => $this->get('session')->get('resource_select_image_callback'),
			'canAddResource'  => $rightManager->hasRightSomeWhere('RESOURCE_MY_RESOURCES'),
			'currentGroupId'  => $rightManager->getCurrentGroup()->getId(),
			'label'			  => $label,
			'isDeletedFile'	  => $isDeletedFile,
            'canAccess'       => $canAccess
		), $insertDockBar);
	}

	/**
     * Edition d'une ressource
	 *
     * @Route("/dossier/{labelSlug}/fichier/{resourceSlug}/editer", name="resource_navigate_file_edit", options={"expose": true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function editFileAction($labelSlug, $resourceSlug)
	{
		$request = $this->getRequest();
		if (!$request->isXmlHttpRequest()) {
			return $this->redirectHome();
		}

		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $resourceSlug)
		->findOne();

		if (null == $resource) {
			return $this->redirectHome();
		}

		$rightManager = $this->get('bns.right_manager');
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.resource_manager');

		// Le label existe et est bien lié au document ?
		$label = ResourceLabelGroupQuery::create('rlag')
			->joinWith('rlag.Group g')
			->join('rlag.ResourceLinkGroup rlg')
			->join('rlg.Resource r')
			->where('rlag.Slug = ?', $labelSlug)
			->where('r.Slug = ?', $resourceSlug)
		->findOne();

		if (null == $label) {
			$label = ResourceLabelUserQuery::create('rlau')
				->join('rlau.ResourceLinkUser rlu')
				->join('rlu.Resource r')
				->where('rlau.Slug = ?', $labelSlug)
				->where('r.Slug = ?', $resourceSlug)
			->findOne();

			if (null == $label) {
				return $this->redirectHome();
			}
		}

		if ($label->getType() == 'user') {
			$links = $label->getResourceLinkUsers();
			$isDeletedFile = $links[0]->getStatus() == 0;
		}
		else {
			$links = $label->getResourceLinkGroups();
			$isDeletedFile = $links[0]->getStatus() == 0;
		}

		if (!$resourceRightManager->canManageResource($resource, $label)) {
			return $this->redirectHome();
		}

		$rm->setObject($resource);
		$form = $this->createForm(new ResourceType($resource), $resource);
        $canAccess = true;

        if ($resource->getTypeUniqueName() == 'PROVIDER_RESOURCE') {
            try {
                $resourceProvider = $resource->getResourceProvider();

                if (null == $this->get('bns.provider_resource_manager')->getProviderResource($resourceProvider->getUai(), $resourceProvider->getProviderId(), $resourceProvider->getReference())) {
                    $canAccess = false;
                }
            }
            catch (WaitingForCacheException $e) {
                return $this->renderHistory('BNSAppResourceBundle:Provider:provider_waiting_for_cache.html.twig', array(
                    'url' => $this->generateUrl('resource_navigate_file_edit', array(
                        'labelSlug'    => $label->getSlug(),
                        'resourceSlug' => $resource->getSlug()
                    ), true)
                ));
            }
        }

		if ($request->getMethod() == 'POST') {
			$form->bind($request);

			if ($form->isValid()) {
				// perform some action, such as saving the task to the database
				$resource->save();

				return $this->render('BNSAppResourceBundle:ContentBlock:front_block_file.html.twig', array(
					'resource'		  => $resource,
					'rm'			  => $rm,
					'can_manage'	  => true,
					'navigationType'  => $this->getResourceNavigationType(),
					'reference'		  => $this->getCallBackReference(),
					'select_img_id'   => $this->get('session')->get('resource_select_image_final_id'),
					'select_callback' => $this->get('session')->get('resource_select_image_callback'),
					'currentGroupId'  => $rightManager->getCurrentGroup()->getId(),
					'label'			  => $label,
					'isDeletedFile'	  => $isDeletedFile,
                    'canAccess'       => $canAccess
				));
			}
		}

		return $this->render('BNSAppResourceBundle:ContentBlock:front_block_file_edit.html.twig', array(
			'resource'	 => $resource,
			'form'		 => $form->createView(),
			'rm'		 => $rm,
			'can_manage' => true,
			'tmp_labels' => array(),
			'label'		 => $label,
            'canAccess'  => $canAccess
		));
	}

	/**
     * Gestion des filtres en sidebar
	 *
     * @Route("/filtrer", name="resource_filter" , options={"expose"=true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
	 *
	 * @Template()
     */
	public function filterAction(Request $request)
	{
        if (!$request->isXmlHttpRequest() || false === $request->get('type', false)) {
            return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
        }

		$type = $this->getRequest()->get('type');
        $filters = $this->get('session')->get('resource_filters', array(
            'favorites' => false,
            'alpha'     => false,
            'chrono'    => false
        ));

        switch ($type) {
            case 'favorites':
                $filters['favorites'] = !$filters['favorites'];
            break;

            case 'alpha':
                $filters['alpha'] = !$filters['alpha'];
                if ($filters['alpha']) {
                    $filters['chrono'] = false;
                }
            break;

            case 'chrono':
                $filters['chrono'] = !$filters['chrono'];
                if ($filters['chrono']) {
                    $filters['alpha'] = false;
                }
            break;
        }

        $this->get('session')->set('resource_filters', $filters);

        return $this->navigateAction('filters', null);
	}

	/**
     * Gestion de la selection de resources
	 *
     * @Route("/document/mettre-en-favoris", name="resource_document_favorite")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
	public function resourceFavoriteAction(Request $request)
	{
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || $this->getRequest()->get('resource_id', false) === false) {
			return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
		}

        if (false !== $this->getRequest()->get('provider_id', false)) {
            $resource = ResourceQuery::create('r')
                ->join('r.ResourceProvider rp')
                ->where('rp.ProviderId = ?', $request->get('provider_id'))
                ->where('rp.Reference = ?', $request->get('resource_id'))
                ->where('rp.Uai = ?', $request->get('uai'))
            ->findOne();

            if (null == $resource) {
                return new Response();
            }

            /*if (null == $resource) {
                $providerResource = null;
                try {
                    $providerResource = $this->get('bns.provider_resource_manager')->getProviderResource($uai, $providerId, $id);
                }
                catch (WaitingForCacheException $e) {
                     // TODO
                }
                
                $resource = $this->get('bns.resource_creator')->createFromProviderResource($providerResource), $this->get('bns.right_manager')->getCurrentGroupId());
            }*/
        }
        else {
            // Resource is found ?
            $resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
            if (null == $resource) {
                return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
            }
        }

		// Secure
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$rightManager = $this->get('bns.right_manager');
		$rightManager->forbidIf(!$resourceRightManager->canReadResource($resource));

		// Finally
		$resource->toggleFavorite($this->get('bns.right_manager')->getModelUser()->getId());

		return new Response();
	}

    /**
     * @Route("/ressource-pedagogique/{uai}/{providerId}/{id}/acceder", name="resource_provider_goto")
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function goToResourceProviderAction($uai, $providerId, $id)
    {
        $url = null;
        try {
            $url = $this->get('bns.provider_resource_manager')->getProviderResourceUrl($uai, $providerId, $id);

            if (null == $url) {
                return $this->renderError('Cette ressource pédagogique n\'existe pas.');
            }
        }
        catch (WaitingForCacheException $e) {
             $response = new Response();
             $response->setStatusCode(206);

             return $response;
        }

        return $this->render('BNSAppResourceBundle:Provider:provider_iframe.html.twig', array(
            'url' => $url
        ));
    }

	/**
	 * @return string
	 */
	private function getSearchWhiteListUrl()
	{
		// Envoi de l'Url du XML d'annotations pourle moteur de recherche Google
		switch ($this->get('kernel')->getEnvironment()) {
			case 'app_dev':
				return "https://www.beneyluschool.net/mediatheque/white-list/456ad277cc96844587982e15668369c6";

			default:
				$key = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('WHITE_LIST_UNIQUE_KEY');
				// Si clé non initialisée, on l'initialise
				if ($key == null || $key == '') {
					$this->get('bns.resource_manager')->updateUniqueKey($this->get('bns.right_manager')->getCurrentGroup()->getId());
					$key = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('WHITE_LIST_UNIQUE_KEY');
				}

				return $this->get('router')->generate('BNSAppResourceBundle_white_list_xml', array(
					'key' => $key
				), true);
		}
	}

    /**
     * @param array $resources
     * @param string $type
     *
     * @return array
     */
    private function sortLinks($resources, $type)
    {
        // SQL opti
        $resources->populateRelation('ResourceLinkUser', ResourceLinkUserQuery::create('rlu')
            ->joinWith('rlu.ResourceLabelUser rula')
            ->where('rlu.Status = ?', $type == 'search')
        );
        $resources->populateRelation('ResourceLinkGroup', ResourceLinkGroupQuery::create('rlg')
            ->joinWith('rlg.ResourceLabelGroup rgla')
            ->where('rlg.Status = ?', $type == 'search')
        );

        $links = array();
        //$canManage = $this->get('bns.right_manager')->hasRight('RESOURCE_ADMINISTRATION');

        foreach ($resources as $resource) {
            foreach ($resource->getAllLinks(true, $type == 'search') as $link) {
                $links[$link->getLabel()->getSlug()][] = array(
                    'link'     => $link,
                    'resource' => $resource
                );
            }
        }

        return $links;
    }
}
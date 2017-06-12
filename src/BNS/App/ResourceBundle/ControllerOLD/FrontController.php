<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\FixtureBundle\Model\MigrationQuery;
use BNS\App\ResourceBundle\BNSResourceManager;
use BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkUserQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Buzz\Browser;

class FrontController extends CommonController
{
	/**
     * Page d'accueil des ressources si accès depuis la dockbar (page vierge - seulement illustration - et chargement de l'Iframe)
     * @Route("accueil/{type}", name="BNSAppResourceBundle_home", defaults={"type" = "search"})
	 * @Template()
	 * @RightsSomeWhere("RESOURCE_ACCESS")
	 * @param $type : pour la gestion des actions possibles (savoir si on parcourt ou si on insert : none || insert || join || select_image)
     */
    public function frontAction($type)
    {
        $this->setActionType($type);
		return array('type' => $type,'resourceId' => 'none');
    }

	/**
     * Page d'accueil des ressources
	 *
     * @Route("/", name="BNSAppResourceBundle_front", defaults={"insertDockBar": true}, options={"expose": true})
     * @Route("/joindre", name="resource_iframe_join", defaults={"insertDockBar": false, "navigationType": "join"})
     * @Route("/inserer", name="resource_iframe_insert", defaults={"insertDockBar": false, "navigationType": "insert"})
     * @Route("/selectionner", name="resource_iframe_select", defaults={"insertDockBar": false, "navigationType": "select"})
     */
    public function indexAction($insertDockBar, $navigationType = 'ressources')
    {
        // Selection du type de navigation
		// TODO what's ?
		/*$this->get('session')->remove('resource_current_label');
		$this->get('session')->remove('resource_navigation_page');*/

		// Reset all session vars
		$this->killResourceNavigationType();
		$this->clearFilters();
		$this->clearSelection();
        $this->clearGroupContext();
        $this->clearCurrentLabelIntoSession();

		$query = ResourceQuery::create('r')
			->join('r.ResourceLinkUser rlu', \Criteria::LEFT_JOIN)
			->join('r.ResourceLinkGroup rlg', \Criteria::LEFT_JOIN)
			->join('rlg.ResourceLabelGroup rlag', \Criteria::LEFT_JOIN)
        ;

        $rightManager = $this->get('bns.right_manager');
        if ($rightManager->hasRight('RESOURCE_ACCESS')) {
			$query->where('rlag.GroupId = ?', $rightManager->getCurrentGroup()->getId());
        }else{
            $query->where('r.UserId = ?', $this->getUser()->getId());
        }

        $query->where('r.StatusDeletion = ?', 1)
			->where('rlu.Status = ?', 1)
			->orWhere('rlg.Status = ?', 1)
			->orderBy('r.CreatedAt', \Criteria::DESC)
			->limit(5)
		;

		$lastResources = $query->find();

		$lastResources->populateRelation('ResourceLinkUser', ResourceLinkUserQuery::create('rlu')
			->joinWith('rlu.ResourceLabelUser rlau')
			->orderBy('rlu.CreatedAt', \Criteria::DESC))
		;
		$lastResources->populateRelation('ResourceLinkGroup', ResourceLinkGroupQuery::create('rlg')
			->joinWith('rlg.ResourceLabelGroup rlag')
			->orderBy('rlg.CreatedAt', \Criteria::DESC))
		;

		$this->setResourceNavigationType($navigationType);

		return $this->render('BNSAppResourceBundle:Front:home.html.twig', array(
			'insertDockBar'	   => $insertDockBar,
			'lastResources'	   => $lastResources,
			'objectiveContext' => strtoupper($navigationType),
			'allowedType' => $this->get('session')->get('resource_select_file_type', null),
			'currentGroupId'   => $rightManager->getCurrentGroupId(),
            'nbAuthorisedGroups' => count($this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ACCESS'))
		));
    }

	/**
    * Redirection vers une categorie
    * @Route("categorie/{type}/{label_id}", name="BNSAppResourceBundle_show_category" , options={"expose"=true})
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template("BNSAppResourceBundle:Front:index.html.twig")
    */
    public function showCategoryAction(Request $request)
    {
		$this->setCurrentLabelIntoSession($this->getLabelFromRequest($request));

        // Selection du type de navigation
		return array(
			'current_label' => $label
		);
    }

	/**
    * Page d'accueil de l'administration
    * @Route("iframe/{type}/{reference}", name="BNSAppResourceBundle_call_iframe" , options={"expose"=true}, defaults={"reference" = "none", "resourceId" = "none" })
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @param $type : action possible (insert || navigation || join)
    */
    public function callIframeAction($type = 'search', $reference = 'none', $resourceId = 'none', $resourceSlug = null)
    {
		if ($reference != 'none') {
			$this->setCallBackReference($reference);
		}

		$this->setActionType($type);

		return array(
			'type' => $type,
			'resourceId' => $resourceId,
			'resourceSlug' => $resourceSlug
		);
    }

	/**
     * Page de call de la sélection d'une image
	 *
     * @Route("selection/image/{final_id}/{callback}", name="BNSAppResourceBundle_front_select_image_caller" , options={"expose"=true})
	 *
	 * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function selectImageCallerAction($final_id, $callback)
    {
        $this->get('session')->set('resource_select_image_final_id', $final_id);
		$this->get('session')->set('resource_select_image_callback', $callback);
		$this->setResourceNavigationType('select');

		return $this->forward('BNSAppResourceBundle:Front:callIframe', array(
			'type' => 'select')
		);
    }

    /**
     * Page de call de la sélection d'un fichier
     *
     * TODO faire passer toutes les sélections IMAGE par ce controleur, plus
     * générique
     *
     * @Route("selection/file/{final_id}/{callback}", name="BNSAppResourceBundle_front_select_file_caller" , options={"expose"=true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function selectFileCallerAction($final_id, $callback)
    {
        $allowed_type = $this->getRequest()->get('allowed_type');

        // TODO enlever ce default quand tous les calls vers ce ctrl seront configurés
        if (!$allowed_type) {
            $allowed_type = 'IMAGE';
        }

        $this->get('session')->set('resource_select_file_final_id', $final_id);
        $this->get('session')->set('resource_select_file_callback', $callback);
        if ($allowed_type) {
            $this->get('session')->set('resource_select_file_type', $allowed_type);
        } else {
            $this->get('session')->remove('resource_select_file_type');
        }

        $this->setResourceNavigationType('select');

        return $this->forward('BNSAppResourceBundle:Front:callIframe', array(
                'type' => 'select')
        );
    }

	/**
     * Page pour voir directement une resource depuis son Id
	 *
     * @Route("/voir/{slug}", name="resource_iframe_document_view")
	 *
	 * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function frontViewAction($slug)
    {
		// Reset all session vars
		$this->killResourceNavigationType();
		$this->clearFilters();
		$this->clearSelection();
        $this->clearGroupContext();

        return $this->forward('BNSAppResourceBundle:Front:callIframe', array(
			'type'		    => 'resource_view',
			'resourceSlug'  => $slug
		));
    }

	/**
     * Page de téléchargement des fichiers des ressources de manière temporaire : public !
	 *
	 * @param $resource_slug slug de la ressource
	 * @param $key Clé de match
	 * @param $validity Timestamp pour vérifier la validité
	 *
	 * @Route("telecharger-temporaire/{resource_slug}/{validity}/{key}", name="resource_file_download_temporary")
     */
	public function downloadTempAction($resource_slug, $key, $validity)
	{
		$resource = ResourceQuery::create()->findOneBySlug($resource_slug);
		if (null == $resource) {
			return $this->redirectHome();
		}

		$rm = $this->get('bns.resource_manager');
		$rm->setObject($resource);

		if (!$rm->checkTempUrlKey($key, $validity)) {
			return new Response();
		}

		$response = $this->createResponseForDownload($resource);

		return $response;
	}

	/**
	 * @param Resource $resource
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function createResponseForDownload($resource, $size = "original")
	{
		$response = new Response();
		$rm = $this->get('bns.resource_manager');
		$nginx = $this->container->hasParameter('xsendfile_header_nginx');

		if (null != $resource && $resource->isActive()) {
			if (!$nginx) {
				$response->headers->set('X-Sendfile', $rm->getAbsoluteFilePath($size));
			}
			else {
				$response->headers->set('X-Accel-Redirect', '/protected/' . $rm->getAbsoluteFilePath($size));
			}

			$response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $resource->getFilename()));
			$response->headers->set('Content-Type', $resource->getFileMimeType());

			$response->setStatusCode(200);
		}
		else {
			if (!$nginx) {
				$response->headers->set('X-Sendfile', $rm->getDeletedImage($resource));
			}
			else {
				$response->headers->set('X-Accel-Redirect', '/protected/' . $rm->getDeletedImage($resource));
			}

			$response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', "document-supprime.png"));
			$response->headers->set('Content-Type', 'image/png');

			// $response->setStatusCode(404);
		}

		return $response;
	}

	/**
     * Page de téléchargement des fichiers des ressources
	 * Attention : avoir XsendFile d'installé
	 *
     * @Route("/telecharger/{resourceSlug}", name="BNSAppResourceBundle_download")
	 * @Route("/visualiser/{resourceSlug}/public/{hash}/{size}", name="resource_visualize_public_document", defaults={"isVisualize": true})
	 * @Route("/visualiser/{resourceSlug}/{resourceHash}", name="resource_visualize_document", defaults={"isVisualize": true})
     * @Route("/visualiser/{resourceSlug}/{resourceHash}/{size}", name="resource_visualize_document_with_size", defaults={"isVisualize": true})
     * @Route("/visualiser-document/{resourceSlug}/{hash}/{size}", name="resource_visualize_document_in_ent_with_size", defaults={"isVisualize": true})
     */
	public function downloadAction($resourceSlug, $resourceHash = null, $hash = null, $isVisualize = false, $size = 'original')
	{
		if (null != $hash) {
			$hash = explode('___', Crypt::decrypt(urldecode($hash), $this->container->getParameter('symfony_secret')));
			if (count($hash) < 3) {
				return $this->createResponseForDownload(null);
			}

			// Has expired ?
			list($resourceId, $resourceAuthorId, $expiresAt) = $hash;
			if ($expiresAt < time()) {
				return $this->createResponseForDownload(null);
			}
		}

		$rm = $this->get('bns.resource_manager');
		$resources = ResourceQuery::create('r')
			->joinWith('r.ResourceLinkUser rlu', \Criteria::LEFT_JOIN)
			->joinWith('rlu.ResourceLabelUser rlau', \Criteria::LEFT_JOIN)
			->joinWith('rlau.User u', \Criteria::LEFT_JOIN)
			->joinWith('r.ResourceLinkGroup rlg', \Criteria::LEFT_JOIN)
			->joinWith('rlg.ResourceLabelGroup rlag', \Criteria::LEFT_JOIN)
			->where('r.Slug = ?', $resourceSlug)
		->find();

		$resource = isset($resources[0]) ? $resources[0] : null;
		if (null != $resource) {
			$rim = $this->get('bns.right_manager');
			$rm->setObject($resource);

			if (null != $hash) {
				// Validate resource
				if ($resourceId != $resource->getId() || $resourceAuthorId != $resource->getUserId()) {
					return $this->createResponseForDownload(null);
				}
			}
			else {
                if (null != $resourceHash) {
                    // Validate ressource hash
                    $resourceHash = explode('___', Crypt::decrypt(urldecode($resourceHash), $this->container->getParameter('symfony_secret')));
                    if (count($resourceHash) < 3) {
                        return $this->createResponseForDownload(null);
                    }

                    list($resourceId, $resourceAuthorId, $slug) = $resourceHash;
                    if ($resourceId != $resource->getId() || $resourceAuthorId != $resource->getUserId() || $slug != $resourceSlug) {
                        return $this->createResponseForDownload(null);
                    }
                }

				// Vérification des droits
				$resourceRightManager = $this->get('bns.resource_right_manager');
				$resourceRightManager->setUser($rim->getUserSession());

				if (!$resourceRightManager->canReadResource($resource, $isVisualize)) {
					return $this->createResponseForDownload(null);
				}
			}
		}

		$response = $this->createResponseForDownload($resource,$size);

		// Add download count if download
		if (!$isVisualize && null != $resource && $resource->isActive()) {
			$resource->addDownloadCount();
			$resource->keepUpdateDateUnchanged();
			$resource->save();
		}

		return $response;
	}

	/**
    * Lien de téléchargement pour les anciens documents (V2) - Fallback
    * @Route("download/{old_id}", name="BNSAppResourceBundle_download_old2")
    * @Route("generateSecuredBlogDownloadLink/file_id/{old_id}", name="BNSAppResourceBundle_download_old")
    */
	public function downloadOldAction($old_id) {
		$migrationObject = MigrationQuery::create()
		    ->filterByEnvironment($this->container->getParameter('application_environment'))
		    ->filterByObjectClass('aws_file')
		    ->filterByOldId($old_id)
		->findOne();

		if ($migrationObject && $resource = ResourceQuery::create()->findOneById($migrationObject->getNewId())) {
			return $this->forward('BNSAppResourceBundle:Front:download',array('resource_slug' => $resource->getSlug(),'size' => 'original'));
		}

		throw $this->createNotFoundException();
	}

	/**
     * Barre de boutons du dessus
	 *
     * @Route("barre-outil", name="BNSAppResourceBundle_toolbar" , options={"expose"=true})
	 *
	 * @param String $page Page sur laquelle afficher la barre d'outils
	 *
	 * @RightsSomeWhere("RESOURCE_ACCESS")
	 *
	 * @Template()
     */
	public function toolbarAction($page)
	{
		$tools = array();
		$navType = $this->get('session')->get('resource_action_type');

		// Croix pour fermer ou non
		if (in_array($navType,array('join','insert','select_image'))) {
			$tools["close"] = true;
		}
		//Selon la page
		switch ($page) {
			case "home":
				$tools["add_resources"] = true;
				if ($navType == "select_image") {
					$tools["title"] = "Sélection d'une image";
				}
			break;
			case "add-choose":
				$currentLabel = $this->getCurrentLabelFromSession();
				$tools['current_label_id'] = $currentLabel ? $currentLabel->getId() : null;
				$tools['current_label_type'] = $currentLabel ? $currentLabel->getType() : null;
			case "add-files":
			case "add-urls":
				$tools["back_link"] = true;
			break;
		}

		return array(
			'tools' => $tools
		);
	}

	/**
    * XML de la white list pour un groupe donnée
    * @Route("white-list/{key}", name="BNSAppResourceBundle_white_list_xml" , options={"expose"=true})
	* @param String $key Clé unique identifiant
	* @Template()
    */
	public function whiteListXmlAction($key)
	{
		$reM = $this->get('bns.resource_manager');
		$group = GroupQuery::create()->filterBySingleAttribute("WHITE_LIST_UNIQUE_KEY",$key)->findOne();
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		if($gm->getAttribute('WHITE_LIST_USE_PARENT') == true){
			$parentWhiteList = unserialize($gm->getAttribute('WHITE_LIST'));
		}else{
			$parentWhiteList = array();
		}
		$links = $reM->getWhiteListObjects($group->getId());
		$response = new Response();
		$response = $this->render('BNSAppResourceBundle:Front:whiteListXml.html.twig', array('links' => $links,'parent_white_list' => $parentWhiteList));
		$response->headers->set('Content-Type', 'text/xml');
        return $response;
	}

    //TEMPORAIRE : accès direct UNIVERSALIS


    /**
     * Accès Universalis
     * @Route("universalis", name="BNSAppResourceBundle_universalis")
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function universalisAccessAction()
    {
        //$this->get('bns.right_manager')->forbidIf(!$this->container->hasParameter('has_universalis') || $this->container->getParameter('has_universalis') != true);
        $uai = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('UAI');

        $params = array(
            'rne'       => $uai,
            'sso_id'    => 8533,
            'dategen'   => date('U')
        );

        $signParams =  array($this->container->getParameter('universalis_secret_key'), $params['rne'], $params['sso_id'], $params['dategen']);
        $url = $this->buildUrl(array_merge(
            $params,
            array('sign' => $this->sign('md5', $signParams, ''))
        ));

        $this->get('logger')->debug('Client Universalis URL :' . $url, array('uai' => $uai, 'signParams' => $signParams));

        $browser = new Browser();
        $result = $browser->get($url);


        $resources = $this->handleResult($result);

        if($resources != false)
        {
            $rm = $this->get('bns.right_manager');
            $isTeacher = $rm->getUserManager()->getMainRole() == 'teacher';
            $roleString = $isTeacher ? 'ENSEIGNANT' : 'NONENSEIGNANT';
            return $this->redirect($resources .  '&profil=' . $roleString . '&uuid=' . $rm->getUserSession()->getLogin());
        }else{
            return $this->redirectHome();
        }
    }

    protected function handleResult($result)
    {
        $resources = array();
        if ($result->isOk()) {
            $document = new \DOMDocument();
            $document->loadXML($result->getContent());

            /** @var $resource \DOMElement */
            foreach ($document->getElementsByTagName('ressource') as $resource) {
                $response = $resource->getElementsByTagName('reponse')->item(0);
                if ($response && 'OK' === strtoupper($response->nodeValue)) {
                    $url = $resource->getElementsByTagName('url')->item(0);
                    $label = $resource->getElementsByTagName('libelle')->item(0);
                    $message = $resource->getElementsByTagName('message')->item(0);
                    return $url->nodeValue;
                }
            }
        }
        return false;
    }

    protected function buildUrl(array $data)
    {
        return "http://www.universalis-edu.com/nomade/ENT.php" . '?' . http_build_query($data);
    }

    protected function sign($method, $data, $separator = '.')
    {
        if (is_callable($method)) {
            return call_user_func($method, implode($separator, $data));
        }
    }

    //Fin temporaire acces UNIVERSALIS
}

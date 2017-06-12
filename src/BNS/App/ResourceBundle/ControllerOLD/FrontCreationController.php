<?php
namespace BNS\App\ResourceBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/creation")
 */
class FrontCreationController extends CommonController
{

	/**
    * Page de choix d'ajouts des ressources
    * @Route("/choix", name="BNSAppResourceBundle_add_choose")
	* @Template()
    */
    public function addChooseAction(Request $request)
    {
		if (!$request->isXmlHttpRequest() || !$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}

		return array(
			'insertDockBar' => true
		);
    }

	//////////////   METHODES LIEES A L'AJOUT D'URLS    \\\\\\\\\\\\\\\\\\\

	/**
    * Page d'e choix d'ajouts des ressources'ajout de ressource depuis une URL
    * @Route("/url", name="BNSAppResourceBundle_add_url")
	* @Template()
    */
    public function addUrlAction(Request $request)
    {
		if (!$request->isXmlHttpRequest() || !$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}
        
        //statistic action
        $this->get("stat.resource")->newFile();
		
        return array();
    }

	/**
	* Action d'ajout d'une Url à la liste d'Urls traitées en batch
    * @Route("/url/submit", name="BNSAppResourceBundle_add_url_submit", options={"expose"=true})
	* @Template()
    */
    public function addUrlSubmitAction(Request $request)
    {
		if (!$request->isXmlHttpRequest() || !$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}
		
		$datas = array();
		$urls = $this->getRequest()->get('url');
		$rc = $this->get('bns.resource_creator');
		$validUrls = 0;
		$unvalidUrls = 0;
		foreach($urls as $url){
			$value = $rc->initDatasFromUrl($url);
			if($value == false){
				$unvalidUrls++;
			}else{
				$validUrls++;
				$datas[] = $value;
			}
		}

		return array('datas' => $datas,'valid_urls' => $validUrls,'unvalid_urls' => $unvalidUrls);
    }


	/**
	* Création dans l'application des resources "Urls"
    * @Route("/creation/urls", name="BNSAppResourceBundle_create_urls")
	* @Template("BNSAppResourceBundle:FrontCreation:addUrl.html.twig")
    */
    public function createUrlsAction(Request $request)
    {
		if (!$request->isXmlHttpRequest() || !$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}
		
		$rm = $this->get('bns.resource_manager');
		$rc = $this->get('bns.resource_creator');

		$urls = $this->getRequest()->get('url');
		$titles = $this->getRequest()->get('title');
		$descriptions = $this->getRequest()->get('description');
		$types = $this->getRequest()->get('type');
		$destination = $this->getRequest()->get('destination');
		$this->setCurrentLabelIntoSession($this->getLabelFromPattern($destination));
		
		$errors = array();
		$success = array();

		foreach($urls as $key => $url){

			if(!isset($titles[$key]) || !isset($types[$key])){
				$errors[] = $key;
			}elseif(trim($titles[$key] == "")){
				$errors[] = $key;
			}else{
				//OK GO
				$datas = array();

				$datas['url'] = $url;
				$datas['title'] = $titles[$key];
				$datas['description'] = $descriptions[$key];
				$datas['destination'] = $destination;
				$datas['type'] = $types[$key];
				$rm->setUser($this->get('bns.right_manager')->getModelUser());
				$rc->createFromUrl($datas);
				$success[] = $key;
			}
		}
		return array('success' => true,'current_label' => $this->getCurrentLabelFromSession());
    }

	//////////////   METHODES LIEES A L'AJOUT DE FICHIERS     \\\\\\\\\\\\\\\\\\\

	/**
    * Page de choix d'ajouts des ressources
    * @Route("/fichiers", name="BNSAppResourceBundle_add_files")
	* @Template()
    */
    public function addFilesAction(Request $request)
    {
		if (!$request->isXmlHttpRequest() || !$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}
		
		//Groupes dans lesquelles je peux editer l'arborescence
		$manageable_groups = $this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ACCESS_BACK');

        //statistic action
        $this->get("stat.resource")->newFile();
        
		return array("manageable_groups" => $manageable_groups);
    }

	/**
    * Page de choix d'ajouts des ressources
    * @Route("/fichiers/submit", name="BNSAppResourceBundle_add_files_submit", options={"expose"=true})
	* @Template()
    */
    public function addFilesSubmitAction()
    {
		if (!$this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')) {
			return $this->redirectHome();
		}
		
		$resource_creator = $this->get('bns.resource_creator');
		$resource_creator->setUser($this->get('bns.right_manager')->getModelUser());

		$resource_creator->initFileUploader();

		switch ($this->getRequest()->getMethod()) {
            case 'HEAD':
            case 'GET':
                $resource_creator->get();
                break;
            case 'POST':
                if ($this->getRequest()->get('_method') === 'DELETE') {
					break;
                }
				
				// Vérification des droits
				$destination = $this->getRequest()->get('destination');
				$destination = $destination[0];
				$json = $resource_creator->createFromRequest($this->getLabelFromPattern($destination));
                break;
            default:
                header('HTTP/1.1 405 Method Not Allowed');
        }

        $response = new Response();
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Content-Disposition', 'inline; filename="files.json"');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'OPTIONS, HEAD, GET, POST, PUT, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'X-File-Name, X-File-Type, X-File-Size');
		$response->headers->set('Content',$json);
        return $response;
    }
}

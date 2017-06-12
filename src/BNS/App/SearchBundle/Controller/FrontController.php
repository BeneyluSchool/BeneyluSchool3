<?php

namespace BNS\App\SearchBundle\Controller;

use BNS\App\CoreBundle\Buzz\Browser;
use BNS\App\CoreBundle\Model\GroupQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

class FrontController extends Controller
{

    private function getSearchWhiteListUrl()
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();

        return $this->get('bns.search_manager')->getSearchWhiteListUrl($group);
    }

    /**
     * XML de la white list pour un groupe donnée
     * @Route("white-list/{key}", name="BNSAppResourceBundle_white_list_xml" , options={"expose"=true})
     * @param String $key Clé unique identifiant
     * @Template()
     */
    public function whiteListXmlAction($key)
    {
        $group = GroupQuery::create()->filterBySingleAttribute("WHITE_LIST_UNIQUE_KEY",$key)->findOne();
        $gm = $this->get('bns.group_manager');
        $gm->setGroup($group);
        if($gm->getAttribute('WHITE_LIST_USE_PARENT') == true){
            $parentWhiteList = unserialize($gm->getAttribute('WHITE_LIST'));
        }else{
            $parentWhiteList = array();
        }
        $links = $this->get('bns.search_manager')->getWhiteListObjects($group->getId());
        $response = new Response();
        $response = $this->render('BNSAppSearchBundle:Front:whiteListXml.html.twig', array('links' => $links,'parent_white_list' => $parentWhiteList));
        $response->headers->set('Content-Type', 'text/xml');
        return $response;
    }


    /**
	 * @Route("/", name="BNSAppSearchBundle_front_old")
	 * @Rights("SEARCH_ACCESS")
     * @Template()
	 */
	public function indexAction()
	{
        $this->get('stat.search')->visit();

        return array(
            'white_list_url' => $this->getSearchWhiteListUrl(),
            'has_medialandes' => $this->get('bns.right_manager')->hasMedialandes(true,true),
            'hasUai' => $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('UAI') != false
        );
	}

    /**
     * @Route("/ajout-recherche", name="BNSAppSearchBundle_front_add_search", options={"expose"=true})
     * @Rights("SEARCH_ACCESS")
     */
    public function addSearchAction(Request $request)
    {
        $term = $request->get('q');
        $this->get('bns.search_manager')->addSearch($term, $this->get('bns.right_manager')->getUserSession());
        return new Response('ok');
    }

    /**
     * Accès Universalis
     * @Route("universalis", name="BNSAppSearchBundle_universalis", options={"expose"=true})
     */
    public function universalisAccessAction()
    {
        $libelle = $this->getRequest()->get('libelle');
        if($libelle == null)
        {
            $libelle = "Universalis Junior";
        }

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
        $resources = $this->handleResult($result,$libelle);

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

    protected function handleResult($result, $offer = null)
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
                    if($offer != null && $label->nodeValue == $offer)
                    {
                        return $url->nodeValue;
                    }
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

    /**
     * Redirect the user to homepage if direct http access, or return empty response
     *
     * @return Response
     */
    protected function redirectHome()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppSearchBundle_front'));
    }

    //Fin temporaire acces UNIVERSALIS
}


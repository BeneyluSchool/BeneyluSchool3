<?php

namespace BNS\App\SearchBundle\Controller;


use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\SearchBundle\Model\SearchInternetQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/gestion")
 */

class BackController extends Controller
{

    /**
     * @Route("/", name="BNSAppSearchBundle_back_old", options={"expose"=true})
     * @Rights("SEARCH_ACCESS_BACK")
     * @Template()
     */
    public function indexAction()
    {
        $this->get('stat.search')->visit();

        return array(
            'lastSearchs' => SearchInternetQuery::create()
                ->joinUser()
                ->filterByUserId($this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueNameIds('PUPIL'))
                ->orderByCreatedAt(\Criteria::DESC)
                ->limit(20)
                ->find(),
            'page' => 'index',
            'section' => 'index'
        );
    }

    /**
     * Page de gestion de la liste blanche
     * @Route("/liste-blanche", name="BNSAppSearchBundle_back_white_list")
     * @Template()
     * @Rights("SEARCH_ACCESS_BACK")
     */
    public function whiteListAction()
    {
        $rm = $this->get('bns.right_manager');
        $group = $rm->getCurrentGroup();
        $links = $this->get('bns.search_manager')->getLinks($group);
        $whiteList = $this->get('bns.search_manager')->getWhiteList($group->getId());
        return array(
            'links'			   => $links,
            'whiteList'		   => $whiteList
        );
    }

    /**
     * Activation / désactivation des items de la whiteList
     * @Route("/liste-blanche/lien", name="BNSAppSearchBundle_back_white_list_toggle", options={"expose"=true}))
     * @Template("BNSAppSearchBundle:Back:whiteListBlock.html.twig")
     * @Rights("SEARCH_ACCESS_BACK")
     */
    public function whiteListToggleAction(Request $request)
    {
        $sm = $this->get('bns.search_manager');
        $riM = $this->get('bns.right_manager');
        $contextId = $riM->getCurrentGroupId();

        $link = MediaQuery::create()->findOneById($request->get('media_id'));
        if($this->get('bns.media_library_right.manager')->canReadMedia($link))
        {
            $status = $sm->toggleWhiteList($link->getId(),$contextId);
            return array('link' => $link,'status' => $status);
        }else{
            throw new AccessDeniedException();
        }
    }

    /**
     * Page de visualisation et d'activation de la white liste générale
     * @Route("/liste-blanche-generale", name="BNSAppSearchBundle_back_white_list_general")
     * @Template()
     * @Rights("SEARCH_ACCESS_BACK")
     */
    public function whiteListGeneralAction()
    {
        $whiteListGeneral = unserialize($this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST'));
        $whiteListUse = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST_USE_PARENT');
        return array(
            'white_list_general' => $whiteListGeneral,
            'white_list_use' => $whiteListUse,
            'can_administrate' => $this->get('bns.right_manager')->hasRight('SEARCH_ACCESS_BACK')
        );
    }

    /**
     * Toggle de l'utilisation de la white liste générale
     * @Route("/liste-blanche-generale-changement", name="BNSAppSearchBundle_back_white_list_general_toggle" , options={"expose"=true})
     * @Rights("SEARCH_ACCESS_BACK")
     */
    public function customWhiteListGeneralToggleAction()
    {
        $whiteListGeneral = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST_USE_PARENT');
        $whiteListGeneral = $whiteListGeneral == true ? 0 : 1;
        $this->get('bns.right_manager')->getCurrentGroupManager()->setAttribute('WHITE_LIST_USE_PARENT',$whiteListGeneral);
        //Mise à jour de la clé pour la cache Google
        $this->get('bns.search_manager')->updateUniqueKey($this->get('bns.right_manager')->getCurrentGroupId());
        return new Response();
    }

    /**
     * @Route("/liste-blanche/export", name="BNSAppSearchBundle_back_white_list_export")
     * @Rights("SEARCH_ACCESS_BACK")
     */
    public function exportWhiteList()
    {
        $params = $this->whiteListAction();
        if (!$params['can_administrate']) {
            return $this->redirect($this->generateUrl('BNSAppSearchBundle_back'));
        }
        $params['now'] = time();
        $response = $this->render('BNSAppSearchBundle:Back:export_white_list.html.twig', $params);
        $response->headers->set('Content-Disposition', 'attachment; filename="favoris_' . date('d-m-Y', time()) . '.html"');
        return $response;
    }

}

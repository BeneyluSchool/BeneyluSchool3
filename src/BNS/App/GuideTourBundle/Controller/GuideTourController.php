<?php

namespace BNS\App\GuideTourBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Model\UserGuideTour;
use BNS\App\CoreBundle\Model\UserGuideTourQuery;
use BNS\App\CoreBundle\Model\UserGuideTourPeer;

class GuideTourController extends Controller
{
    public function displayGuideTourAction($currentRoute)
    {
		$filePath = '';
		if ($this->get('bns.right_manager')->isAuthenticated()) {
			$request = $this->getRequest();
			// On vérifie s'il existe ou non une entrée dans le tableau en session
			$routesToDontDisplay = $this->getRouteToNeverDisplayList();

			// si $userGuideTour ne vaut pas null alors cela veut dire que l'utilisateur souhaite ne plus afficher de guide pour cette page
			if (!in_array($currentRoute, $routesToDontDisplay)) {
				$type = $this->getUser()->getHighRoleId() == $this->get('bns.role_manager')->findGroupTypeRoleByType('PUPIL')->getId()? '_pupil' : '';
				$fileRelativePath = '/medias/js/guide_tour/' . $request->getLocale() . '/' . $currentRoute . $type . '.js';
				$filePath = file_exists($this->get('kernel')->getRootDir() . '/../web' . $fileRelativePath) ? $fileRelativePath : '';
			}
		}

		return $this->render('BNSAppGuideTourBundle:GuideTour:render_guide_tour.html.twig', array(
			'current_route'	=> $currentRoute,
			'file_path'		=> $filePath
		));
    }
	
	/**
	 * @Route("/sauvegarder-ne-plus-afficher/{currentRoute}", name="save_never_display_guide_tour", options={"expose"=true})
	 */
	public function saveNeverDisplayAction($currentRoute)
	{
		if (null == UserGuideTourQuery::create()
				->add(UserGuideTourPeer::USER_ID, $this->getUser()->getId())
				->add(UserGuideTourPeer::ROUTE, $currentRoute)
			->findOne()
			)
		{
			$userGuideTour = new UserGuideTour();
			$userGuideTour->setUser($this->getUser());
			$userGuideTour->setRoute($currentRoute);			
			
			//Finally
			$userGuideTour->save();
		}
		
		$this->addRouteToNeverDisplayList($currentRoute);
		
		return new Response();
	}
	
	private function getRouteToNeverDisplayList()
	{
		$session = $this->get('session');
		$routes = $session->get('bns.guide_tour_never_display_route_list', null);
		if (null == $routes)
		{
			$routes = array();
			foreach (UserGuideTourQuery::create()->add(UserGuideTourPeer::USER_ID, $this->getUser()->getId())->find() as $userGuideTour) {
				$routes[] = $userGuideTour->getRoute();
			}
			
			$session->set('bns.guide_tour_never_display_route_list', $routes);
		}
		
		return $session->get('bns.guide_tour_never_display_route_list');
	}
	
	private function addRouteToNeverDisplayList($route)
	{
		$routes = $this->getRouteToNeverDisplayList();
		$routes[] = $route;
		
		// Finally
		$this->get('session')->set('bns.guide_tour_never_display_route_list', $routes);
	}
}

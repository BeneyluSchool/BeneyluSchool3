<?php

namespace BNS\App\ResourceBundle\Controller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontToolBarController extends CommonController
{
	/**
	 * @param boolean $insertCross
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function showAction($canBeClosed = false)
	{
		// Croix pour fermer ou non
		if (in_array($this->getResourceNavigationType(), array('join', 'insert', 'select_image'))) {
			$canBeClosed = true;
		}

		return $this->render('BNSAppResourceBundle:ToolBar:front_toolbar_layout.html.twig', array(
			'canBeClosed'     => $canBeClosed,
			'navigationType'  => $this->getResourceNavigationType(),
			'reference'	      => $this->getCallBackReference(),
			// TODO supprimer le fallback sur attributs image, quand tous les calls mediatheque passeront par select_file
			'select_img_id'   => $this->get('session')->get('resource_select_file_final_id', $this->get('session')->get('resource_select_image_final_id')),
			'select_callback' => $this->get('session')->get('resource_select_file_callback', $this->get('session')->get('resource_select_image_callback')),
			'canAddResource'  => $this->get('bns.right_manager')->hasRightSomeWhere('RESOURCE_MY_RESOURCES')
		));
	}
}

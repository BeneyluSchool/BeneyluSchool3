<?php
namespace BNS\App\GPSBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/")
 */

class FrontController extends Controller
{	
	/**
	 * @Route("", name="BNSAppGpsBundle_front")
	 * @Template()
	 */
	public function indexAction()
	{		
		//Récupération du groupe en cours
		$rm = $this->get('bns.right_manager');
		$group = $rm->getCurrentGroup();
		$group_id = $group->getId();
		$groupManager = $this->get('bns.group_manager');
		$groupManager->setGroup($group);
		
		//Recupération des catégories
		$map = $this->get('bns.front_map')->initialize($group_id);
		$categories = $map['categories'];
		$group = $map['group'];
		
		return array(
			'categories' => $categories,
			'group' => $group
		);
	}
}


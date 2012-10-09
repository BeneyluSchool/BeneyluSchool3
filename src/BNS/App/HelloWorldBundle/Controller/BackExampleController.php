<?php

namespace BNS\App\HelloWorldBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * @Route("/gestion/exemples-administration")
 */
class BackExampleController extends Controller
{
	private static $currentId = 0;
	
	public function getItemsAction()
	{
		$items = array(
			array(
				'id'			=> self::$currentId++,
				'title'			=> "Ceci est un test",
				'hasCategories'	=> true,
				'date'			=> time() - 3600,
				'author'		=> array('fullname' => 'Sylvain Lorinet'),
				'type'			=> 'orange',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			),
			array(
				'id'			=> self::$currentId++,
				'title'			=> "Ceci est un test avec catégories",
				'hasCategories'	=> true,
				'date'			=> time() - 9687,
				'author'		=> array('fullname' => 'Michel Bob'),
				'type'			=> 'orange',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			),
			array(
				'id'			=> self::$currentId++,
				'title'			=> "Voici un title qui s'avère est un très long titre sur à peu près deux lignes",
				'hasCategories'	=> true,
				'date'			=> time() - 35445,
				'author'		=> array('fullname' => 'Eric Tiède'),
				'type'			=> 'green',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			),
			array(
				'id'			=> self::$currentId++,
				'title'			=> "Et un item sans catégorie",
				'hasCategories'	=> false,
				'date'			=> time() - 58752,
				'author'		=> array('fullname' => 'Jean Bonbeurre'),
				'type'			=> 'blue',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			),
			array(
				'id'			=> self::$currentId++,
				'title'			=> "Pour finir un article avec un titre relativement long et à nouveau sans catégorie",
				'hasCategories'	=> false,
				'date'			=> time() - 94140,
				'author'		=> array('fullname' => 'Batman Hogan'),
				'type'			=> 'green',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			)
		);
		
		return $this->render('BNSAppHelloWorldBundle:Item:back_item_list.html.twig', array(
			'items'			=> $items,
			'isAjaxCall'	=> $this->getRequest()->isXmlHttpRequest()
		));
	}
	
	/**
	 * @Route("/exemple-slug-item", name="hello_world_manager_administration_see")
	 */
	public function seeAction()
	{
		return $this->render('BNSAppHelloWorldBundle:Item:back_item_visualisation.html.twig', array(
			'item'	=> array(
				'id'			=> self::$currentId++,
				'title'			=> "Ceci est un test avec catégories",
				'hasCategories'	=> true,
				'date'			=> time() - 9687,
				'author'		=> array('fullname' => 'Michel Bob'),
				'type'			=> 'orange',
				'description'	=> 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper...'
			)
		));
	}
}
<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogPeer;


/**
 * Skeleton subclass for performing query and update operations on the 'blog' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogPeer extends BaseBlogPeer
{
	/**
	 * @param array $params
	 */
	public static function create(array $params)
	{
		// Création du blog
		$blog = new Blog();
		$blog->setGroupId($params['group_id']);
		$blog->setTitle('Blog : ' . $params['label']);
		$blog->save();
		
		/* TODO icons Pas de catégories par défaut
		$categoryData = array(
			array(
				'title'				=> 'La Classe',
				'icon_classname'	=> 'default'
			),
			array(
				'title'				=> 'Les Devoirs',
				'icon_classname'	=> 'default'
			),
			array(
				'title'				=> 'Les Sorties',
				'icon_classname'	=> 'default'
			),
			array(
				'title'				=> 'Les Récréations',
				'icon_classname'	=> 'default'
			),
			array(
				'title'				=> 'Le Déjeuner',
				'icon_classname'	=> 'default'
			)
		);
		*/
		// Fake root for nested level
		$rootCategory = new BlogCategory();
		$rootCategory->setBlogId($blog->getId());
		$rootCategory->setTitle('Root Category' . $blog->getId());
		$rootCategory->makeRoot();
		$rootCategory->save();
		/*
		foreach ($categoryData as $data) {
			$category = new BlogCategory();
			$category->setBlogId($blog->getId());
			$category->setTitle($data['title']);
			$category->setIconClassname($data['icon_classname']);
			$category->insertAsLastChildOf($rootCategory);
			$category->save();
			
			$category = null;
		}*/
	}
}
<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogCategory;


/**
 * Skeleton subclass for representing a row from the 'blog_category' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogCategory extends BaseBlogCategory
{
	public function getChildrenFromCollection($blogCategories, $override = false)
	{
		if (!isset($this->collNestedSetChildren) || $override) {
			$this->collNestedSetChildren = array();
			
			// Récupération
			foreach ($blogCategories as $category) {
				if ($category->getBlogId() == $this->getBlogId() &&
					$category->getLeft() > $this->getLeft() &&
					$category->getLeft() < $this->getRight() &&
					$category->getLevel() == $this->getLevel() + 1)
				{
					$this->collNestedSetChildren[] = $category;
				}
			}
		}
		
		return $this->collNestedSetChildren;
	}
	
	/**
	 * @return boolean True if category has icon
	 */
	public function hasIcon()
	{
		$iconClassName = $this->getIconClassname();
		
		return null != $iconClassName && 'default' != $iconClassName;
	}
}
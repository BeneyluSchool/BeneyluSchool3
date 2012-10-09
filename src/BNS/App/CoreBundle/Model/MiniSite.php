<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSite;
use BNS\Central\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'mini_site' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSite extends BaseMiniSite
{
	const PERMISSION_ACCESS			= 'MINISITE_ACCESS';
	const PERMISSION_ACCESS_BACK	= 'MINISITE_ACCESS_BACK';
	const PERMISSION_ADMINISTRATION	= 'MINISITE_ADMINISTRATION';
	
	/**
	 * @param string $slug 
	 * 
	 * @return MiniSitePage|boolean False if not found
	 */
	public function findPageBySlug($slug)
	{
		return $this->findPageBy('slug', $slug);
	}
	
	/**
	 * @param int $id 
	 * 
	 * @return MiniSitePage|boolean False if not found
	 */
	public function findPageById($id)
	{
		return $this->findPageBy('id', $id);
	}
	
	/**
	 * @return MiniSitePage 
	 */
	public function getHomePage()
	{
		return $this->findPageBy('isHome', true);
	}
	
	/**
	 * @param string $columnName
	 * @param mixed $object
	 * 
	 * @return MiniSitePage|boolean 
	 */
	private function findPageBy($columnName, $object)
	{
		$pages = $this->getMiniSitePages();
		$methodName = 'get' . ucfirst($columnName);
		
		foreach ($pages as $page) {
			if ($page->$methodName() == $object) {
				return $page;
			}
		}
		
		return false;
	}
	
	/**
	 * @param string $slug
	 * @param MiniSitePage $pageToReplace
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function replaceMiniSitePage($slug, MiniSitePage $pageToReplace)
	{
		if (!isset($this->collMiniSitePages)) {
			$this->getMiniSitePages();
		}
		
		$foundKey = null;
		foreach ($this->collMiniSitePages as $i => $page) {
			if ($page->getSlug() == $slug) {
				$foundKey = $i;
				break;
			}
		}
		
		if (null == $foundKey) {
			throw new \InvalidArgumentException('The page with slug : ' . $slug . ' does NOT exist !');
		}
		
		$this->collMiniSitePages[$foundKey] = $pageToReplace;
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isPublic()
	{
		return $this->getIsPublic();
	}
	
	/**
	 * Switch public state
	 */
	public function switchPublic()
	{
		if ($this->isPublic()) {
			$this->setIsPublic(false);
		}
		else {
			$this->setIsPublic(true);
		}
	}

	/**
	 * @return string 
	 */
	public function __toString()
	{
		return '#' . $this->getId() . ' - ' . $this->getTitle();
	}
}
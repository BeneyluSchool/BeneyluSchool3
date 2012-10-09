<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSitePage;

/**
 * Skeleton subclass for representing a row from the 'mini_site_page' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSitePage extends BaseMiniSitePage
{
	/**
	 * @param PropelObjectCollection $miniSitePageNews
	 */
	public function replaceMiniSitePageNews($miniSitePageNews)
	{
		$this->collMiniSitePageNewss = $miniSitePageNews;
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isActivated()
	{
		return $this->getIsActivated();
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean
	 */
	public function isHome()
	{
		return $this->getIsHome();
	}
	
	/**
	 * Disable the page if activated and enable the page is disabled
	 */
	public function switchActivation()
	{
		if ($this->isActivated()) {
			$this->setIsActivated(false);
		}
		else {
			$this->setIsActivated(true);
		}
	}
}
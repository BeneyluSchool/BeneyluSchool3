<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSiteWidget;


/**
 * Skeleton subclass for representing a row from the 'mini_site_widget' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSiteWidget extends BaseMiniSiteWidget
{
	/**
	 * @param \BNS\App\CoreBundle\Model\PropelObjectCollection $extraProperties
	 */
	public function replaceMiniSiteWidgetExtraPropertys(\PropelObjectCollection $extraProperties)
	{
		$this->collMiniSiteWidgetExtraPropertys = $extraProperties;
	}
	
	/**
	 * @return string
	 */
	public function __toString()
	{
		return '#' . $this->getId() . ' ' . $this->getTitle();
	}
}
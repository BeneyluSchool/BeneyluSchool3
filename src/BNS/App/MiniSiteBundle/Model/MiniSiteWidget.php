<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\MiniSiteBundle\Model\om\BaseMiniSiteWidget;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSiteWidget extends BaseMiniSiteWidget
{
	/**
	 * @param \BNS\App\MiniSiteBundle\Model\PropelObjectCollection $extraProperties
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

    public function getType()
    {
        if ($this->getWidgetTemplateId()) {
            return $this->getMiniSiteWidgetTemplate()->getType();
        }

        return null;
    }

    public function getPropertyValue()
    {
        if ($this->getType()=="TIME"){
            return "Time";
        }
        if ($this->getMiniSiteWidgetExtraProperties()) {
            return $this->getMiniSiteWidgetExtraProperties();
        }

        return null;
    }
}

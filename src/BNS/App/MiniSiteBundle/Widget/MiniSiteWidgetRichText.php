<?php

namespace BNS\App\MiniSiteBundle\Widget;

use BNS\App\MiniSiteBundle\Widget\Core\MiniSiteWidgetCore;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSiteWidgetRichText extends MiniSiteWidgetCore
{
	protected function __configure()
	{
		$this->properties = array(
			'content' => 'textarea'
		);
	}
	
	/**
	 * @param string $content
	 */
	public function setContent($content)
	{
		$this->setProperty('CONTENT', $content);
	}
	
	/**
	 * @return string
	 */
	public function getContent()
	{
		return $this->getProperty('CONTENT');
	}
}
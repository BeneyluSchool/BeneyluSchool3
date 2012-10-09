<?php

namespace BNS\App\MiniSiteBundle\Widget;

use BNS\App\MiniSiteBundle\Widget\Core\MiniSiteWidgetCore;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSiteWidgetRss extends MiniSiteWidgetCore
{
	protected function __configure()
	{
		$this->properties = array(
			'RSS_URL'	=> 'text',
			'LIMIT'		=> array(
				'input'		=> 'choice',
				'options'	=> array('choices' => array(
					1	=> '1', 2	=> '2',
					3	=> '3', 4	=> '4',
					5	=> '5', 10	=> '10'
			)))
		);
	}
	
	/**
	 * @param string $url
	 */
	public function setRssUrl($url)
	{
		if (!preg_match('#http://#', $url)) {
			$url = 'http://' . $url;
		}
		
		$this->setProperty('RSS_URL', $url);
	}
	
	/**
	 * @return string
	 */
	public function getRssUrl()
	{
		return $this->getProperty('RSS_URL');
	}
	
	/**
	 * @return string
	 */
	public function setLimit($limit)
	{
		return $this->setProperty('LIMIT', $limit);
	}
	
	/**
	 * @return int 
	 */
	public function getLimit()
	{
		return $this->getProperty('LIMIT');
	}
}
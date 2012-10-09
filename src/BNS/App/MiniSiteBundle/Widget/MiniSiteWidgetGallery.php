<?php

namespace BNS\App\MiniSiteBundle\Widget;

use BNS\App\MiniSiteBundle\Widget\Core\MiniSiteWidgetCore;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSiteWidgetGallery extends MiniSiteWidgetCore
{
	private $imageResourceIds;
	
	protected function __configure()
	{
		$this->properties = array(
			'IMAGE_RESOURCE_IDS'	=> 'hidden'
		);
	}
	
	/**
	 * @return array<Integer> 
	 */
	public function getImageResourceIds()
	{
		if (!isset($this->imageResourceIds)) {
			$this->imageResourceIds = $this->getProperty('IMAGE_RESOURCE_IDS', array());
			if (!is_array($this->imageResourceIds)) {
				$this->imageResourceIds = str_split(',', $this->imageResourceIds);
			}
		}
		
		return $this->imageResourceIds;
	}
	
	/**
	 * Retreive the resource object
	 * 
	 * @return null TODO
	 */
	public function getImageResource()
	{
		// TODO
	}
	
	/**
	 * @param id $resourceId
	 */
	public function addImageResourceIds($resourceId)
	{
		if (in_array($resourceId, $this->getImageResourceIds())) {
			return;
		}
		
		$this->imageResourceIds[] = $resourceId;
		$this->setProperty('IMAGE_RESOURCE_IDS', implode(',', $this->imageResourceIds));
	}
}
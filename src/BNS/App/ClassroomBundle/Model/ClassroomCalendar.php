<?php

namespace BNS\App\ClassroomBundle\Model;

use BNS\App\ClassroomBundle\Model\om\BaseClassroomNewspaper;

class ClassroomCalendar extends BaseClassroomNewspaper
{

	public function read()
	{
		$this->setViews($this->getViews() + 1);
		$this->save();
	}

	public function getLast()
	{
		if(!isset($this->last))
		{
			$last = ClassroomNewspaperQuery::create()->filterByIsCalendar(null, \Criteria::ISNULL)->findOneByDate(date('Y-m-d',$this->getDate('U') - 3600 * 24));
			$this->last = $last;
		}
		return $this->last;
	}

	public function getMediaUrl()
	{
		$media = $this->getMediaRelatedByMediaId();

		if($media->getTypeUniqueName() == 'DOCUMENT' && $media != null)
			return $media->getDownloadUrl();
		elseif($media->getTypeUniqueName() == 'EMBEDDED_VIDEO' && $media != null)
		{

			$value = unserialize($media->getValue());
			$type = $value['type'];
			$id = $value['value'];

			return $media->getValueFromVideoId($type, $id);
		}
	}

	public function getMediaPreviewUrl()
	{
		if($this->getMediaRelatedByMediaPreviewId())
			return $this->getMediaRelatedByMediaPreviewId()->getDownloadUrl();
		return false;
	}

}

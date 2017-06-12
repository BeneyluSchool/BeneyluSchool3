<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\HomeworkBundle\Model\om\BaseHomework;

/**
 *
 * @author brian.clozel@atos.net
 */
class Homework extends BaseHomework
{

	use RichTextParser;

	public function getRichDescription()
	{
		return $this->parse($this->getDescription());
	}

	/**
	 * Pour utilisation dans les slugs
	 */
	public function getShortDate()
	{
		return $this->getDate('d-m-Y');
	}

	/**
	 * Renvoie un tableau d'ids pour les queries ou les tests
	 * @return array
	 */
	public function getGroupsIds()
	{
		$ids = array();
		foreach($this->getGroups() as $group){
			$ids[] = $group->getId();
		}
		return $ids;
	}

	public function getResourceAttachments()
	{
		if ($this->isNew() && isset($this->attachments)) {
			return $this->attachments;
		} else {
			return parent::getResourceAttachments();
		}
	}

	/**
	 * Gets the name of the related HomeworkSubject, if any
	 *
	 * @return null|string
	 */
	public function getSubjectName()
	{
		if ($this->getSubjectId()) {
			return $this->getHomeworkSubject()->getName();
		}

		return null;
	}

	public function getShortDescription()
	{
		return StringUtil::substrws($this->getDescription());
	}

}
// Homework

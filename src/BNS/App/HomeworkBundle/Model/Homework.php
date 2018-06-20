<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\HomeworkBundle\Model\om\BaseHomework;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    public function getUserIds()
    {
        $users = $this->getUsers();

        if ($users) {
            return $users->getPrimaryKeys(false);
        }

        return [];
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

	public function validatePublicationDate(ExecutionContextInterface $context)
    {
        if ($this->getScheduledPublication()) {
            if (!$this->getPublicationDate()) {
                $context->buildViolation('INVALID_HOMEWORK_REQUIRE_PUBLICATION_DATE')
                    ->atPath('publicationDate')
                    ->addViolation();

                return;
            }

            if (!($this->getPublicationDate() <= $this->getDate())) {
                $context->buildViolation('INVALID_HOMEWORK_PUBLICATION_DATE_BEFORE')
                    ->atPath('publicationDate')
                    ->addViolation();
            }
        }
    }

    public function getStatus()
    {
        if ($this->getScheduledPublication()) {
            $now = new \DateTime();

            return $this->getPublicationDate() > $now ? 'SCHED' : 'PUB';
        }

        return 'PUB';
    }

    public function createSlug()
    {
        if (!$this->isNew()) {
            $key = $this->getId();
        } else {
            $key = 'key-' . rand(999999999, min(9999999999, PHP_INT_MAX));
        }

        return 'homework-' . $key;
    }

}
// Homework

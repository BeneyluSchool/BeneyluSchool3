<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileFeed;


/**
 * Skeleton subclass for representing a row from the 'profile_feed' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileFeed extends BaseProfileFeed
{
	const TYPE_STATUS		= 'status';
	const TYPE_RESOURCE		= 'resource';

	const PROFILE_FEED_LIMIT = 5;

	/**
	 * @return string Le type de la publication (constante TYPE_*)
	 *
	 * @throws \RuntimeException Si aucun type n'est trouvé ou si celui-ci n'est pas spécifié dans le code
	 */
	public function getType()
	{
		if (isset($this->singleProfileFeedStatus))
			return self::TYPE_STATUS;
		else if (isset($this->singleProfileFeedResource))
			return self::TYPE_RESOURCE;

		throw new \RuntimeException('Unknown feed type ! Please, specify the feed type in the ProfileFeed::getType() method.');

		return null;
	}

	/**
	 * Simple shortcut
	 *
	 * @return ProfileFeedResource
	 */
	public function getResource()
	{
		return $this->getProfileFeedResource();
	}

	/**
	 * @return User L'auteur de la publication
	 */
	public function getAuthor()
	{
		return $this->getProfile()->getUser();
	}

    /**
     * @return int
     */
    public function getAuthorId()
    {
        return $this->getProfile()->getUserId();
    }

    /**
	 * Inverse the comments order
	 *
	 * @param type $limit
	 *
	 * @return array<ProfileComment>
	 */
	public function getProfileCommentsInverse($limit = null)
	{
		$comments = array();
		$coms = $this->getProfileComments();
		$count = count($coms);
		$max = null != $limit ? $limit > $count ? $count : $limit : $count;

		for ($i=$max-1; $i>=0; $i--) {
			$comments[] = $coms[$i];
		}

		return $comments;
	}

	/**
	 * @return boolean
	 */
	public function isPendingValidation()
	{
		return $this->getStatus() == ProfileFeedPeer::STATUS_PENDING_VALIDATION;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getProfile()->getUser()->getFullName();
	}

    /**
     * Pour l'instant utilisé pour les status de nouvelle année
     */
    public function togglePublication()
    {
        $this->setPublishable(!$this->getPublishable());
        $this->save();
    }
}

<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileFeedStatus;
use PropelPDO;


/**
 * Skeleton subclass for representing a row from the 'profile_feed_status' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileFeedStatus extends BaseProfileFeedStatus
{
	/**
	 * @var ProfileFeed 
	 */
	private $feed;
	
	public function __construct()
	{
		$this->feed = new ProfileFeed();
	}
	
	/**
	 * @return ProfileFeed 
	 */
	public function getFeed()
	{
		return $this->feed;
	}
	
	/**
	 * @param PropelPDO $con 
	 */
	public function save(PropelPDO $con = null)
	{
		// On récupère la clé primaire (l'id de la publication) à l'enregistrement si elle n'a pas déjà été settée
		if ($this->feed->isNew() && null == $this->getFeedId())
		{
			$this->feed->save();
			$this->setFeedId($this->feed->getId());
		}
		
		parent::save($con);
	}
}

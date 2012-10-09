<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSitePageNews;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'mini_site_page_news' table.

 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSitePageNews extends BaseMiniSitePageNews implements \BNS\App\AutosaveBundle\Autosave\AutosaveInterface
{
	/**
	 * @var array<String, array<Integer>> ALLOWED_NEW_STATUSES Si le numéro du statut se trouve dans le tableau,
	 * alors la news est autorisée à changer de statut.
	 * 
	 * 0: DRAFT
	 * 1: PUBLISHED
	 * 2: FINISHED
	 * 3: WAITING_FOR_CORRECTION
	 */
	public static $ALLOWED_NEW_STATUSES = array(
		MiniSitePageNewsPeer::STATUS_DRAFT => array(
			1, 2, 3
		),
		MiniSitePageNewsPeer::STATUS_FINISHED => array(
			1, 3
		),
		MiniSitePageNewsPeer::STATUS_PUBLISHED => array(
			2
		),
		MiniSitePageNewsPeer::STATUS_WAITING_FOR_CORRECTION => array(
			1, 2
		)
	);
	
	
	/**
	 * Simple shortcut
	 * 
	 * @return User The news author
	 */
	public function getAuthor()
	{
		return $this->getUser();
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\User $user
	 */
	public function setAuthor(User $user)
	{
		$this->setUser($user);
	}
	
	/**
	 * @return boolean
	 */
	public function isPublished()
	{
		return $this->getStatus() == MiniSitePageNewsPeer::STATUS_PUBLISHED && $this->getPublishedAt()->getTimestamp() <= time();
	}
	
	/**
	 * @return boolean 
	 */
	public function isDraft()
	{
		return $this->getStatus() == MiniSitePageNewsPeer::STATUS_DRAFT;
	}
	
	/**
	 * @return boolean 
	 */
	public function isFinished()
	{
		return $this->getStatus() == MiniSitePageNewsPeer::STATUS_FINISHED;
	}
	
	/**
	 * @return boolean 
	 */
	public function isWaitingForCorrection()
	{
		return $this->getStatus() == MiniSitePageNewsPeer::STATUS_WAITING_FOR_CORRECTION;
	}
	
	public function autosave(array $objects)
	{
		// Check rights
		if (!BNSAccess::getContainer()->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ACCESS_BACK)) {
			throw new AccessDeniedHttpException('You can not access to this page');
		}
		
		// New object : save into database and return his new primary key
		if ($this->isNew()) {
			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setAuthor(BNSAccess::getUser());
			$this->setPageId($objects['page_id']);
			$this->setStatus(MiniSitePageNewsPeer::STATUS_DRAFT);
			$this->save();
		}
		else {
			// Check rights
			if ($this->isPublished() && !BNSAccess::getContainer()->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ADMINISTRATION)) {
				throw new AccessDeniedHttpException('You can not access to this page');
			}
			
			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setStatus(MiniSitePageNewsPeer::STATUS_DRAFT);
			$this->save();
		}
		
		return $this->getPrimaryKey();
	}
}
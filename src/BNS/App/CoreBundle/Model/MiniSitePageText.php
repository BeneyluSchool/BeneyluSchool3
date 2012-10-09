<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSitePageText;
use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'mini_site_page_text' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSitePageText extends BaseMiniSitePageText implements AutosaveInterface
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
		MiniSitePageTextPeer::STATUS_DRAFT => array(
			1, 2, 3
		),
		MiniSitePageTextPeer::STATUS_FINISHED => array(
			1, 3
		),
		MiniSitePageTextPeer::STATUS_PUBLISHED => array(
			2
		),
		MiniSitePageTextPeer::STATUS_WAITING_FOR_CORRECTION => array(
			1, 2
		)
	);
	
	/**
	 * Simple shortcut
	 * 
	 * @return User
	 */
	public function getAuthor()
	{
		return $this->getUser();
	}
	
	/**
	 * @return boolean
	 */
	public function isPublished()
	{
		return $this->getStatus() == MiniSitePageTextPeer::STATUS_PUBLISHED && $this->getPublishedAt()->getTimestamp() <= time();
	}
	
	/**
	 * @return boolean 
	 */
	public function isDraft()
	{
		return $this->getStatus() == MiniSitePageTextPeer::STATUS_DRAFT;
	}
	
	/**
	 * @return boolean 
	 */
	public function isFinished()
	{
		return $this->getStatus() == MiniSitePageTextPeer::STATUS_FINISHED;
	}
	
	/**
	 * @return boolean 
	 */
	public function isWaitingForCorrection()
	{
		return $this->getStatus() == MiniSitePageTextPeer::STATUS_WAITING_FOR_CORRECTION;
	}
	
	/**
	 * @param array $objects
	 * 
	 * @return int The primary key
	 * 
	 * @throws AccessDeniedHttpException
	 */
	public function autosave(array $objects)
	{
		// Check rights
		if (!BNSAccess::getContainer()->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ACCESS_BACK)) {
			throw new AccessDeniedHttpException('You can NOT access to this page');
		}
		
		if ($this->isPublished() && !BNSAccess::getContainer()->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ADMINISTRATION)) {
			throw new AccessDeniedHttpException('You can NOT access to this page');
		}
		
		$this->setDraftContent($objects['draft_content']);
		$this->setDraftTitle($objects['draft_title']);
		$this->setStatus(MiniSitePageTextPeer::STATUS_DRAFT);
		$this->save();
		
		return $this->getPrimaryKey();
	}
}

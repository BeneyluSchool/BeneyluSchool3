<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePageText;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageText extends BaseMiniSitePageText implements AutosaveInterface
{

	use RichTextParser;

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

	public function getRichPublishedContent()
	{
		return $this->parse($this->getPublishedContent());
	}

	/**
	 * Simple shortcut
	 *
	 * @return User
	 */
	public function getAuthor()
	{
		return $this->getUserRelatedByAuthorId();
	}

	/**
	 * Simple shortcut
	 *
	 * @return User
	 */
	public function getLastModificationAuthor()
	{
		return $this->getUserRelatedByLastModificationAuthorId();
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
	 * @return string 180 characters content
	 */
	public function getShortDraftContent()
	{
		return StringUtil::substrws($this->getDraftContent());
	}

    /**
	 * @return string 180 characters content
	 */
	public function getShortPublishedContent()
	{
		return StringUtil::substrws($this->getPublishedContent());
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
		$this->setLastModificationAuthorId(BNSAccess::getUser()->getId());

		$this->save();

		return $this->getPrimaryKey();
	}
}

<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePageNews;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageNews extends BaseMiniSitePageNews implements \BNS\App\AutosaveBundle\Autosave\AutosaveInterface
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

	public function isCityNews()
	{
		return $this instanceof MiniSitePageCityNews;
	}

	public function isFuture()
	{
		return $this->getPublishedAt() && $this->getPublishedAt('Ymd') > (new \DateTime())->format('Ymd');
	}

	public function isPast()
	{
		return $this->getPublishedEndAt() && $this->getPublishedEndAt('Ymd') < (new \DateTime())->format('Ymd');
	}

	public function getLogicalStatus()
	{
		if ($this->isCityNews()) {
			if ($this->isFuture()) {
				return 'FINISHED';
			} else if ($this->isPast()) {
				return 'SCHEDULED';
			}
		}

		return $this->getStatus();
	}

	public function getRichContent()
	{
		return $this->parse($this->getContent());
	}

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

	/**
	 * @return string 180 characters content
	 */
	public function getShortContent()
	{
		return StringUtil::substrws($this->getContent());
	}

	public function autosave(array $objects)
	{
        $container = BNSAccess::getContainer();

		// Check rights
		if (!$container->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ACCESS_BACK)) {
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
            //Stat Action
            $container->get('stat.site')->createDynamicPageArticle();
		}
		else {
			// Check rights
			if ($this->isPublished() && !$container->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ADMINISTRATION)) {
				throw new AccessDeniedHttpException('You can not access to this page');
			}

			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setStatus(MiniSitePageNewsPeer::STATUS_DRAFT);
			$this->save();
		}

		return $this->getPrimaryKey();
	}

    public function switchPin()
    {
        if ($this->getIsPinned()) {
            $this->setIsPinned(false);
        }
        else {
            $this->setIsPinned(true);
        }
    }

    public function getResourceAttachments()
    {
        if($this->isNew() && isset($this->attachments))
        {
            return $this->attachments;
        }else{
            return parent::getResourceAttachments();
        }
    }
}

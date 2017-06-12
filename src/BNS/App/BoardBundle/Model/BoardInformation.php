<?php

namespace BNS\App\BoardBundle\Model;

use \BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use \BNS\App\BoardBundle\Model\om\BaseBoardInformation;
use \BNS\App\CoreBundle\Access\BNSAccess;
use \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BoardInformation extends BaseBoardInformation implements AutosaveInterface
{
	/**
     * @param array $objects
     *
     * @return int The object's primary key
     *
     * @throws AccessDeniedHttpException
     */
    public function autosave(array $objects)
    {
        // Check rights
        $rightManager = BNSAccess::getContainer()->get('bns.right_manager');
        if (!$rightManager->hasRight(Board::PERMISSION_BOARD_ACCESS_BACK)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You can NOT access to this page');
        }

        $this->setTitle($objects['title']);
        $this->setContent($objects['content']);

        // New object : save into database and return his new primary key
        if ($this->isNew()) {
            $board = BoardQuery::create()->filterByGroup($rightManager->getCurrentGroup())->findOne();
            $this->setBoard($board);
            $this->setUser(BNSAccess::getUser());
            $this->setStatus(BoardInformationPeer::STATUS_DRAFT);
            $this->save();

        } else {
            // Check rights
            if (($this->isPublished() || $this->isProgrammed()) && !BNSAccess::getContainer()->get('bns.right_manager')->hasRight(Board::PERMISSION_BOARD_ACCESS_BACK)) {
                throw new AccessDeniedHttpException('You can NOT access to this page');
            }
            $this->setStatus(BoardInformationPeer::STATUS_DRAFT);
            $this->save();
        }

        return $this->getPrimaryKey();
    }

    /**
     * @return boolean
     */
    public function isProgrammed()
    {
        return null != $this->getPublishedAt() && $this->getPublishedAt()->getTimestamp() > time();
    }

    /**
     * @return boolean True if article has status to PUBLISHED, false otherwise
     */
    public function isPublished()
    {
        return null != $this->getPublishedAt() && $this->getPublishedAt()->getTimestamp() <= time();
    }

	/**
	 * Simple shortcut
	 * 
	 * @return boolean
	 */
	public function isAlert()
	{
		return $this->getIsAlert();
	}

	/**
	 * Simple shortcut
	 * 
	 * @return boolean
	 */
	public function isCopied()
	{
		return $this->getIsCopied();
	}
}

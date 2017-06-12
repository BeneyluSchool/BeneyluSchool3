<?php

namespace BNS\App\BoardBundle\Model;

use \BNS\App\BoardBundle\Model\om\BaseBoardInformationQuery;
use \BNS\App\CoreBundle\Access\BNSAccess;

class BoardInformationQuery extends BaseBoardInformationQuery
{
	/**
     * @param Board $board
     * @param null $pager
     * @param null|int $page
     * @param int $limit
     *
     * @return array<BoardInformation>
     */
    public static function getInformationsFromBoard(Board $board, &$pager = null, $page = null, $filters = null, $limit = 5)
    {
        $query = self::create()
            ->joinWith('User')
            ->joinWith('User.Profile')
            ->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
            ->orderByIsAlert(\Criteria::DESC)
            ->orderByCreatedAt(\Criteria::DESC)
        ;

        if (null != $filters) {
            if (isset($filters['filters']) && count($filters['filters']) > 0) {
                if (false !== ($key = array_search('programmed', $filters['filters']))) {
                    $query->filterByPublishedAt('now', \Criteria::GREATER_THAN);
                    unset($filters['filters'][$key]);
                }
                if (false !== ($key = array_search(BoardInformationPeer::STATUS_PUBLISHED, $filters['filters']))) {
                    $query->_or()->filterByPublishedAt('now', \Criteria::LESS_EQUAL);
                    unset($filters['filters'][$key]);
                }
                if (count($filters['filters']) > 0) {
                    $query->_or()->filterByStatus($filters['filters'], \Criteria::IN);
                }
            }
        }

        $query->filterByBoard($board);

        // Show only own article if is pupil user
        if (!BNSAccess::getContainer()->get('bns.right_manager')->hasRight(Board::PERMISSION_BOARD_ACCESS_BACK)) {
            $query->filterByUser(BNSAccess::getUser());
        }

        if (null == $page) {
            $articles = $query->find();
        } else {
            $pager = $query->paginate($page, $limit);
            $articles = $pager->getResults();
        }

        return $articles;
    }

    public function filterByisPublished()
    {
        $this->filterByStatus(BoardInformationPeer::STATUS_PUBLISHED, \Criteria::EQUAL);
        $this->filterByPublishedAt(time(), \Criteria::LESS_EQUAL);
        $this->filterByExpiresAt(time(), \Criteria::GREATER_THAN);
        $this->orWhere('bi.ExpiresAt IS NULL');

        return $this;
    }
}

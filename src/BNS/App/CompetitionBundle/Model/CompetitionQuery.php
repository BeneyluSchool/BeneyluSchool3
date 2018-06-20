<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseCompetitionQuery;

class CompetitionQuery extends BaseCompetitionQuery
{

    /**
     * Applies status filters to simple and challenge competitions.
     *
     * @param array $simpleFilters
     * @param array $challengeFilters
     * @return $this
     */
    public function applyStatusFilters(array $simpleFilters, array $challengeFilters)
    {
        $conditions = [];
        if (count($simpleFilters)) {
            $this->condition('simple', CompetitionPeer::CLASS_KEY.' = ?', CompetitionPeer::CLASSKEY_SIMPLECOMPETITION);
            $this->condition('simple_status', CompetitionPeer::STATUS.' IN ?', $simpleFilters);
            $this->combine(['simple', 'simple_status'], \Criteria::LOGICAL_AND, 'simple_filter');
            $conditions[] = 'simple_filter';
        }
        if (count($challengeFilters)) {
            $this->condition('challenge', CompetitionPeer::CLASS_KEY.' = ?', CompetitionPeer::CLASSKEY_READINGCHALLENGE);
            $this->condition('challenge_status', CompetitionPeer::STATUS.' IN ?', $challengeFilters);
            $this->combine(['challenge', 'challenge_status'], \Criteria::LOGICAL_AND, 'challenge_filter');
            $conditions[] = 'challenge_filter';
        }
        if (count($conditions)) {
            $this->combine($conditions, \Criteria::LOGICAL_OR);
        }

        return $this;
    }

}

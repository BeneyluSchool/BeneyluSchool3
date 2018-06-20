<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseQuestionnaireParticipation;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;

class QuestionnaireParticipation extends BaseQuestionnaireParticipation
{
    protected $globalLike;

    public function showQuestionnaire()
    {
        return MediaQuery::create()->findPk($this->getQuestionnaireId());
    }

    /**
     *
     */
    public function getGlobalLike()
    {
        if (null === $this->globalLike) {
            $BookParticipation = BookParticipationQuery::create()
                ->filterByUserId($this->getUserId())
                ->useBookQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->filterByQuestionnaireId($this->getQuestionnaireId())
                    ->endUse()
                ->endUse()
                ->findOne();
            if ($BookParticipation) {
                $this->globalLike = $BookParticipation->getLike();
            } else {
                $competitionParticipation = CompetitionParticipationQuery::create()
                    ->filterByUserId($this->getUserId())
                    ->useCompetitionQuery()
                        ->useCompetitionQuestionnaireQuery()
                            ->filterByQuestionnaireId($this->getQuestionnaireId())
                        ->endUse()
                    ->endUse()
                    ->findOne();
                if ($competitionParticipation) {
                    $this->globalLike = $competitionParticipation->getLike();
                }
            }
        }

        return $this->globalLike;
    }

    public function setGlobalLike($like)
    {
        $this->globalLike = (bool) $like;
    }
}

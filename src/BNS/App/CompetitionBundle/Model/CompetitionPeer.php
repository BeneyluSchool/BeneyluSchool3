<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseCompetitionPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

class CompetitionPeer extends BaseCompetitionPeer
{
    const TYPE_SIMPLE_COMPETITION = "SIMPLE_COMPETITION";
    const TYPE_READING_CHALLENGE = "READING_CHALLENGE";
    const TYPE_PEDAGOGIC_COURSE = "PEDAGOGIC_COURSE";

    /**
     * @param $id
     * @return Competition|null
     */
    public static function getCompetitionByQuestionnaireId($id)
    {
        $media = MediaQuery::create()->findPk($id);
        if (!$media) {
            return null;
        }
        if (!$competition = $media->getCompetition()) {
            if ($book = $media->getBook()) {
                $competition = $book->getCompetition();
            }
        }

        return $competition;
    }

    /**
     * @param $id
     * @return CompetitionQuestionnaire|null|CompetitionBookQuestionnaire
     */
    public static function getCompetitionQuestionnaireByQuestionnaireId($id)
    {
        $media = MediaQuery::create()->findPk($id);
        if (!$media) {
            return null;
        }
        $competitionQuestionnaire = CompetitionQuestionnaireQuery::create()->filterByQuestionnaireId($id)->findOne();

        if (!$competitionQuestionnaire) {
           $competitionQuestionnaire = CompetitionBookQuestionnaireQuery::create()->filterByQuestionnaireId($id)->findOne();
        }

        return $competitionQuestionnaire;
    }
}

<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseCompetitionBookQuestionnaire;

class CompetitionBookQuestionnaire extends BaseCompetitionBookQuestionnaire
{
    const VALIDATE_PENDING = 0;
    const VALIDATE_VALIDATED = 1;
    const VALIDATE_REFUSED = -1;


    public function fixOldScope()
    {
        if (is_integer($this->getQuestionnaireId())) {
            $this->setQuestionnaireId($this->getQuestionnaireId());
        }
    }

    public function preUpdate(\PropelPDO $con = null)
    {
        $this->fixOldScope();
        return true;
    }

    public function getCompetition()
    {
       return CompetitionQuery::create()->useBookQuery()
            ->filterById($this->getBookId())
            ->endUse()
            ->findOne();
    }
}

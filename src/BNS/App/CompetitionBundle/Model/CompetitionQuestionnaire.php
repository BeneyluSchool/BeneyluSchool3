<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseCompetitionQuestionnaire;

class CompetitionQuestionnaire extends BaseCompetitionQuestionnaire
{
    const VALIDATE_PENDING = 0;
    const VALIDATE_VALIDATED = 1;
    const VALIDATE_REFUSED = -1;
}

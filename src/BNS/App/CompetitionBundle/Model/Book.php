<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseBook;
use BNS\App\CoreBundle\Access\BNSAccess;
use PropelPDO;

class Book extends BaseBook
{

    /**
     * Exposed on form, to handle the questionnaire Media collection and their parameters
     *
     * @var array
     */
    public $questionnaires;

    /**
     * @var float
     */
    public $percent;

    /**
     * @var int
     */
    public $score;

    public function getQuestionnaires()
    {
        $criteria = new \Criteria();
        $criteria->addAscendingOrderByColumn(CompetitionBookQuestionnairePeer::RANK);
        $questionnaires = [];
        foreach ($this->getCompetitionBookQuestionnairesJoinQuestionnaire($criteria) as $competitionBookQuestionnaire) {
            if (!$competitionBookQuestionnaire->getValidate()) {
                continue;
            }
            $media = $competitionBookQuestionnaire->getQuestionnaire();
            $media->allowAttempts = $competitionBookQuestionnaire->getAllowAttempts();
            $media->maxAttemptsNumber = $competitionBookQuestionnaire->getAttemptsNumber();
            $media->required = $competitionBookQuestionnaire->getRequired();
            $media->pagesCount = $media->getWorkshopContent()->getWorkshopDocument()->getWorkshopDocumentPagesCount();
            $media->questionsCount = $media->getWorkshopContent()->getWorkshopDocument()->getWorkshopDocumentQuestionsCount();
            $media->sender = $competitionBookQuestionnaire->getUser();
            $questionnaires[] = $media;
        }

        return $questionnaires;
    }

    /**
     * @inheritDoc
     */
    public function preSave(PropelPDO $con = null)
    {
        if ($this->competitionBookQuestionnairesScheduledForDeletion !== null) {
            if (!$this->competitionBookQuestionnairesScheduledForDeletion->isEmpty()) {
                foreach ($this->competitionBookQuestionnairesScheduledForDeletion as $competitionBookQuestionnaire) {
                    // actually delete the cross ref record
                    $competitionBookQuestionnaire->delete($con);
                }
                $this->competitionBookQuestionnairesScheduledForDeletion = null;
            }
        }

        return parent::preSave($con);
    }

    public function getMediaUrl()
    {
        if ($media = $this->getMedia()) {
            return BNSAccess::getContainer()->get('bns.media.download_manager')->getImageDownloadUrl($media, 'competition_portrait');
        }

        return null;
    }

    public function getCompetitionTitle()
    {
        if ($competition = $this->getCompetition()) {
            return $competition->getTitle();
        }

        return null;
    }

    public function getCompetitionType()
    {
        if ($competition = $this->getCompetition()) {
            return $competition->getCompetitionType();
        }

        return null;
    }

    public function getPublishedAt($format = null)
    {
        if ($competition = $this->getCompetition()) {
            return $competition->getPublishedAt($format);
        }

        return null;
    }

    public function getPercent()
    {
        if (isset($this->percent)) {
            return $this->percent;
        }
    }

    public function getScore()
    {
        if (isset($this->score)) {
            return $this->score;
        }
    }

    public function getNoticeDoc()
    {
        $media = $this->getNotice();
        if (!$media) {
            return $media;
        }

        $notice = BookNoticeQuery::create()
            ->filterByNotice($media)
            ->filterByBook($this)
            ->filterByValidate(true)
            ->findOne();

        if ($notice) {
            $media->sender = $notice->getUser();
        } else {
            $media->sender = $this->getCompetition()->getUser();
        }

        return $media;
    }

}

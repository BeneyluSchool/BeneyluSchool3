<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipation;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopDocument;
use BNS\App\CoreBundle\Model\User;

/**
 * Class WorkshopDocument
 *
 * @package BNS\App\WorkshopBundle\Model
 *
 * @method string getType()
 * @method User getAuthor()
 * @method WorkshopDocument setAuthor()
 * @method Media getMedia()
 * @method WorkshopDocument setMedia(Media $media)
 */
class WorkshopDocument extends BaseWorkshopDocument implements WorkshopContentInterface
{

    const STATUS_EDITABLE = 'e';

    const STATUS_LOCKED = 'l';

    /**
     * Renvoie le nom du document qui est stocké dans la table resource
     * @return mixed
     */
    public function getLabel()
    {
        return $this->getMedia()->getLabel();
    }

    public function getMediaId()
    {
        return $this->getMedia()->getId();
    }

    /**
     * @deprecated seems not used
     * Ajoute une page
     * @param $layout
     * @param $position
     * @param $orientation
     * @return WorkshopPage
     */
    public function addPage($layout, $position, $orientation)
    {
        $page = new WorkshopPage();
        $page->setDocumentId($this->getId());
        $page->setLayoutCode($layout);
        $page->setOrientation($orientation);
        try {
            $page->insertAtRank($position);
        } catch (\PropelException $e) {
            $page->insertAtBottom();
        }
        $page->save();

        return $page;
    }

    public function getWidgetGroups()
    {
        return WorkshopWidgetGroupQuery::create()
            ->useWorkshopPageQuery()
                ->filterByWorkshopDocument($this)
            ->endUse()
            ->find()
        ;
    }

    public function isLocked()
    {
        return self::STATUS_LOCKED === $this->getStatus();
    }

    public function isEditable()
    {
        return self::STATUS_EDITABLE === $this->getStatus();
    }

    public function isQuestionnaire()
    {
        return Media::WORKSHOP_DOCUMENT_QUESTIONNAIRE === $this->getDocumentType();
    }

    public function getCompetition()
    {
        if (MediaManager::STATUS_QUESTIONNAIRE_COMPETITION === $this->getMedia()->getStatusDeletion()) {
            /** @var Media $media */
            if ($media = $this->getMedia()) {
                if ($competition = $media->getCompetition()) {
                    return $competition;
                }
                if ($book = $media->getBook()) {
                    return $book->getCompetition();
                }
            }
        }

        return null;
    }

    public function getBook()
    {
        return BookQuery::create()
            ->useCompetitionBookQuestionnaireQuery()
            ->filterByQuestionnaireId($this->getMedia()->getId())
            ->endUse()
            ->findOne();
    }

    public function getParticipation()
    {
        if ($this->isQuestionnaire()) {
            return QuestionnaireParticipationQuery::create()
                ->filterByQuestionnaireId($this->getId())
                ->filterByUserId(BNSAccess::getContainer()->get('bns.right_manager')->getUserSessionId())
                ->select(['score', 'like'])
                ->findOne();
        }

        return null;
    }

    public function getWorkshopDocumentPagesCount()
    {
        return WorkshopPageQuery::create()
            ->filterByDocumentId($this->getId())
            ->count();
    }

    public function getWorkshopDocumentQuestionsCount()
    {
        if ($this->isQuestionnaire()) {
            return WorkshopWidgetGroupQuery::create()
                ->useWorkshopWidgetQuery()
                    ->filterByType(['closed', 'multiple', 'simple', 'gap-fill-text'])
                ->endUse()
                ->useWorkshopPageQuery()
                    ->filterByDocumentId($this->getId())
                ->endUse()
                ->count();
        }

        return null;
    }


    public function getWorkshopDocumentMaxAttempts()
    {
        $competitionQuestionnaire = CompetitionQuestionnaireQuery::create()
            ->filterByQuestionnaireId($this->getMediaId())
            ->findOne();

        if ($competitionQuestionnaire) {
            return $competitionQuestionnaire->getAttemptsNumber();
        }

        return null;
    }

}

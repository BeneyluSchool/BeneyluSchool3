<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CorrectionBundle\Model\CorrectionInterface;
use BNS\App\CorrectionBundle\Model\CorrectionTrait;
use BNS\App\MediaLibraryBundle\Model\AttachmentTrait;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidget;
use PropelPDO;

class WorkshopWidget extends BaseWorkshopWidget implements CorrectionInterface
{
    use AttachmentTrait, CorrectionTrait, RichTextParser {
        AttachmentTrait::postSave as attachmentTraitPostSave;
        AttachmentTrait::postDelete as attachmentTraitPostDelete;
        CorrectionTrait::postSave as CorrectionTraitPostSave;
        CorrectionTrait::postDelete as CorrectionTraitPostDelete;
    }


    /**
     * @var float
     */
    public $percent;

    /**
     * @var int
     */
    public $score;

    /**
     * @inheritDoc
     */
    public static function getCorrectionRightName()
    {
        return 'WORKSHOP_CORRECTION';
    }

    public function getExtendedSetting()
    {
       return $this->getWorkshopWidgetExtendedSetting();
    }

    public function getPage()
    {
        $page = WorkshopPageQuery::create()
            ->filterByWorkshopWidgetGroup($this->getWorkshopWidgetGroup())
            ->findOne();
        return $page;
    }

    public function getAttemptsNumber()
    {
        return $this->getWorkshopWidgetGroup()->getWorkshopPage()->getWorkshopDocument()->getAttemptsNumber();
    }

    public function getPercent(){
        if(isset($this->percent)){
            return $this->percent;
        }
    }

    public function getScore(){
        if (isset($this->score)) {
            return $this->score;
        }
    }

    public function getRichContent()
    {
        return $this->parse($this->getContent());
    }

    public function getCompetition()
    {
        $competition = CompetitionQuery::create()
            ->useCompetitionQuestionnaireQuery()
                ->useQuestionnaireQuery()
                    ->useWorkshopContentQuery()
                        ->useWorkshopDocumentQuery()
                            ->useWorkshopPageQuery()
                                ->useWorkshopWidgetGroupQuery()
                                    ->useWorkshopWidgetQuery()
                                        ->filterById($this->getId())
                                    ->endUse()
                                ->endUse()
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse()
            ->endUse()
            ->findOne();
        if (!$competition) {
            $competition = CompetitionQuery::create()
                ->useBookQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->useQuestionnaireQuery()
                            ->useWorkshopContentQuery()
                                ->useWorkshopDocumentQuery()
                                    ->useWorkshopPageQuery()
                                        ->useWorkshopWidgetGroupQuery()
                                            ->useWorkshopWidgetQuery()
                                                ->filterById($this->getId())
                                            ->endUse()
                                        ->endUse()
                                    ->endUse()
                                ->endUse()
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse()
                ->findOne();
        }

           return $competition;

    }

    public function getMediaClassName()
    {
        return "WorkshopWidget";
    }

    public function preSave(PropelPDO $con = null)
    {
        $mediaIds = array();
        if ($this->getMediaId()) {
            $mediaIds[] = $this->getMediaId();
        }
        if (in_array($this->getType(), ['closed', 'multiple', 'simple', 'gap-fill-text'])) {
            if ($this->getExtendedSetting()) {
                $choices = $this->getExtendedSetting()->getChoices();
                if (isset($choices) && is_array($choices)) {
                    foreach ($choices as $choice) {
                        if (isset($choice['media_id'])) {
                            $mediaIds[] = ($choice['media_id']);
                        }
                    }
                }
            }
        }
        $medias = MediaQuery::create()->filterById($mediaIds, \Criteria::IN)->find();
        $this->setAttachments($medias);
        return parent::preSave($con);
    }

    public function postSave(\PropelPDO $con = null)
    {
        $this->CorrectionTraitPostSave($con);
        $this->attachmentTraitPostSave($con);
        parent::postSave($con);
    }

    public function postDelete(\PropelPDO $con = null)
    {
        $this->CorrectionTraitPostDelete($con);
        $this->attachmentTraitPostDelete($con);
        parent::postDelete($con);
    }
}

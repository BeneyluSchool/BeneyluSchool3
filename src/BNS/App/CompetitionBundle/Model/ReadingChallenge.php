<?php

namespace BNS\App\CompetitionBundle\Model;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;


/**
 * Skeleton subclass for representing a row from one of the subclasses of the 'Competition' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CompetitionBundle.Model
 */
class ReadingChallenge extends Competition {

    /**
     * Constructs a new ReadingChallenge class, setting the class_key column to CompetitionPeer::CLASSKEY_3.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setClassKey(CompetitionPeer::CLASSKEY_3);
    }

    public function toStatisticsArray()
    {
        $array = array();
        array_push($array, [$this->getTitle(), $this->getScore(), $this->getPercent() * 100]);
        foreach ($this->getBooks() as $book) {
            array_push($array, [$book->getTitle(), $book->getScore(), $book->getPercent() * 100]);
            foreach ($book->questionnaires as $questionnaire) {
                /** @var Media $questionnaire */
                array_push($array, [$questionnaire->getLabel(), $questionnaire->getScore(), $questionnaire->getPercent() * 100]);
                foreach ($questionnaire->getWorkshopWidgetGroupsByMedia() as $workshopWidgetGroup) {
                    /** @var WorkshopWidgetGroup $workshopWidgetGroup */
                    foreach ($workshopWidgetGroup->getWorkshopWidgets() as $widget) {
                        array_push($array, [strip_tags($widget->getContent()), $widget->getScore(), $widget->getPercent() * 100]);
                    }
                }
            }
        }
        return $array;
    }
} // ReadingChallenge

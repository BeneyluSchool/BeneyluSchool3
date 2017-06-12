<?php

namespace BNS\App\ClassroomBundle\Model;

use BNS\App\ClassroomBundle\Model\om\BaseClassroomNewspaper;

class ClassroomNewspaper extends BaseClassroomNewspaper
{

    /** @var ClassroomNewspaper */
    protected $last;

    public function read()
    {
        $this->setViews($this->getViews() + 1);
        $this->save();
    }

    public function getLast()
    {
        if (!$this->last) {
            $this->last = ClassroomNewspaperQuery::create()
                ->filterByIsCalendar(null, \Criteria::ISNULL)
                ->findOneByDate(date('Y-m-d',$this->getDate('U') - 3600 * 24))
            ;
        }

        return $this->last;
    }

    public function getLastRiddle()
    {
        $last = $this->getLast();
        if ($last) {
            return $last->getRiddle();
        }

        return null;
    }

    public function getLastRiddleAnswer()
    {
        $last = $this->getLast();
        if ($last) {
            return $last->getRiddleAnswer();
        }

        return null;
    }

    public function getMediaUrl()
    {
        $media = $this->getMediaRelatedByMediaId();

        if ($media && $media->getTypeUniqueName() == 'DOCUMENT') {
            return $media->getDownloadUrl();
        } elseif ($media && $media->getTypeUniqueName() == 'EMBEDDED_VIDEO') {
            $value = unserialize($media->getValue());
            $type = $value['type'];
            $id = $value['value'];

            return $media->getValueFromVideoId($type, $id);
        }
    }

    public function getMediaPreviewUrl()
    {
        if ($media = $this->getMediaRelatedByMediaPreviewId()) {
            return $media->getDownloadUrl();
        }

        return false;
    }

}

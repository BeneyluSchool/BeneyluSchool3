<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidgetGroup;

class WorkshopWidgetGroup extends BaseWorkshopWidgetGroup
{
    /**
     * Ajoute un widget à un groupe de widgets
     * @param $position
     * @param $type
     * @param Media $media
     * @param $content
     * @return WorkshopWidget
     */
    public function addWidget($position, $type, Media $media = null, $content)
    {
        $widget = new WorkshopWidget();
        $widget->setWidgetGroupId($this->getId());
        $widget->setPosition($position);
        $widget->setType($type);
        if ($media != null) {
            $widget->setMediaId($media->getId());
        } else {
            $widget->setMediaId(null);
        }
        $widget->setContent($content);
        $widget->save();

        return $widget;
    }


    public function fixOldScope()
    {
        if (is_array($this->oldScope)) {
            if (isset($this->oldScope[0]) && !isset($this->oldScope[1])) {
                $this->oldScope[1] = $this->getZone();
            }
            if (isset($this->oldScope[1]) && !isset($this->oldScope[0])) {
                $this->oldScope[0] = $this->getPageId();
            }
        }
    }

    public function preUpdate(\PropelPDO $con = null)
    {
        $this->fixOldScope();
        return true;
    }
}

<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidgetGroup;

class WorkshopWidgetGroup extends BaseWorkshopWidgetGroup
{

    protected $versioningDisabled = false;

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
    public function getVersionCreatedBy()
    {
        $value = parent::getVersionCreatedBy();

        return $value ? (int)$value : null;
    }

    public function isVersioningNecessary($con = null)
    {
        return !$this->versioningDisabled && parent::isVersioningNecessary($con);
    }

    public function disableVersioning()
    {
        $this->versioningDisabled = true;
    }

    public function enableVersioning()
    {
        $this->versioningDisabled = false;
    }

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
        try {
            $widget->insertAtRank($position);
        } catch (\PropelException $e) {
            $widget->insertAtBottom();
        }
        $widget->setType($type);
        if ($media) {
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

    public function getPercent(){
        if(isset($this->percent)){
            return $this->percent;
        }
    }

    /**
     * special setter that prevent sortable automatique behavior
     * @param $pageId
     * @param $zone
     * @param $position
     */
    public function setPageZonePosition($pageId, $zone, $position)
    {
        $this->setPageId($pageId);
        $this->setZone($zone);
        $this->setPosition($position);

        if (!$this->isColumnModified(WorkshopWidgetGroupPeer::POSITION) &&
            (
                $this->isColumnModified(WorkshopWidgetGroupPeer::PAGE_ID) ||
                $this->isColumnModified(WorkshopWidgetGroupPeer::ZONE)
            )
        ) {
            // force modification to prevent sortable from moving object
            $this->modifiedColumns[] = WorkshopWidgetGroupPeer::POSITION;
        }
    }
}

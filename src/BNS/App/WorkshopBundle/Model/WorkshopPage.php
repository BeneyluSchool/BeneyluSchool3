<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopPage;

class WorkshopPage extends BaseWorkshopPage
{

    const ORIENTATION_PORTRAIT = 'p';
    const ORIENTATION_LANDSCAPE = 'l';

    public function __construct()
    {
        $this->setOrientation(self::ORIENTATION_PORTRAIT);
    }

    /**
     * Ajoute et renvoie un groupe de widget pour une page
     * @param $type
     * @param $zone
     * @param $position
     * @return WorkshopWidgetGroup
     */
    public function addWidgetGroup($type, $zone, $position)
    {
        $widgetGroup = new WorkshopWidgetGroup();
        $widgetGroup->setType($type);
        $widgetGroup->setZone($zone);
        $widgetGroup->setPosition($position);
        $widgetGroup->save();
        return $widgetGroup;
    }
}

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
     * @deprecated do not use
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

        // this shouldn't be done like this use insertAt*
        $widgetGroup->setPosition($position);

        $widgetGroup->save();
        return $widgetGroup;
    }

    /**
     * @inheritDoc
     */
    public function setSizes($v)
    {
        if (is_array($v)) {
            $currentValue = $this->getSizes();
            if (is_array($currentValue)) {
                $v = array_merge($currentValue, $v);
            }
        }

        return parent::setSizes(json_encode($v));
    }

    /**
     * @inheritDoc
     */
    public function getSizes()
    {
        return json_decode(parent::getSizes(), true);
    }

    public function countPastWidgets()
    {
        return WorkshopWidgetQuery::create()
            ->filterByType(['simple', 'multiple', 'closed', 'gap-fill-text'], \Criteria::IN)
            ->useWorkshopWidgetGroupQuery()
                ->useWorkshopPageQuery()
                    ->filterByPosition($this->getPosition(), \Criteria::LESS_THAN)
                    ->filterByDocumentId($this->getDocumentId())
                ->endUse()
            ->endUse()
            ->count();
    }
}

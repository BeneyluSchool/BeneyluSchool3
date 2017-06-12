<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidget;

class WorkshopWidget extends BaseWorkshopWidget
{

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
}

<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\CorrectionBundle\Model\CorrectionTrait;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidgetVersion;

class WorkshopWidgetVersion extends BaseWorkshopWidgetVersion
{
    use CorrectionTrait;

    /**
     * @inheritDoc
     */
    public static function getCorrectionRightName()
    {
        return 'WORKSHOP_CORRECTION';
    }
}

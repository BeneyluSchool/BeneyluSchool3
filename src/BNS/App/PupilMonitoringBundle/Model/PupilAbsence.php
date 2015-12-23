<?php

namespace BNS\App\PupilMonitoringBundle\Model;

use BNS\App\PupilMonitoringBundle\Model\om\BasePupilAbsence;
use BNS\App\PupilMonitoringBundle\Model\om\BasePupilAbsencePeer;

class PupilAbsence extends BasePupilAbsence
{
    
    public function printType()
    {
        switch($this->getType())
        {
            case BasePupilAbsencePeer::TYPE_AFTERNOON:
                return "Après midi";
            case BasePupilAbsencePeer::TYPE_MORNING:
                return "Matin";
            case BasePupilAbsencePeer::TYPE_DAY:
                return "Journée complète";
        }
    }
    
    public function printLegitimate()
    {
        return $this->getIsLegitimate() ? 'Légitime' : '';
    }
}

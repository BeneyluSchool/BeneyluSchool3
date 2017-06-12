<?php

namespace BNS\App\PortalBundle\Model;

use BNS\App\PortalBundle\Manager\PortalManager;
use BNS\App\PortalBundle\Model\om\BasePortal;

class Portal extends BasePortal
{

    public function getFont($forPrint = false)
    {
        $settings = unserialize($this->getSettings());
        if(isset($settings['font']))
        {
            return $forPrint ? PortalManager::$fonts[$settings['font']] : $settings['font'];
        }
        return 'ubuntu';
    }

    public function getColor($forPrint = false)
    {
        $settings = unserialize($this->getSettings());
        if(isset($settings['color']))
        {
            return $forPrint ? PortalManager::$colors[$settings['color']] : $settings['color'];
        }
        return 'blue';
    }

    public function setFont($font)
    {
        $settings = unserialize($this->getSettings());
        $settings['font'] = $font;
        $this->setSettings(serialize($settings));
    }

    public function setColor($color)
    {
        $settings = unserialize($this->getSettings());
        $settings['color'] = $color;
        $this->setSettings(serialize($settings));
    }

    public function getWidgetsByZone($zone = 'MAIN')
    {
        return PortalWidgetQuery::create()
            ->orderByPosition()
            ->usePortalWidgetGroupQuery()
                ->filterByPortalId($this->getId())
                ->usePortalZoneQuery()
                    ->filterByUniqueName($zone)
                ->endUse()
            ->endUse()
            ->find();
    }




}

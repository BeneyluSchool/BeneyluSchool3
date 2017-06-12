<?php

namespace BNS\App\CoreBundle\MobileDetect;

use SunCat\MobileDetectBundle\Helper\DeviceView as BaseDeviceView;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class DeviceView extends BaseDeviceView
{
    /**
     * @inheritDoc
     */
    public function hasSwitchParam()
    {
        // disable switch param
        return false;
    }

}

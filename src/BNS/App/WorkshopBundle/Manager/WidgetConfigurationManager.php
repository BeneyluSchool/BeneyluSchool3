<?php

namespace BNS\App\WorkshopBundle\Manager;

/**
 * Class WidgetConfigurationManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class WidgetConfigurationManager extends ConfigurationManager
{

    /**
     * Cache for the valid configuration codes
     *
     * @var array
     */
    private $configurationCodes;

    /**
     * Gets the list of valid configuration codes
     *
     * @return array
     */
    public function getValidConfigurationCodes()
    {
        if (null === $this->configurationCodes) {
            $this->configurationCodes = array();
            foreach ($this->getList() as $configuration) {
                $this->configurationCodes[] = $configuration['code'];
            }
        }

        return $this->configurationCodes;
    }

}

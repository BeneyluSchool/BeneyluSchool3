<?php

namespace BNS\App\WorkshopBundle\Manager;

/**
 * Class ThemeManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class ThemeManager extends ConfigurationManager
{

    /**
     * Cache for valid theme codes
     *
     * @var array
     */
    private $validThemeCodes;

    /**
     * Tries to get the theme of the given object
     *
     * @param $object
     * @return array|null
     */
    public function getForObject($object)
    {
        // maybe the object has a theme code
        if (method_exists($object, 'getThemeCode')) {
            $code = $object->getThemeCode();

            return $this->findOneBy('code', $code);
        }

        return null;
    }

    /**
     * Gets the list of valid theme codes
     *
     * @return array
     */
    public function getValidThemeCodes()
    {
        if (null === $this->validThemeCodes) {
            $this->validThemeCodes = array();
            foreach ($this->getList() as $theme) {
                $this->validThemeCodes[] = $theme['code'];
            }
        }

        return $this->validThemeCodes;
    }

    /**
     * Gets the default theme code
     *
     * @return string
     */
    public function getDefaultThemeCode()
    {
        // for now, assume that the first defined theme is the default
        $res = $this->getValidThemeCodes();
        return $res[0];
    }

}

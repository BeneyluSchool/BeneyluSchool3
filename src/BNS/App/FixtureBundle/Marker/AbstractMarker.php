<?php

namespace BNS\App\FixtureBundle\Marker;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractMarker implements MarkerInterface
{
    /**
     * @param string $marker
     * @param mixed  $value
     *
     * @return mixed
     */
    public function getMarkers($marker, $value)
    {
        if (!preg_match_all('#' . $marker . '\(([^\s]+)\)#', $value, $matches)) {
            return false;
        }

        return $matches[1];
    }
}
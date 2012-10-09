<?php

namespace BNS\App\GPSBundle\Map;

use Vich\GeographicalBundle\Map\Marker\Icon\IconGeneratorInterface;

class IconGenerator implements IconGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function generateIcon($obj)
    {
		//Selon le lieu et donc sa catégorie renvoyer une Urlk
        return '/medias/images/gps/flag.png';
    }
}

<?php

namespace BNS\App\GPSBundle\Map;

use Vich\GeographicalBundle\Map\Marker\Icon\IconGeneratorInterface;

class IconGenerator implements IconGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
	protected $container;
	
	public function __construct($container)
    {
		$this->container = $container;
	}
	
    public function generateIcon($obj)
    {
		//Selon le lieu et donc sa catégorie renvoyer une Urlk
        return  $this->container->get('templating.helper.assets')->getUrl('/medias/images/gps/flag.png');
    }
}

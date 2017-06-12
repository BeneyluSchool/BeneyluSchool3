<?php

namespace BNS\App\CoreBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

class CustomLoader extends Loader
{
    private $activatedBundles;

    public function __construct($bundlesList)
    {
        $this->activatedBundles	= $bundlesList;
    }


    public function load($resource, $type = null)
    {

        $collection = new RouteCollection();

        if ('@LexikTranslationBundle/Resources/config/routing.yml' === $resource) {
            if (in_array('Lexik\Bundle\TranslationBundle\LexikTranslationBundle', $this->activatedBundles)) {
                $type = 'yaml';
                $importedRoutes = $this->import($resource, $type);
                $collection->addCollection($importedRoutes);
            }
        }

        return $collection;
    }

    public function supports($resource, $type = null)
    {
        return $type === 'customLoader';
    }

}

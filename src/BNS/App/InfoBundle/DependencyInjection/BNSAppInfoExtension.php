<?php

namespace BNS\App\InfoBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSAppInfoExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['feeds'])) {
            foreach($config['feeds'] as $key => $value)
            {
                $container->setParameter('bns_app_info_feeds_' . $key, $value);
            }
        }

        if (isset($config['nb_announcements'])) {
            foreach($config['nb_announcements'] as $key => $value)
            {
                foreach($value as $secondKey => $secondValue)
                {
                    $container->setParameter('bns_app_info_nb_announcements_' . $key . '_' . $secondKey, $secondValue);
                }
            }
        }

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
}

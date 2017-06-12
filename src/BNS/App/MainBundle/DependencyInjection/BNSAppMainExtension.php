<?php

namespace BNS\App\MainBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSAppMainExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ($container->hasDefinition('bns_main.visit_statistic') && isset($config['statistics']['visit_indicators']) && count($config['statistics']['visit_indicators']) > 0) {
            $service = $container->getDefinition('bns_main.visit_statistic');
            $service->replaceArgument(1, [
                'indicators' => $config['statistics']['visit_indicators']
            ]);
        }
    }
}

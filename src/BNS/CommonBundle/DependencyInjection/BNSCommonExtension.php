<?php

namespace BNS\CommonBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSCommonExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
//        $configuration = new Configuration();
//        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        // Load Project info file
        $configFolder = $container->getParameter('kernel.root_dir') . '/config';
        $locator = new FileLocator($configFolder);
        $projectFile = $locator->locate('projects.yml', null, true);
        $configValues = array();
        if ($projectFile && file_exists($projectFile)) {
            $configValues = Yaml::parse(file_get_contents($projectFile));
        }

        if ($container->hasDefinition('bns_common.manager.project_info')) {
            $serviceDefinition = $container->getDefinition('bns_common.manager.project_info');
            $serviceDefinition->replaceArgument(0, $configValues);
        }
    }
}

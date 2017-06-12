<?php

namespace BNS\App\CoreBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSAppCoreExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['api_limit'])) {
            $container->setParameter('bns_api_limit', $config['api_limit']);
        }

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ($container->has('bns_core.application_manager')) {
            $definition = $container->getDefinition('bns_core.application_manager');

            $definition->replaceArgument(0, $config['application_management']['enabled']);
            $definition->replaceArgument(1, $config['application_management']['auto_install']);
            $definition->replaceArgument(2, $config['application_management']['uninstall_disabled']);
            if (isset($config['application_management']['base_applications'])) {
                $definition->replaceArgument(3, $config['application_management']['base_applications']);
            }
            if (isset($config['application_management']['system_applications'])) {
                $definition->replaceArgument(4, $config['application_management']['system_applications']);
            }
            if (isset($config['application_management']['private_applications'])) {
                $definition->replaceArgument(5, $config['application_management']['private_applications']);
            }
            if (isset($config['application_management']['restricted_applications'])) {
                $definition->replaceArgument(8, $config['application_management']['restricted_applications']);
            }
        }

        if ($container->has('bns_core.assistant_right_manager')) {
            $definition = $container->getDefinition('bns_core.assistant_right_manager');

            $modules = $config['assistant_rights']['modules'];
            $permissions = $config['assistant_rights']['permissions'];

            $definition->setArguments(array(
                $modules,
                $permissions
            ));
        }
    }
}

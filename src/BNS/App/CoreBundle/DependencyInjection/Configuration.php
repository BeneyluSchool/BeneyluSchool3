<?php

namespace BNS\App\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('bns_app_core');

        $rootNode
            ->children()
                ->arrayNode('assistant_rights')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('permissions')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('modules')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ;

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode
            ->children()
                ->arrayNode('api_limit')
                    ->children()
                        ->integerNode('home_subscription')->end()
                    ->end()
                ->end()
                ->arrayNode('application_management')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('auto_install')
                            ->defaultFalse()
                            ->info('do not remove user permission if application is not installed')
                        ->end()
                        ->booleanNode('uninstall_disabled')
                            ->defaultFalse()
                            ->info('disable uninstallation of application')
                        ->end()
                        ->arrayNode('base_applications')
                            ->performNoDeepMerging()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('system_applications')
                            ->performNoDeepMerging()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('private_applications')
                            ->performNoDeepMerging()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('restricted_applications')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->performNoDeepMerging()
                            ->prototype('array')
                                ->performNoDeepMerging()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ;

        return $treeBuilder;
    }
}

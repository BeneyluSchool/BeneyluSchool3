<?php

namespace BNS\App\InfoBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('bns_app_info');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode
            ->children()
                ->arrayNode('feeds')
                    ->children()
                        ->scalarNode('twitter')->end()
                        ->scalarNode('updates')->end()
                        ->scalarNode('blog')->end()
                        ->scalarNode('forum')->end()
                    ->end()
                ->end()
                ->arrayNode('nb_announcements')
                    ->children()
                        ->arrayNode('index')
                        ->children()
                            ->scalarNode('blog')->end()
                            ->scalarNode('forum')->end()
                            ->scalarNode('custom')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        return $treeBuilder;
    }
}

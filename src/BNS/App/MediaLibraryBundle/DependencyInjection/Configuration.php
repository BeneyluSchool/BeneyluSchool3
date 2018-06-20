<?php

namespace BNS\App\MediaLibraryBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('bns_app_media_library');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode
            ->isRequired()
            ->children()
                ->scalarNode('default_adapter')->defaultValue('local')->end()
                ->arrayNode('thumb_configs')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('width')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('height')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('options')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('fill')
                                        ->defaultFalse()
                                    ->end()
                                    ->scalarNode('thumb_mode')
                                        ->defaultValue('outbound')
                                        ->validate()
                                        ->ifNotInArray(array('inset', 'outbound'))
                                            ->thenInvalid('Invalid thumb_mode %s')
                                        ->end()
                                    ->end()
                                    ->booleanNode('upscale')
                                        ->defaultTrue()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

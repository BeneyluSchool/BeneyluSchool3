<?php

namespace BNS\App\TranslationBundle\DependencyInjection;

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
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('translation');

        $rootNode
            ->children()
                ->scalarNode('api_key')->isRequired()->end()
                ->scalarNode('secret')->isRequired()->end()
                ->arrayNode('mappings')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('sources')->prototype('scalar')->end()->end()
                            ->arrayNode('locales')->prototype('scalar')->end()->end()
                            ->scalarNode('output')->defaultValue('[dirname][filename].[locale].[extension]')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

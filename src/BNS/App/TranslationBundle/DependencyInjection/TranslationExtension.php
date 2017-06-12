<?php

namespace BNS\App\TranslationBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Onesky\Api\Client;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;




/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class TranslationExtension extends Extension
{
    const DEFAULT_OUTPUT = '[dirname][filename].[locale].[extension]';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $locales = $container->getParameter('available_languages');

        $container->addDefinitions(array(
            'onesky_verification' => $this->getVerificationDefinition($config),
            'onesky_client'     => $this->getClientDefinition($config),
            'onesky_downloader' => $this->getDownloaderDefinition($config, $locales),
        ));
    }

    /**
     * @param $config
     *
     * @return Definition
     */
    private function getClientDefinition($config)
    {
        $client = new Definition('Onesky\Api\Client');
        $client->addMethodCall('setApiKey', array($config['api_key']));
        $client->addMethodCall('setSecret', array($config['secret']));
        return $client;
    }


    /**
     * @param $config
     *
     * @return Definition
     */
    private function getDownloaderDefinition($config, $locales)
    {
        $downloader = new Definition('BNS\App\TranslationBundle\Onesky\Downloader', array(
            new Reference('onesky_client'),
        ));

        foreach ($config['mappings'] as $mappingConfig) {
            $downloader->addMethodCall('addMapping', array(new Definition(
                'BNS\App\TranslationBundle\Onesky\Mapping',
                array(
                    isset($mappingConfig['sources']) ? $mappingConfig['sources'] : array(),
                    $locales ? $locales : array(),
                    isset($mappingConfig['output'])  ? $mappingConfig['output'] : self::DEFAULT_OUTPUT,
                )
            )));
        }
        return $downloader;
    }

    /**
     * @param $config
     *
     * @return Definition
     */
    private function getVerificationDefinition($config)
    {
        return new Definition('BNS\App\TranslationBundle\Onesky\Verification');
    }
}

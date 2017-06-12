<?php

namespace BNS\App\CampaignBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSAppCampaignExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ($container->hasDefinition('bns_app_campaign.campaign_message_consumer')) {
            $messageConsumerDefinition = $container->getDefinition('bns_app_campaign.campaign_message_consumer');
            $services = array();
            foreach ($container->findTaggedServiceIds('bns_campaign_sender') as $id => $definition) {
                $services[] = new Reference($id);
            }
            $messageConsumerDefinition->replaceArgument(1, $services);
        }
    }
}

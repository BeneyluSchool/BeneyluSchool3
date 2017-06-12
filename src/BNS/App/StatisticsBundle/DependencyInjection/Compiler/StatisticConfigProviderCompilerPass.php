<?php
namespace BNS\App\StatisticsBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class StatisticConfigProviderCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('bns.statistic_manager')) {
            return;
        }

        $definition = $container->findDefinition('bns.statistic_manager');
        $taggedServices = $container->findTaggedServiceIds('bns_statistic.statistic_config_provider');

        foreach ($taggedServices as $id => $tag) {
            // We add all services tagged with "bns_statistic.statistic_config_provider"
            // to "bns.statistic_manager"
            $definition->addMethodCall('addStatistic', array(new Reference($id)));
        }
    }

}

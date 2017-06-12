<?php
namespace BNS\App\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CustomRuntimeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('templating.asset.default_package')) {
            $package = $container->getDefinition('templating.asset.default_package');

            $arguments = $package->getArgument(0);
            foreach ($arguments as $key => $arg) {
                if (is_string($arg) && 0 === strpos($arg, '@')) {
                    $arguments[$key] = new Reference(substr($arg, 1));
                }
            }
            $package->replaceArgument(0, $arguments);
        }
    }
}

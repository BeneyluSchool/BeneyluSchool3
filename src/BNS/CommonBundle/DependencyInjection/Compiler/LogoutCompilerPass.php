<?php
namespace BNS\CommonBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LogoutCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('security.firewall.map') && $container->hasDefinition('bns_common.security_logout.logout')) {
            $securityDef = $container->getDefinition('security.firewall.map');
            $map = $securityDef->getArgument(1);

            $options = [];
            foreach ($map as $key => $val) {
                $name = str_replace('security.firewall.map.context.', '', $key);
                if ($container->hasDefinition('security.authentication.rememberme.services.persistent.' . $name)) {
                    $def = $container->getDefinition('security.authentication.rememberme.services.persistent.' . $name);
                    $options = $def->getArgument(3);
                    break;
                }
            }
            $logoutDef = $container->getDefinition('bns_common.security_logout.logout');
            $logoutDef->replaceArgument(3, $options);
        }
    }

}

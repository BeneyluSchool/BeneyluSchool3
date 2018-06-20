<?php
namespace BNS\App\CoreBundle;

use BNS\App\CoreBundle\DependencyInjection\Compiler\BuzzTestCompilerPass;
use BNS\App\CoreBundle\DependencyInjection\Compiler\CustomRuntimeCompilerPass;

use BNS\App\CoreBundle\DependencyInjection\Compiler\ExpressionFunctionCompilerPass;
use BNS\App\CoreBundle\DependencyInjection\Compiler\GuzzleTestCompilerPass;
use BNS\App\CoreBundle\DependencyInjection\Compiler\JMSSetLoggerCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppCoreBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CustomRuntimeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new ExpressionFunctionCompilerPass());
        $container->addCompilerPass(new JMSSetLoggerCompilerPass());

        if ('app_test' === $container->getParameter('kernel.environment')) {
            $container->addCompilerPass(new BuzzTestCompilerPass());
            $container->addCompilerPass(new GuzzleTestCompilerPass());
        }
    }
}

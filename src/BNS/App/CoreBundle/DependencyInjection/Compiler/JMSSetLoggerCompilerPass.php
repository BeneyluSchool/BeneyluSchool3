<?php

namespace BNS\App\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class JMSSetLoggerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('jms_translation.extractor.file.form_extractor')) {
            $package = $container->getDefinition('jms_translation.extractor.file.form_extractor');

            $package->addMethodCall("setLogger", array(new Reference("logger")));
        }
    }
}

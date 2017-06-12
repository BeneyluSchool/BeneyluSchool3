<?php

namespace BNS\App\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ExpressionFunctionCompilerPass
 *
 * @package BNS\App\CoreBundle\DependencyInjection\Compiler
 */
class ExpressionFunctionCompilerPass implements CompilerPassInterface
{

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $expressionEvaluator = $container->getDefinition('hateoas.expression.evaluator');

        foreach ($container->findTaggedServiceIds('hateoas.expression_function') as $id => $tags) {
            $expressionEvaluator->addMethodCall('registerFunction', array(new Reference($id)));
        }
    }

}

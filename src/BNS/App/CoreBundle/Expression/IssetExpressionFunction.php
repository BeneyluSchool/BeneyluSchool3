<?php

namespace BNS\App\CoreBundle\Expression;

use Hateoas\Expression\ExpressionFunctionInterface;

/**
 * Class IssetExpressionFunction
 *
 * @package BNS\App\CoreBundle\Expression
 */
class IssetExpressionFunction implements ExpressionFunctionInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'isset';
    }

    /**
     * {@inheritDoc}
     */
    public function getCompiler()
    {
        return function ($str) {
            if (!is_string($str)) {
                return $str;
            }

            return sprintf('isset($context[%s])', $str);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getEvaluator()
    {
        return function ($context, $str) {
            if (!is_string($str)) {
                return $str;
            }

            return isset($context[$str]);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getContextVariables()
    {
        return array();
    }
}

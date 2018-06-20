<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\LsuBundle\Model\om\BaseLsuDomain;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class LsuDomain extends BaseLsuDomain
{
    public function validateDomainWithCode(ExecutionContextInterface $context) {
        if (!$this->getCode()) {
            $context->buildViolation('ERROR_INVALID_DOMAIN')
                ->atPath('lsuDomain')
                ->addViolation();
        }
    }
}

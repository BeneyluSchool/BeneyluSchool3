<?php

namespace BNS\App\PaasBundle\Manager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Eymeric Taelman
 * Classe permettant la sécu des urls du PAAS accessibles
 */
class RequestCheckManager
{
    public function __construct($secretKey)
    {
        $this->globalSecretKey = $secretKey;
    }


}

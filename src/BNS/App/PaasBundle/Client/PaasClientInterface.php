<?php

namespace BNS\App\PaasBundle\Client;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface PaasClientInterface
{
    /**
     * The id of the client or the username (for type user)
     *
     * @return int|string The id of the client or the username (for type user)
     */
    public function getPaasIdentifier();

    /**
     * The type of client (USER|CLASSROOM|SCHOOL)
     *
     * @return string The type of client (USER|CLASSROOM|SCHOOL)
     */
    public function getPaasType();
}

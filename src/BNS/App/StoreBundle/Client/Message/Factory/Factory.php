<?php

namespace BNS\App\StoreBundle\Client\Message\Factory;

use BNS\App\StoreBundle\Client\Message\Response;
use Buzz\Message\Factory\Factory as BaseFactory;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class Factory extends BaseFactory
{
    /**
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
   public function createResponse()
   {
       return new Response();
   }
}
<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;

class Status
{

    /**
     * @Type("integer")
     */
    public $code;
    /**
     * @Type("string")
     */
    public $mnemo;

}
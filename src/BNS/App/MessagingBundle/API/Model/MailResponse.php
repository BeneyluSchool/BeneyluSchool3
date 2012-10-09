<?php

namespace BNS\App\MessagingBundle\API\Model;

use BNS\App\MessagingBundle\API\Model\Folder;
use BNS\App\MessagingBundle\API\Model\Response;
use JMS\SerializerBundle\Annotation\Type;
use JMS\SerializerBundle\Annotation\SerializedName;

class MailResponse extends Response
{
    /**
     * @Type("BNS\App\MessagingBundle\API\Model\Mail")
     */
    public $mail;

}
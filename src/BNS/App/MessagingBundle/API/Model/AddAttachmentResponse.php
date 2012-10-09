<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

class AddAttachmentResponse extends Response
{
    /**
     * @Type("BNS\App\MessagingBundle\API\Model\Attachment")
     */
    public $attachment;
}
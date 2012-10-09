<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

class SaveAsDraftResponse extends Response
{

    /**
     * @Type("BNS\App\MessagingBundle\API\Model\MessageId")
     */
    public $id;
}

?>

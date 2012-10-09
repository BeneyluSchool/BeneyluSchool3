<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use JMS\SerializerBundle\Annotation\SerializedName;

class MessageId
{

    /**
     * @Type("string")
     */
    public $folder;

    /**
     * @Type("integer")
     * @SerializedName("msgId")
     */
    public $msgId;

}

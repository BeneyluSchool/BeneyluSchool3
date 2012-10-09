<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

class CheckAttachmentUploadResponse extends Response
{
    /**
     * @Type("integer")
     * @SerializedName("uploadProgress")
     */
    public $uploadProgress;
}
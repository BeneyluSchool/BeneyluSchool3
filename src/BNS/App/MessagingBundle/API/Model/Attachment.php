<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

class Attachment 
{
    /**
     * @Type("string")
     * @SerializedName("attachmentName")
     */
    public $attachmentName;
    
    /**
     * @Type("string")
     * @SerializedName("attachmentLink")
     */
    public $attachmentLink;
    
    /**
     * @Type("string")
     * @SerializedName("attachmentSize")
     */
    public $attachmentSize;
    
    /**
     * @Type("string")
     * @SerializedName("mimeType")
     */
    public $mimeType;
    
    /**
     * @Type("integer")
     */
    public $rank;


}
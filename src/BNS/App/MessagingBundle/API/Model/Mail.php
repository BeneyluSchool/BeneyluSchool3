<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

/**
 * Description of Mail
 *
 * @author a186105
 */
class Mail
{
    /**
     * @Type("array<BNS\App\MessagingBundle\API\Model\Attachment>")
     */
    public $attachment;
    
    /**
     * @Type("string")
     */
    public $message;
    
    /**
     * @Type("BNS\App\MessagingBundle\API\Model\MailHeader")
     */
    public $header;
    
}

?>

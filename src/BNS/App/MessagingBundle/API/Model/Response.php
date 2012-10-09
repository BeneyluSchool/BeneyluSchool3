<?php
namespace BNS\App\MessagingBundle\API\Model;

use BNS\App\MessagingBundle\API\Model\Status;
use JMS\SerializerBundle\Annotation\Type;

class Response
{
    /**
     * @Type("BNS\App\MessagingBundle\API\Model\Status")
     */
    public $status;

    public function setStatus(Status $status)
    {
        $this->status = $status;
    }

}
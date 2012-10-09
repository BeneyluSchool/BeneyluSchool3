<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use JMS\SerializerBundle\Annotation\SerializedName;

class Folder
{

    /**
     * @Type("string")
     */
    public $name;

    /**
     * @Type("string")
     * @SerializedName("functionalName")
     */
    public $functionalName;

    /**
     * @Type("string")
     * @SerializedName("fatherFunctionalName")
     */
    public $fatherFunctionalName;
    
    /**
     * @Type("array<BNS\App\MessagingBundle\API\Model\Folder>")
     */
    public $childs;

    /**
     * @Type("integer")
     */
    public $size;

    /**
     * @Type("integer")
     * @SerializedName("totalNbMessage")
     */
    public $totalNbMessage;

    /**
     * @Type("integer")
     * @SerializedName("unreadMessage")
     */
    public $unreadMessage;

    /**
     * @Type("array<string, integer>")
     * @SerializedName("nbUnreadByType")
     */
    public $nbUnreadByType;

}

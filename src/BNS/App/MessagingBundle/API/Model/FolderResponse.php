<?php

namespace BNS\App\MessagingBundle\API\Model;

use BNS\App\MessagingBundle\API\Model\Folder;
use BNS\App\MessagingBundle\API\Model\Response;
use JMS\SerializerBundle\Annotation\Type;
use JMS\SerializerBundle\Annotation\SerializedName;

class FolderResponse extends Response
{
    /**
     * @Type("array<BNS\App\MessagingBundle\API\Model\Folder>")
     * @SerializedName("folderSystem")
     */
    public $folderSystem;
    /**
     * @Type("array<BNS\App\MessagingBundle\API\Model\Folder>")
     * @SerializedName("folderUser")
     */
    public $folderUser;


}
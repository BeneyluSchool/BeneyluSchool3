<?php
namespace BNS\App\MessagingBundle\API\Model;


use BNS\App\MessagingBundle\API\Model\Response;
use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MailHeader;
use JMS\SerializerBundle\Annotation\SerializedName;


class SearchResponse extends Response
{

    /**
     * @Type("integer")
     * @SerializedName("nbMails")
     */
    public $nbMails;

    /**
     * @Type("integer")
     * @SerializedName("nbUnreadMessage")
     */
    public $nbUnreadMessage;

    /**
     * @Type("array<BNS\App\MessagingBundle\API\Model\MailHeader>")
     * @SerializedName("mailHeader")
     */
    public $mailHeader;

    /**
     * @Type("integer")
     * @SerializedName("sortBy")
     */
    public $sortBy;
    
    /**
     * Calculé au retour de la réponse
     * @var type 
     */
    public $nbPage;

}
<?php

namespace BNS\App\MessagingBundle\API\Model;

use JMS\SerializerBundle\Annotation\Type;
use BNS\App\MessagingBundle\API\Model\MessageId;
use JMS\SerializerBundle\Annotation\SerializedName;

class MailHeader
{

    /**
     * @Type("BNS\App\MessagingBundle\API\Model\MessageId")
     */
    public $id;

    /**
     * @Type("integer")
     */
    public $priority;

    /**
     * @Type("string")
     */
    public $subject;

    /**
     * @Type("string")
     */
    public $from;

    /**
     * @Type("string")
     */
    public $to;

    /**
     * @Type("string")
     */
    public $cc;

    /**
     * @Type("string")
     * @SerializedName("replyTo")
     */
    public $replyTo;

    /**
     * @Type("string")
     * @SerializedName("phoneTo")
     */
    public $phoneTo;

    /**
     * @Type("integer")
     */
    public $size;

    /**
     * @Type("string")
     */
    public $date;

    /**
     * @Type("boolean")
     */
    public $unread;

    /**
     * @Type("boolean")
     */
    public $flagged;

    /**
     * @Type("boolean")
     */
    public $replied;

    /**
     * @Type("boolean")
     */
    public $forwarded;

    /**
     * @Type("string")
     * @SerializedName("spamRating")
     */
    public $spamRating;

    /**
     * @Type("boolean")
     * @SerializedName("hasAttachment")
     */
    public $hasAttachment;

    /**
     * @Type("boolean")
     * @SerializedName("isCalendarEvent")
     */
    public $isCalendarEvent;

    /**
     * @Type("array<string>")
     * @SerializedName("flagsList")
     */
    public $flagsList;

    /**
     * @Type("string")
     */
    public $context;

    /**
     * @Type("string")
     * @SerializedName("iCalMethod")
     */
    public $iCalMethod;

}
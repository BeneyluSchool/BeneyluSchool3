<?php

namespace BNS\App\ClassroomBundle\DataReset\Exception;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class DataResetException extends \RuntimeException
{
    /**
     * @var string 
     */
    private $viewMessage;

    /**
     * @param string $message
     * @param string $viewMessage
     */
    public function __construct($message, $viewMessage)
    {
        parent::__construct($message);
    }

    /**
     * @return string
     */
    public function getViewMessage()
    {
        return $this->viewMessage;
    }
}
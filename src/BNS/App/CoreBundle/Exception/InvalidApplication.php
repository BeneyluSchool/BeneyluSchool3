<?php
namespace BNS\App\CoreBundle\Exception;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class InvalidApplication extends \InvalidArgumentException
{
    public $applicationName;

    public function __construct($applicationName)
    {
        $this->applicationName = $applicationName;
        $message = sprintf("Invalid application (%s)", $this->applicationName);

        return parent::__construct($message);
    }

}

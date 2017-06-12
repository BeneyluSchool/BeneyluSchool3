<?php
namespace BNS\App\CoreBundle\Exception;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class InvalidUninstallApplication extends \InvalidArgumentException
{
    public $baseApplication;

    public $applicationName;

    public function __construct($applicationName, $baseApplication = false)
    {
        $this->baseApplication = $baseApplication;
        $this->applicationName = $applicationName;

        if ($baseApplication) {
            $message = sprintf("Can't uninstall base application (%s)", $this->applicationName);
        } else {
            $message = sprintf("Invalid application name (%s)", $this->applicationName);
        }

        return parent::__construct($message);
    }

}

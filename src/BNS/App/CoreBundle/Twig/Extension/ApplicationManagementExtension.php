<?php

namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Application\ApplicationManager;

/**
 * Class MaterialExtension
 *
 * @package BNS\App\CoreBundle\Twig\Extension
 */
class ApplicationManagementExtension extends \Twig_Extension
{

    /**
     * @var ApplicationManager
     */
    private $applicationManager;

    public function __construct(ApplicationManager $applicationManager)
    {
        $this->applicationManager = $applicationManager;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('hasApplicationManagement', array($this, 'hasApplicationManagement')),
            new \Twig_SimpleFunction('isAllowedApplication', array($this, 'isAllowedApplication')),
        );
    }

    public function hasApplicationManagement()
    {
        return $this->applicationManager->isEnabled();
    }

    public function isAllowedApplication($name, $group)
    {
        return $this->applicationManager->isAllowedApplicationName($name, $group);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'application_management_extension';
    }

}

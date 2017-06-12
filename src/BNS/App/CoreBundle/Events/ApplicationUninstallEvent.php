<?php
namespace BNS\App\CoreBundle\Events;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\Module;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApplicationUninstallEvent extends Event
{
    /**
     * @var Module
     */
    protected $application;

    /**
     * @var Group
     */
    protected $group;

    public function __construct(Module $application, Group $group)
    {
        $this->application = $application;
        $this->group = $group;
    }

    /**
     * @return Module
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->group;
    }


}

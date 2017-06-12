<?php
namespace BNS\App\CoreBundle\Events;

use BNS\App\CoreBundle\Model\Activity;
use BNS\App\CoreBundle\Model\Group;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ActivityUninstallEvent extends Event
{
    /**
     * @var Activity
     */
    protected $activity;

    /**
     * @var Group
     */
    protected $group;

    public function __construct(Activity $activity, Group $group)
    {
        $this->activity = $activity;
        $this->group = $group;
    }

    /**
     * @return Activity
     */
    public function getActivity()
    {
        return $this->activity;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->group;
    }
}

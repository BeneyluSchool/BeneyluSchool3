<?php
namespace BNS\App\PaasBundle\Activities;

use BNS\App\CoreBundle\Events\ActivityUninstallEvent;
use BNS\App\CoreBundle\Events\BnsEvents;
use BNS\App\CoreBundle\Model\Activity;
use BNS\App\CoreBundle\Model\ActivityQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupActivity;
use BNS\App\CoreBundle\Model\GroupActivityQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ActivityManager
{
    /** @var EventDispatcherInterface  */
    protected $eventDispatcher;

    /** @var LoggerInterface  */
    protected $logger;

    public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Return the list of activities
     * @param Group $group
     *
     * @return array|mixed|\PropelObjectCollection|GroupActivity[]
     */
    public function getActivities(Group $group)
    {
        $groupActivities = GroupActivityQuery::create()
            ->filterByGroup($group)
            ->filterByUninstalled(false)
            ->joinWith('Activity')
            ->find()
        ;

        return $groupActivities;
    }

    /**
     * Return the list of activities
     * @param string $uniqueName
     * @param Group $group
     *
     * @return GroupActivity
     */
    public function getActivity($uniqueName, Group $group)
    {
        $groupActivity = GroupActivityQuery::create()
            ->useActivityQuery()
                ->filterByUniqueName($uniqueName)
            ->endUse()
            ->filterByGroup($group)
            ->filterByUninstalled(false)
            ->with('Activity')
            ->findOne()
        ;

        return $groupActivity;
    }

    public function open(Activity $activity, Group $group)
    {
        return $this->changeState($activity, $group, true);
    }

    public function close(Activity $activity, Group $group)
    {
        return $this->changeState($activity, $group, false);
    }

    /**
     * Used by the paas to create / install an activity for a given $group
     *
     * @param $uniqueName
     * @param $offerData
     * @param Group $group
     */
    public function install($uniqueName, $offerData, Group $group)
    {
        $activity = ActivityQuery::create()
            ->filterByUniqueName($uniqueName)
            ->findOneOrCreate()
            ;

        // we update the label / offerData
        $activity->setLabel(isset($offerData['name']) ? $offerData['name'] : $uniqueName);
        $activity->setOfferData($offerData);
        if (isset($offerData['image_url'])) {
            $activity->setImageUrl($offerData['image_url']);
        }
        $activity->save();

        // we install the activity for the group
        $groupActivity = GroupActivityQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByActivityId($activity->getId())
            ->findOneOrCreate();

        // reset field if it was uninstalled before
        $groupActivity->setUninstalled(false);
        $groupActivity->setExpired(false);

        $groupActivity->save();

        $this->logger->debug(sprintf('[Activity manager] install activity "%s" for group "%s" ', $uniqueName, $group->getId()));
    }


    /**
     * flag a group activity to uninstalled or do nothing if not installed
     *
     * @param Activity $activity
     * @param Group $group
     * @throws \Exception
     * @throws \PropelException
     */
    public function uninstall(Activity $activity, Group $group)
    {
        $groupActivity = GroupActivityQuery::create()
            ->filterByActivity($activity)
            ->filterByGroup($group)
            ->findOne();

        if ($groupActivity) {
            $groupActivity->setUninstalled(true);
            $groupActivity->save();
        }

        $this->logger->debug(sprintf('[Activity manager] uninstall activity "%s" for group "%s"%s',
            $activity->getUniqueName(),
            $group->getId(),
            ($groupActivity ? '' : ' (was not installed)')
        ));

        // send event to notify of an uninstallation (for paas)
        $this->eventDispatcher->dispatch(BnsEvents::ACTIVITY_UNINSTALL, new ActivityUninstallEvent($activity, $group));
    }

    /**
     * decorate the GroupActivity object for the API
     *
     * @param GroupActivity $groupActivity
     * @param $userGroupRights
     */
    public function decorate(GroupActivity $groupActivity, $userGroupRights)
    {
        if (in_array('MAIN_ACTIVITY_ACCESS', $userGroupRights['permissions']) || $groupActivity->getOpened()) {
            $groupActivity->hasAccessFront = true;
        }
        if (in_array('MAIN_ACTIVITY_ACTIVATION', $userGroupRights['permissions'])) {
            $groupActivity->canOpen = true;
            $groupActivity->isUninstallable = true;
        }
    }

    protected function changeState(Activity $activity, Group $group, $state)
    {
        $groupActivity = GroupActivityQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByActivityId($activity->getId())
            ->findOneOrCreate()
        ;
        $groupActivity->setOpened((bool) $state);

        return $groupActivity->save();
    }
}

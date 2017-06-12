<?php

namespace BNS\App\NotificationBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\NotificationBundle\Model\NotificationQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearNotificationDataReset extends AbstractDataReset
{
    /** @var BNSGroupManager $this->groupManager */
    protected $groupManager;

    public function __construct($container)
    {
        $this->groupManager = $container->get('bns.classroom_manager');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'change_year_notification';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        NotificationQuery::create('n')
            ->where('n.GroupId = ?', $group->getId())
        ->delete();

        /** @var BNSGroupManager $this->groupManager */
        $this->groupManager->setGroup($group);

        // On supprime toutes les notifications pour tous les utilisateurs de la classe
        foreach($this->groupManager->getUsers() as $user)
        {
            NotificationQuery::create()->filterByTargetUserId($user->getId())->delete();
        }
    }
}
<?php

namespace BNS\App\NotificationBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearNotificationDataReset extends AbstractDataReset
{
    /** @var BNSGroupManager $this->groupManager */
    protected $groupManager;

    /** @var \BNS\App\NotificationBundle\Manager\NotificationManager|object  */
    protected $notificationManager;

    /**
     * TODO inject normal services
     * ChangeYearNotificationDataReset constructor.
     * @param ContainerInterface $container
     */
    public function __construct($container)
    {
        $this->groupManager = $container->get('bns.classroom_manager');
        $this->notificationManager = $container->get('notification_manager');

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
            $this->notificationManager->clearNotificationCache($user);
        }
    }
}

<?php

namespace BNS\App\MiniSiteBundle\Command;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNewsQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\NotificationBundle\Notification\MinisiteBundle\MinisitePageNewsPublishedNotification;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCityNewsCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('bns:minisite:notify-city-news')
            ->setDescription('Send notifications for city news')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        if (!BNSAccess::getContainer()) {
            BNSAccess::setContainer($container);
        }
        $groupManager = $container->get('bns.group_manager');
        $notificationManager = $container->get('notification_manager');

        /** @var MiniSitePageCityNews[] $allNews */
        $allNews = MiniSitePageCityNewsQuery::create()
            ->filterByStatus(MiniSitePageNewsPeer::STATUS_PUBLISHED)
            ->filterByPublishedAt(time(), \Criteria::LESS_THAN)
            ->filterByHasNotified(false)
            ->find()
        ;

        foreach ($allNews as $news) {
            foreach($news->getSchools() as $school) {
                $users = $groupManager->setGroup($school)->getUsers();
                $notificationManager->send($users, new MinisitePageNewsPublishedNotification($container, $news->getId(), $school->getId()));
            }
        }
    }

}

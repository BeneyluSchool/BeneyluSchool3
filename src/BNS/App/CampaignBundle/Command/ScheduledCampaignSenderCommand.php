<?php
namespace BNS\App\CampaignBundle\Command;

use BNS\App\CampaignBundle\Model\CampaignPeer;
use BNS\App\CampaignBundle\Model\CampaignQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ScheduledCampaignSenderCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('bns:campaign:send-scheduled')
            ->setDescription('Send scheduled campaign that need to be sent. Should run every minute')
            ->setHelp('Send scheduled campaign')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $campaignProducer = $this->getContainer()->get('old_sound_rabbit_mq.campaign_producer');
        $logger = $this->getContainer()->get('logger');

        $campaigns = CampaignQuery::create()
            ->filterByStatus(CampaignPeer::STATUS_SCHEDULED)
            ->filterByScheduledAt(null, \Criteria::ISNOTNULL)
            ->filterByScheduledAt('now', \Criteria::LESS_EQUAL)
            ->orderByScheduledAt(\Criteria::ASC)
            ->limit(100)
            ->find()
            ;

        foreach ($campaigns as $campaign) {
            $campaign->setStatus(CampaignPeer::STATUS_WAITING);
            $campaign->save();
            $campaignProducer->publish(serialize([
                'campaign_id' => $campaign->getId()
            ]));
            $logger->info(sprintf('Campaign "%s" sent', $campaign->getId()));
        }
    }
}

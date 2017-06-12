<?php
namespace BNS\App\CampaignBundle\Consumer;

use BNS\App\CampaignBundle\Model\CampaignPeer;
use BNS\App\CampaignBundle\Model\CampaignQuery;
use BNS\App\CampaignBundle\Model\CampaignRecipientQuery;
use BNS\App\CampaignBundle\Sender\CampaignSenderInterface;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CampaignMessageConsumer implements ConsumerInterface
{
    /** @var int  */
    protected $lastSend = 0;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var CampaignSenderInterface[]|array  */
    protected $campaignSenders;

    public function __construct(
        LoggerInterface $logger,
        array $campaignSenders = array()
    ) {
        $this->logger = $logger;

        foreach ($campaignSenders as $sender) {
            if (!$sender instanceof CampaignSenderInterface) {
                throw new \InvalidArgumentException('CampaignSeder should implement "BNS\App\CampaignBundle\Sender\CampaignSenderInterface"');
            }
        }

        $this->campaignSenders = $campaignSenders;
    }

    public function execute(AMQPMessage $msg)
    {
        \Propel::disableInstancePooling();
        CampaignPeer::clearInstancePool();
        UserPeer::clearInstancePool();
        try {
            if (time() - $this->lastSend > 3600) {
                \Propel::close();
            }
            $message = @unserialize($msg->body);
            if (false === $message || !isset($message['campaign_id'])) {
                $this->logger->error('CampaignMessageConsumer invalid message', ['msg' => $msg]);

                return;
            }

            $campaign = CampaignQuery::create()->filterByStatus(CampaignPeer::STATUS_PENDING)->findPk($message['campaign_id']);
            if (!$campaign) {
                $this->logger->error(sprintf('CampaignMessageConsumer invalid campaign "%s"', $message['campaign_id']), ['msg' => $msg]);

                return;
            }

            $users = UserQuery::create()
                ->filterByArchived(false)
                ->filterById($message['recipient_ids'])
                ->useCampaignRecipientQuery()
                    ->filterByCampaignId($campaign->getId())
                    ->filterByIsSent(false)
                ->endUse()
                ->find()
            ;
            if (0 === $users->count()) {
                $this->logger->error(sprintf('CampaignMessageConsumer invalid campaign "%s" no recipients', $message['campaign_id']), ['msg' => $msg]);

                return;
            }

            $supported = false;
            // TODO handle individual failure
            foreach ($this->campaignSenders as $sender) {
                // send message based on campaign type
                if ($sender->support($campaign)) {
                    $supported = true;
                    $sender->send($campaign, $users);
                    break;
                }
            }

            if (!$supported) {
                $this->logger->error('CampaignMessageConsumer unsupported campaign type', [
                    'campaign_id' => $campaign->getId(),
                ]);
                return;
            }

            // update recipient status
            CampaignRecipientQuery::create()
                ->filterByCampaignId($campaign->getId())
                ->filterByUser($users)
                ->update(array('IsSent' => true))
            ;

            // update campaign status
            $count = CampaignRecipientQuery::create()
                ->filterByCampaignId($campaign->getId())
                ->filterByIsDuplicate(false)
                ->filterByIsSent(false)
                ->count()
            ;
            if (0 === $count) {
                $campaign->setSentAt(new \DateTime());
                $campaign->setStatus(CampaignPeer::STATUS_SENT);
                $campaign->save();
            }

            return true;
        } catch(\PropelException $e) {
            $this->logger->error(sprintf('ERROR CampaignMessageConsumer Propel error "%s", caused by "%s"', $e->getMessage(), $e->getCause()));
            \Propel::close();

            return false;
        } catch (\Exception $e) {
            $this->logger->error('ERROR CampaignMessageConsumer: ' . $e->getMessage());
        }

        return false;
    }
}

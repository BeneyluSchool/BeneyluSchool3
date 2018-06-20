<?php
namespace BNS\App\CampaignBundle\Consumer;

use BNS\App\CampaignBundle\Manager\CampaignManager;
use BNS\App\CampaignBundle\Model\CampaignPeer;
use BNS\App\CampaignBundle\Model\CampaignQuery;
use BNS\App\CampaignBundle\Model\CampaignRecipientQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CampaignConsumer implements ConsumerInterface
{
    /** @var int  */
    protected $lastSend = 0;

    /** @var int  */
    protected $batchLimit = 20;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var CampaignManager  */
    protected $campaignManager;

    public function __construct(LoggerInterface $logger, CampaignManager $campaignManager)
    {
        $this->logger = $logger;
        $this->campaignManager = $campaignManager;
    }

    public function execute(AMQPMessage $msg)
    {
        \Propel::disableInstancePooling();
        CampaignPeer::clearInstancePool();
        CampaignPeer::clearRelatedInstancePool();
        UserPeer::clearInstancePool();
        UserPeer::clearRelatedInstancePool();
        try {
            if (time() - $this->lastSend > 3600) {
                \Propel::close();
            }
            $message = @unserialize($msg->body);
            if (false === $message || !isset($message['campaign_id'])) {
                $this->logger->error('CampaignConsumer invalid message', ['msg' => $msg]);

                return self::MSG_REJECT;
            }

            $campaign = CampaignQuery::create()->filterByStatus(CampaignPeer::STATUS_WAITING)->findPk($message['campaign_id']);

            if (!$campaign) {
                $this->logger->error(sprintf('CampaignConsumer invalid campaign "%s"', $message['campaign_id']), ['msg' => $msg]);

                return self::MSG_REJECT;
            }

            // prepare recipients
            $this->campaignManager->prepareRecipients($campaign);

            // create Campaign message batch (X recipient per batch)
            $recipients = [];
            $i = 0;
            foreach ($campaign->getCampaignRecipients(CampaignRecipientQuery::create()->filterByIsDuplicate(false)) as $recipient) {
                $recipients[] = $recipient;
                $i++;

                if ($i >= $this->batchLimit) {
                    $this->campaignManager->sendMessage($campaign, $recipients);
                    $recipients = [];
                    $i = 0;
                }
            }
            if (count($recipients) > 0) {
                $this->campaignManager->sendMessage($campaign, $recipients);
            }

            $campaign->setStatus(CampaignPeer::STATUS_PENDING);
            $campaign->save();

            $this->lastSend = time();

            return self::MSG_ACK;
        } catch(\PropelException $e) {
            $this->logger->error(sprintf('ERROR CampaignConsumer Propel error "%s", caused by "%s"', $e->getMessage(), $e->getCause()));
            \Propel::close();

            return self::MSG_REJECT_REQUEUE;
        } catch (\Exception $e) {
            $this->logger->error('ERROR CampaignConsumer: ' . $e->getMessage());
        }

        // reject message with error
        return self::MSG_REJECT;
    }
}

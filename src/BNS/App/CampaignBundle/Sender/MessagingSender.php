<?php
namespace BNS\App\CampaignBundle\Sender;

use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CampaignBundle\Model\CampaignMessaging;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageConversation;
use BNS\App\MessagingBundle\Model\MessagingMessagePeer;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MessagingSender implements CampaignSenderInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function send(Campaign $campaign, $users)
    {
        $con = \Propel::getConnection(MessagingMessagePeer::DATABASE_NAME);

        $con->beginTransaction();
        try {
            $messagingMessage = new MessagingMessage();
            $messagingMessage->setStatus(BNSMessageManager::$messagesStatus['CAMPAIGN']);
            $messagingMessage->setGroupId($campaign->getGroupId());
            $messagingMessage->setContent($campaign->getMessage());
            $messagingMessage->setSubject($campaign->getTitle());
            $messagingMessage->save($con);

            // handle attachment
            foreach ($campaign->getResourceAttachments() as $attachment) {
                $messagingMessage->addResourceAttachment($attachment->getId());
            }

            foreach ($users as $user) {
                $messagingConversation = new MessagingConversation();
                $messagingConversation->setMessageParentId($messagingMessage->getId());
                $messagingConversation->setUserId($user->getId());
                $messagingConversation->setUserWithId($user->getId());
                $messagingConversation->setStatus(BNSMessageManager::$messagesConversationStatus['CAMPAIGN']);
                $messagingConversation->save($con);

                $messagingMessageConversation = new MessagingMessageConversation();
                $messagingMessageConversation->setConversationId($messagingConversation->getId());
                $messagingMessageConversation->setMessageId($messagingMessage->getId());
                $messagingMessageConversation->save($con);
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            $this->logger->error('MessagingSender : an error occured while sending the message', [
                'campaign_id' => $campaign->getId(),
                'users' => $users,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function support(Campaign $campaign)
    {
        return $campaign instanceOf CampaignMessaging;
    }
}

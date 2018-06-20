<?php

namespace BNS\App\UserBundle\Consumer;

use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\NotificationBundle\Manager\NotificationManager;
use BNS\App\UserBundle\Manager\AccountMergeManager;
use BNS\App\UserBundle\Model\UserMergePeer;
use BNS\App\UserBundle\Model\UserMergeQuery;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class AccountMergeConsumer implements ConsumerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AccountMergeManager
     */
    private $accountMergeManager;

    /**
     * @var NotificationManager
     */
    private $notificationManager;


    public function __construct(LoggerInterface $logger, AccountMergeManager $accountMergeManager, NotificationManager $notificationManager)
    {
        $this->logger = $logger;
        $this->accountMergeManager = $accountMergeManager;
        $this->notificationManager = $notificationManager;
    }

    /**
     * @param AMQPMessage $msg The message
     * @return int|boolean
     */
    public function execute(AMQPMessage $msg)
    {
        UserPeer::clearInstancePool();
        UserPeer::clearRelatedInstancePool();
        UserMergePeer::clearInstancePool();
        try {
            $data = json_decode($msg->body, true);
            if (!$data || !is_array($data) || !isset($data['id'])) {
                $this->logger->error('[ACCOUNT_MERGE] invalid payload ', [
                    'data' => $data
                ]);

                return ConsumerInterface::MSG_REJECT;
            }
            $userMerge = UserMergeQuery::create()->findPk($data['id']);
            if ($userMerge && in_array($userMerge->getStatus(), [UserMergePeer::STATUS_NEW, UserMergePeer::STATUS_CURRENT])) {
                $res = $this->accountMergeManager->executeMergeRequest($userMerge);
                if ($res) {
                   return ConsumerInterface::MSG_ACK;
               } elseif (null === $res) {
                    return ConsumerInterface::MSG_REJECT;
                }
            }
        } catch(\PropelException $e) {
            $this->logger->error(sprintf('[ACCOUNT_MERGE] ERROR : Propel error "%s", caused by "%s"', $e->getMessage(), $e->getCause()));
            \Propel::close();

            return self::MSG_REJECT_REQUEUE;
        } catch (\Exception $e) {
            $this->logger->error('[ACCOUNT_MERGE] ERROR: ' . $e->getMessage());

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

        return ConsumerInterface::MSG_REJECT;
    }
}

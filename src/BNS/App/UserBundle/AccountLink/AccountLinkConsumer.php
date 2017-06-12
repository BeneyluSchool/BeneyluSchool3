<?php

namespace BNS\App\UserBundle\AccountLink;

use BNS\App\CoreBundle\Model\GroupQuery;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Class AccountLinkConsumer
 *
 * @package BNS\App\UserBundle\AccountLink
 */
class AccountLinkConsumer implements ConsumerInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AccountLinkManager
     */
    private $manager;

    public function __construct(LoggerInterface $logger, AccountLinkManager $manager)
    {
        $this->logger = $logger;
        $this->manager = $manager;
    }

    /**
     * @param AMQPMessage $message
     * @throws \Exception
     */
    public function execute(AMQPMessage $message)
    {
        try {
            $data = json_decode($message->body, true);
            $this->manager->process($data);
        } catch (\Exception $e) {
            $this->logger->error('[ACCOUNT_LINK] ERROR: ' . $e->getMessage());
            throw $e;
        }
    }

}

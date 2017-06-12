<?php

namespace BNS\App\RealtimeBundle\Consumer;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\WorkshopBundle\Manager\LockManager;
use BNS\App\WorkshopBundle\Manager\WidgetGroupManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RealtimeConsumer
 *
 * @package BNS\App\RealtimeBundle\Consumer
 */
class RealtimeConsumer implements ConsumerInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var WidgetGroupManager
     */
    private $widgetGroupManager;

    /**
     * @var LockManager
     */
    private $lockManager;

    public function __construct(LoggerInterface $logger, WidgetGroupManager $widgetGroupManager, LockManager $lockManager, ContainerInterface $container)
    {
        $this->widgetGroupManager = $widgetGroupManager;
        $this->lockManager = $lockManager;
        $this->logger = $logger;

        if (!BNSAccess::getContainer()) {
            BNSAccess::setContainer($container);
        }
    }

    /**
     * @param AMQPMessage $message
     */
    public function execute(AMQPMessage $message)
    {
        try {
            $data = json_decode($message->body, true);
            if (isset($data['disconnect'])) {
                $userId = intval($data['disconnect']);
                if ($userId) {
                    $this->widgetGroupManager->applyUserDrafts($userId, true);
                    $this->lockManager->releaseAllLocks($userId, true);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception Realtime: ' . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->error('ERROR Realtime: ' . $e->getMessage());
        }
    }

}

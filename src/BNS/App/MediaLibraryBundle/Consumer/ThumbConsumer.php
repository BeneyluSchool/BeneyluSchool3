<?php
namespace BNS\App\MediaLibraryBundle\Consumer;

use BNS\App\MediaLibraryBundle\Thumb\ThumbCreatorManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbConsumer implements ConsumerInterface
{
    /** @var ThumbCreatorManager  */
    protected $thumbCreatorManager;

    /** @var LoggerInterface  */
    protected $logger;

    public function __construct(ThumbCreatorManager $thumbCreatorManager, LoggerInterface $logger)
    {
        $this->thumbCreatorManager = $thumbCreatorManager;
        $this->logger = $logger;
    }

    public function execute(AMQPMessage $msg)
    {
        $path = $msg->body;

        try {
            if ($this->thumbCreatorManager->createThumbFromPath($path)) {
                return ConsumerInterface::MSG_ACK;
            }

            $this->logger->error('ThumbConsumer : thumb creation failed', [
                'path' => $path
            ]);

        } catch (\Exception $exception) {
            $this->logger->error(sprintf('ThumbConsumer : thumb creation failed, exception : "%s"', $exception->getMessage()), [
                'path' => $path
            ]);
        } catch (\Error $error) {
            $this->logger->error(sprintf('ThumbConsumer : thumb creation failed, error : "%s"', $error->getMessage()), [
                'path' => $path
            ]);
        }

        // acknowledge even if this failed
        // TODO retry once
        return ConsumerInterface::MSG_ACK;
    }

}

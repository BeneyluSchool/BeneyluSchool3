<?php

namespace BNS\App\NotificationBundle\Consumer;

use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\TranslateFactory\NotificationTranslateFactory;
use BNS\App\CoreBundle\Model\User;
use BNS\App\NotificationBundle\Model\NotificationTypeQuery;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\CoreBundle\Access\BNSAccess;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 */
class NotificationConsumer implements ConsumerInterface
{
	/**
	 * @var \Symfony\Component\DependencyInjection\ContainerInterface
	 */
	private $container;

	/**
	 * @var \Symfony\Bridge\Monolog\Logger
	 */
	private $logger;

	/**
	 * @var int
	 */
	private $lastSend;

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct($container, $logger)
	{
		$this->container	= $container;
		$this->logger		= $logger;
		$this->lastSend		= 0;

		BNSAccess::setContainer($container);
	}

	/**
	 * @param AMQPMessage $message
	 */
	public function execute(AMQPMessage $message)
	{
		try {
            if (time() - $this->lastSend > 3600) {
                \Propel::close();
            }

            // prevent user from containing old data (locale)
            UserPeer::clearInstancePool();

            $parameters = unserialize($message->body);
            $notificationType = NotificationTypeQuery::create('nt')
                ->where('nt.UniqueName = ?', $parameters['notification_type_unique_name'])
                ->findOne();

            if (null == $notificationType) {
                throw new \InvalidArgumentException(
                    'The notification type with unique name : ' . $parameters['notification_type_unique_name'] . ' is NOT found !'
                );
            }

            // Retreive notification constructor parameters
            $attributes = array_merge(
                array(
                    'container' => $this->container
                ),
                unserialize($parameters['objects'])
            );
            $attributes['groupId'] = $parameters['group_id'];

            // Create notification object
            $object = new \ReflectionClass(
                NotificationTranslateFactory::getNamespace($parameters['notification_type_unique_name'], $notificationType->getModuleUniqueName())
            );
            /** @var Notification $notification */
            $notification = $object->newInstanceArgs($attributes);
            $notification->setDate($parameters['date']);
            $notification->setNotificationType($notificationType);

            if (isset($parameters['base_url'])) {
                $notification->setBaseUrl($parameters['base_url']);
            }

            // Disabled engines process
            if (isset($parameters['disabled_engines'])) {
                $notification->setDisabledEngines($parameters['disabled_engines']);
            }

            // Multiple targets
            if (isset($parameters['targets_user_id'])) {
                $users = UserQuery::create('u')
                    ->where('u.Id IN ?', $parameters['targets_user_id'])
                    ->find();

                if (count($users) < count($parameters['targets_user_id'])) {
                    // No throw exception here, we want to continue the process with the users that we found
                    $this->logger->error(
                        'ERROR Notification: One or more users are NOT found when sending notification, please compare. Parameters: { ' . StringUtil::arrayToString(
                            $parameters['targets_user_id']
                        ) . ' } and users found: {' . StringUtil::arrayToString($users->getPrimaryKeys()) . ' }'
                    );
                }

                foreach ($users as $target) {
                    $this->send($target, clone $notification);
                }
            } else {
                // Unique target
                $target = UserQuery::create('u')
                    ->where('u.Id = ?', $parameters['target_user_id'])
                    ->findOne();

                if (null == $target) {
                    throw new \RuntimeException(
                        'ERROR Notification: The user with id : ' . $parameters['target_user_id'] . ' is NOT found when sending notification !'
                    );
                }

                $this->send($target, clone $notification);
            }

            $this->lastSend = time();

        } catch(\PropelException $e) {
            $this->logger->error(sprintf('ERROR Notification Propel error "%s", caused by "%s"', $e->getMessage(), $e->getCause()));
            \Propel::close();

            return false;
		} catch (\Exception $e) {
			$this->logger->error('ERROR Notification: ' . $e->getMessage());
		}
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\User				 $target
	 * @param \BNS\App\NotificationBundle\Model\Notification $notification
	 */
	private function send(User $target, Notification $notification)
	{
		$notification->setTargetUserId($target->getId());
		$notification->setUser($target);
		$notification->send();

		// reset notification counter cache
		$this->container->get('notification_manager')->clearNotificationCache($target);
	}
}

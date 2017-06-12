<?php

namespace BNS\App\HelloWorldBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\NotificationBundle\Model\NotificationTypeQuery;
use BNS\App\NotificationBundle\Model\NotificationTypePeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\NotificationBundle\TranslateFactory\NotificationTranslateFactory;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\NotificationBundle\Model\NotificationPeer;

/**
 * @Route("/notifications") 
 */
class FrontNotificationController extends Controller
{	
	/**
	 * @Route("/", name="BNSAppHelloWorldBundle_front_notifications")
	 */
	public function indexAction()
	{
		$notificationTypes = NotificationTypeQuery::create()->find();
		
		return $this->render('BNSAppHelloWorldBundle:Notifications:index.html.twig', array(
			'notificationTypes' => $notificationTypes
		));
	}
	
	/**
	 * @Route("/liste", name="BNSAppHelloWorldBundle_front_notifications_list")
	 */
	public function listAction()
	{
		$notifications = NotificationQuery::create()
			->joinWith('NotificationType')
			->add(NotificationPeer::TARGET_USER_ID, $this->getUser()->getId())
			->addDescendingOrderByColumn(NotificationPeer::IS_NEW)
			->addDescendingOrderByColumn(NotificationPeer::DATE)
		->find();
		
		return $this->render('BNSAppHelloWorldBundle:Notifications:list.html.twig', array(
			'notifications' => $notifications
		));
	}
	
	/**
	 * @Route("/read/{notificationId}", name="BNSAppHelloWorldBundle_front_notifications_mark_as_read")
	 */
	public function markAsReadAction($notificationId)
	{
		$notification = NotificationQuery::create()
			->add(NotificationPeer::ID, $notificationId)
		->findOne();
		
		$notification->setIsNew(false);
		$notification->save();
		
		return new Response();
	}
	
	/**
	 * @Route("/{notificationTypeUniqueName}", name="BNSAppHelloWorldBundle_front_notifications_details")
	 */
	public function notificationTypeDetailsAction($notificationTypeUniqueName)
	{
		$notificationType = NotificationTypeQuery::create()
			->add(NotificationTypePeer::UNIQUE_NAME, $notificationTypeUniqueName)
		->findOne();
		
		if (null == $notificationType) {
			throw new NotFoundHttpException('The notification is not found with the unique name : ' . $notificationTypeUniqueName . ' !');
		}
		
		// Récupération des attributs du constructeur
		$notification = new \ReflectionClass(NotificationTranslateFactory::getNamespace($notificationTypeUniqueName, $notificationType->getModuleUniqueName()));
		$phpAttributes = $notification->getConstructor()->getParameters();
		$attributes = array();
		$attributeNames = '';
		
		foreach ($phpAttributes as $phpAttribute) {
			$attributes[$phpAttribute->getName()] = !$phpAttribute->isOptional();
			$attributeNames .= $phpAttribute->getName() . ',';
		}
		
		return $this->render('BNSAppHelloWorldBundle:Notifications:details.html.twig', array(
			'notificationType' => $notificationType,
			'attributes' => $attributes,
			'attributeNames' => substr($attributeNames, 0, -1)
		));
	}
	
	/**
	 * @Route("/envoyer/{notificationTypeUniqueName}", name="BNSAppHelloWorldBundle_front_notifications_send")
	 */
	public function sendNotification($notificationTypeUniqueName)
	{
		if ('POST' == $this->getRequest()->getMethod()) {
			$notificationType = NotificationTypeQuery::create()
				->add(NotificationTypePeer::UNIQUE_NAME, $notificationTypeUniqueName)
			->findOne();

			if (null == $notificationType) {
				throw new NotFoundHttpException('The notification is not found with the unique name : ' . $notificationTypeUniqueName . ' !');
			}
			
			// Récupération des attributs pour le constructeur de la notification
			$attributeNames = preg_split('#,#', $this->getRequest()->get('attribute_names'));
			$attributes = array();
			foreach ($attributeNames as $attributeName) {
				$attributes[$attributeName] = $this->getRequest()->get($attributeName);
			}
			
			$targetUser = UserQuery::create()
				->add(UserPeer::ID, $attributes['targetUser'])
			->findOne();
			
			if (null == $targetUser) {
				throw new NotFoundHttpException('The target user with ID ' . $attributes['targetUser'] . ' is not found !');
			}
			
			/**
			 * Attention, ceci est un exemple AUTOMATIQUE : c'est-à-dire que nous ne connaissons pas à l'avance les paramètres
			 * de la notification. Dans un contexte de développeur, vous êtes censés connaître les paramètres et donc instancier
			 * la notification comme une classe normale.
			 * 
			 * Exemple :
			 * $notification = new HelloWorldExampleWithAttribute($targetUser, 'test 1', 'test 2', 'et test 3', $groupId);
			 * $notification->send();
			 */
			
			$object = new \ReflectionClass(NotificationTranslateFactory::getNamespace($notificationTypeUniqueName, $notificationType->getModuleUniqueName()));
			$notification = $object->newInstanceArgs(array_merge(array(
				'container'	=> $container
			), $attributes));
			$notification->setTargetUserId($attributes['targetUser']);
			$notification->setUser($targetUser);
			$notification->send();
		}
		
		return $this->redirect($this->generateUrl('BNSAppHelloWorldBundle_front_notifications_details', array(
			'notificationTypeUniqueName' => $notificationTypeUniqueName
		)));
	}
}


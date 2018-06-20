<?php

namespace BNS\App\NotificationBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\InfoBundle\Model\AnnouncementUserQuery;
use BNS\App\NotificationBundle\Model\NotificationTypeQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\NotificationBundle\Model\NotificationSettingsQuery;
use BNS\App\NotificationBundle\Model\NotificationSettingsCollection;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\NotificationBundle\Model\NotificationCollection;
use BNS\App\NotificationBundle\Model\map\NotificationSettingsTableMap;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontController extends AbstractNotificationController
{
    /**
     * @Route("/", name="BNSAppNotificationBundle_front")
     */
    public function indexAction()
    {
        $user = $this->getUser();
        $this->get('bns.user_manager')->setUser($user);
        $userGroups = $this->get('bns.user_manager')->getGroupsUserBelong();

        $rm = $this->get('bns.right_manager');
        $rmu = $rm->getModulesReachableUniqueNames();

        $personnalModules = ModuleQuery::create()
            ->filterByUniqueName($rmu)
            ->filterByIsContextable(false)
            ->filterByUniqueName('NOTIFICATION', \Criteria::NOT_EQUAL)
            ->find();

        // Notification settings
        $notificationSettings = NotificationSettingsQuery::create()->filterByUser($user)->find();
        $settings = new NotificationSettingsCollection($notificationSettings);

        $unreadAnnouncementsCount = $this->get('bns.announcement_manager')->countUnreadAnnouncements();

        $response = $this->render('BNSAppNotificationBundle:Front:index.html.twig', array(
            'unreadNotifications' => new NotificationCollection([], $settings, $unreadAnnouncementsCount),
            'settings' => $settings,
            'userGroups' => $userGroups,
            'personnalModules' => $personnalModules,
            'totalUnreadNotifications' => $this->get('notification_manager')->getUnreadNotificationNumber($user, true)
        ));

        // reset notifications counter
        NotificationQuery::create('un')
            ->filterByUser($user)
            ->filterByIsNew(true)
            ->update(array('IsNew' => false));
        $this->get('notification_manager')->clearNotificationCache($user);

        return $response;
    }

	/**
	 * @Route("/annonces", name="notification_render_announcements")
	 */
	public function renderAnnouncementsAction()
	{
		$announcements = $this->get('bns.announcement_manager')->getAnnouncements();
		$readAnnouncements = [];
		$unreadAnnouncements = [];
		$readAnnouncementIds = [];
		foreach ($this->get('bns.announcement_manager')->getReadUserAnnouncements() as $readUserAnnouncement) {
			$readAnnouncementIds[] = $readUserAnnouncement->getAnnouncementId();
		}
		foreach ($announcements as $announcement) {
			if (in_array($announcement->getId(), $readAnnouncementIds)) {
				$readAnnouncements[] = $announcement;
			} else {
				$unreadAnnouncements[] = $announcement;
			}
		}

		return new Response(json_encode([
			'notifications' => $this->renderView('BNSAppNotificationBundle:Notification:announcement_list.html.twig', [
				'unread' => $unreadAnnouncements,
				'read' => $readAnnouncements,
			])
		]));
	}

	/**
	 * @Route("/tout-voir", name="notification_render")
	 * @Route("/tout-voir/page/{page}", name="notification_render_page")
	 * @Route("/correction", defaults={"isCorrection" = true}, name="notification_render_correction")
	 * @Route("/correction/page/{page}", defaults={"isCorrection" = true}, name="notification_render_correction_page")
	 * @Route("/{contextGroupId}", name="notification_render_group")
	 * @Route("/{contextGroupId}/page/{page}", name="notification_render_group_page")
	 * @Route("/{contextGroupId}/{moduleUniqueName}", name="notification_render_group_module")
	 * @Route("/{contextGroupId}/{moduleUniqueName}/page/{page}", name="notification_render_group_module_page")
	 */
	public function renderNotificationAction($contextGroupId = null, $moduleUniqueName = null, $page = 1, $isCorrection = false)
	{
		$user = $this->getUser();
		if (null != $moduleUniqueName) {
			$moduleUniqueName = strtoupper($moduleUniqueName);
		}

		// Security process
		if (null != $contextGroupId) {
			if (!$this->getRequest()->isXmlHttpRequest()) {
				throw new NotFoundHttpException('The request expects AJAX header !');
			}

			$this->isSecure($contextGroupId, $moduleUniqueName);
		}

        // Notifications
        $query = NotificationQuery::create('n')
            ->filterByUser($user)
            ->joinWith('NotificationType')
            ->orderBy('n.Date', \Criteria::DESC)
        ;


        // Init route for view
        $route = 'notification_render_page';

        // Notification settings
        $nsQuery = NotificationSettingsQuery::create('ns')
            ->where('ns.UserId = ?', $user->getId());

        // If group requested
        if ($contextGroupId) {
            $route = 'notification_render_group_page';

            if ('personnal' === $contextGroupId) {
                // Personnal, without announces
                $query->where('n.GroupId IS NULL');
                $nsQuery->where('ns.ContextGroupId IS NULL')->where('ns.ModuleUniqueName != ?', 'NOTIFICATION');
            } else {
                $query->where('n.GroupId = ?', $contextGroupId);
                $nsQuery->where('ns.ContextGroupId = ?', $contextGroupId);
            }
        }

        if ($moduleUniqueName) {
            $route = 'notification_render_group_module_page';
            $nsQuery->where('ns.ModuleUniqueName = ?', $moduleUniqueName);
            $moduleUniqueNames = [
                $moduleUniqueName
            ];
        } else {
            //On ne renvoie que les notifications de modules dont on a les droits
            $rm = $this->get('bns.right_manager');
            $moduleUniqueNames = $rm->getModulesReachableUniqueNames();
        }

        $notificationUniqueName = NotificationTypeQuery::create()
            ->filterByModuleUniqueName($moduleUniqueNames, \Criteria::IN)
            ->_if($isCorrection)
                ->filterByIsCorrection(true)
            ->_endif()
            ->_if('personnal' === $contextGroupId)
                ->filterByModuleUniqueName('NOTIFICATION', \Criteria::NOT_EQUAL)
            ->_endif()
            ->select(['UniqueName'])
            ->find()
            ->getArrayCopy()
        ;

        $query->filterByNotificationTypeUniqueName($notificationUniqueName, \Criteria::IN);

        // If correction requested
        if ($isCorrection) {
            $route = 'notification_render_correction_page';
        }

		// Retreive notification settings & notification pager
		$settings = new NotificationSettingsCollection($nsQuery->find());
		$notificationsPager = $query->paginate($page, 15);

		// Mark as read notifications
		$countClassNames = array();
		$unreadNotifications = array();
		$readNotifications = array();

		foreach ($notificationsPager->getResults() as $notification) {
			if ($notification->getIsNew()) {
				// Save into unread notifications array
				$unreadNotifications[$notification->getNotificationType()->getModuleUniqueName()][] = $notification;

				// Mask as read
				$notification->setIsNew(false);
				$notification->save();


				// Increase unread count
				$groupId = $notification->getGroupId() == null ? $notification->getNotificationType() == 'NOTIFICATION' ? 'announce': 'personnal' : $notification->getGroupId();

				// All
				$this->increaseUnreadCount($countClassNames, '.count-notification.all');

				// Group
				$this->increaseUnreadCount($countClassNames, '.count-notification.only-group-' . $groupId);

				// Module
				$this->increaseUnreadCount($countClassNames, '.count-notification.group-' . $groupId . '.module-' . strtolower($notification->getNotificationType()->getModuleUniqueName()));

				// Correction
				if ($notification->getNotificationType()->isCorrection()) {
					$this->increaseUnreadCount($countClassNames, '.count-notification.correction');
				}
			} else {
				$readNotifications[$notification->getNotificationType()->getModuleUniqueName()][] = $notification;
			}
		}
		// new read count
        $this->get('notification_manager')->clearNotificationCache($user);

		$hasNext = $notificationsPager->getLastPage() > $page ? true : false;
		$params = array(
			'notificationsPager'	=> $notificationsPager,
			'unreadNotifications'	=> $unreadNotifications,
			'readNotifications'		=> $readNotifications,
			'settings'				=> $settings,
			'hasNextPage'			=> $hasNext,
			'countClassNames'		=> $countClassNames,
			'firstCall'				=> !$this->getRequest()->isXmlHttpRequest(),
			'page'					=> $page
		);

		$view = '';

		// if there are unread announcements, prepend them to the notifications, only in the "all notifications" view
		if (!$contextGroupId && !$moduleUniqueName && !$isCorrection && $page == 1) {
			$announcements = $this->get('bns.announcement_manager')->getAnnouncements();
			$unreadAnnouncements = [];
			$readAnnouncementIds = [];
			foreach ($this->get('bns.announcement_manager')->getReadUserAnnouncements() as $readUserAnnouncement) {
				$readAnnouncementIds[] = $readUserAnnouncement->getAnnouncementId();
			}
			foreach ($announcements as $announcement) {
				if (!in_array($announcement->getId(), $readAnnouncementIds)) {
					$unreadAnnouncements[] = $announcement;
				}
			}

			if (count($unreadAnnouncements)) {
				$view = $this->renderView('BNSAppNotificationBundle:Notification:announcement_list.html.twig', [
					'unread' => $unreadAnnouncements,
					'read' => [],
				]);
				$params['hideEmpty'] = true;
			}
		}

		// render the list of notifications
		$view .= $this->renderView('BNSAppNotificationBundle:Notification:notification_module_list.html.twig', $params);

		if ($this->getRequest()->isXmlHttpRequest()) {
			return new Response(json_encode(array(
				'notifications'		=> $view,
				'hasNextPage'		=> $hasNext,
				'page'				=> $page,
				'moreLink'			=> $this->generateUrl($route, array(
					'contextGroupId'	=> $contextGroupId,
					'moduleUniqueName'	=> $moduleUniqueName,
					'page'				=> $notificationsPager->getNextPage()
				), true)
			)));
		}

		return new Response($view);
	}

	/**
	 * @param array $countClassNames
	 * @param string $className
	 */
	private function increaseUnreadCount(&$countClassNames, $className)
	{
		if (isset($countClassNames[$className])) {
			$countClassNames[$className] = $countClassNames[$className] + 1;
		}
		else {
			$countClassNames[$className] = 1;
		}
	}

	/**
	 * @return Response
	 */
	public function countAction($isInFront)
	{
        $rm = $this->get('bns.right_manager');
        $rmu = $rm->getModulesReachableUniqueNames();

		$count = NotificationQuery::create('n')
			->where('n.TargetUserId = ?', $this->getUser()->getId())
			->where('n.IsNew = ?', true)
            ->joinWith('NotificationType')
            ->where('NotificationType.ModuleUniqueName IN ?',$rmu)
		->count();

		return $this->render('BNSAppNotificationBundle:Front:count.html.twig', array(
			'count' => $count,
			'route'	=> 'BNSAppNotificationBundle_front'
		));
	}
}

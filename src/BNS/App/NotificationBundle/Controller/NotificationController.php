<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/07/2017
 * Time: 19:01
 */

namespace BNS\App\NotificationBundle\Controller;


use BNS\App\CoreBundle\Model\User;
use FOS\RestBundle\View\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class NotificationController
 * @package BNS\App\NotificationBundle\Controller
 * @Route("/notification")
 */
class NotificationController extends Controller
{

    /**
     * @Route("/{applicationUniqueName}/{groupId}/{notification}", name="BNSAppNotificationBundle_redirect")
     * @param $applicationUniqueName
     * @param $groupId
     * @param $notification
     */
    public function redirectForNotificationAction ($applicationUniqueName = null, $groupId = null, $notification = null, Request $request)
    {
        $user = $this->getUser();
        if (!$user || !($user instanceof User)) {
            throw $this->createAccessDeniedException();
        }
        /*if (!preg_match('\'/[a-zA-Z0-9.-]+/\'', $applicationUniqueName)) {
            throw $this->createNotFoundException();
        }*/
        if (!$this->get('bns.right_manager')->hasRightSomeWhere(strtoupper($applicationUniqueName) . "_ACCESS")) {
            $this->redirect('/');
            throw $this->createAccessDeniedException();
        }

        if (!$this->get('bns.right_manager')->hasRight(strtoupper($applicationUniqueName) . "_ACCESS", $groupId)) {
            $groupsWherePermission = $this->get('bns.right_manager')->getGroupIdsWherePermission(strtoupper($applicationUniqueName) . "_ACCESS");
            $this->redirectForNotificationAction($applicationUniqueName, $groupsWherePermission[0], $notification, $request);
        }

        switch ($notification) {
            case 'front_competition':
            $route = 'BNSAppCompetitionBundle_details';
            $parameters = ['id' => (int)$request->get('id')];
            break;
            case 'front_book':
            $route = 'BNSAppCompetitionBundle_bookDetails';
            $parameters = ['id' => (int)$request->get('id'), 'bookId' => (int)$request->get('bookId')];
            break;
        }
        return $this->redirectToRoute($route, $parameters);



    }
}

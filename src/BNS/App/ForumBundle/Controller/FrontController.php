<?php

namespace BNS\App\ForumBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\ForumBundle\Form\Type\MessageType;
use BNS\App\ForumBundle\Model\Forum;
use BNS\App\ForumBundle\Model\ForumMessage;
use BNS\App\ForumBundle\Model\ForumMessagePeer;
use BNS\App\ForumBundle\Model\ForumMessageQuery;
use BNS\App\ForumBundle\Model\ForumQuery;
use BNS\App\ForumBundle\Model\ForumSubject;
use BNS\App\ForumBundle\Model\ForumSubjectQuery;
use BNS\App\ForumBundle\Model\ForumUser;
use BNS\App\ForumBundle\Model\ForumUserPeer;
use BNS\App\ForumBundle\Model\ForumUserQuery;
use BNS\App\NotificationBundle\Notification\ForumBundle\ForumNewForumMessageNotification;
use BNS\App\NotificationBundle\Notification\ForumBundle\ForumNewForumReplyNotification;

use Pagerfanta\Adapter\PropelAdapter;
use Pagerfanta\Pagerfanta;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class FrontController extends Controller
{
    protected $forums;

    /**
     * @Route("/{page}", name="BNSAppForumBundle_front", defaults={"page"=1})
     * @Route("/list/{slug}/{page}", name="BNSAppForumBundle_front_slug", defaults={"page"=1})
     * @Template()
     * @RightsSomeWhere("FORUM_ACCESS")
     */
    public function indexAction($slug = null, $page = 1)
    {
        if (null === $slug && 1 === $page) {
            $this->get('stat.forum')->visit();
        }

        $rightManager = $this->get('bns.right_manager');

        if (null === $slug && 0 === count($this->getForums())) {
            return $this->render('BNSAppForumBundle:Front:noForum.html.twig', array(
                    'isAdmin' => $rightManager->hasRightSomeWhere('FORUM_ACCESS_BACK')
                    ));
        }

        $forum = ForumQuery::create()
                ->filterBySlug($slug)
                ->findOne();

        if (!$forum) {
            $forum = $this->getForums()->getFirst();
        }

        if (!$this->hasForumRight($forum)) {
            if (!$forum || !$forum->canSubscribe() || !$rightManager->hasRight('FORUM_ACCESS', $forum->getGroupId())) {
                throw $this->createNotFoundException('forum not found');
            }
        }

        $query = ForumSubjectQuery::create()
            ->useForumMessageQuery()
                ->filterByStatus(ForumMessagePeer::STATUS_VALIDATED)
            ->endUse()
            ->groupBy('Id')
            ->filterByForum($forum)
            ->joinWith('User', \Criteria::LEFT_JOIN)
            ->orderByCreatedAt(\Criteria::DESC);

        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        return array(
                'forum'    => $forum,
                'hasForumRight' => $this->hasForumRight($forum),
                'subjects' => $pager
                );
    }

    /**
     * @Template("BNSAppForumBundle:Block:frontForumFilter.html.twig")
     * @RightsSomeWhere("FORUM_ACCESS")
     */
    public function sidebarAction(Forum $forum = null)
    {
        return array(
                'forums'   => $this->getForums(),
                'forum'    => $forum
                );
    }

    /**
     * @Route("/nouveau-sujet/{slug}", name="BNSAppForumBundle_front_new_subject")
     * @Template("BNSAppForumBundle:Front:formSubject.html.twig")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function newSubjectAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$this->hasForumRight($forum)) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }
        if ($forum->isReadOnly()) {
            return $this->redirect($this->generateUrl('BNSAppForumBundle_front_slug', array('slug' => $forum->getSlug())));
        }

        $subject = new ForumSubject();
        $subject->setForum($forum);
        $subject->setUser($this->getUser());

        $message = new ForumMessage();
        $message->setUser($this->getUser());
        $message->setForumSubject($subject);
        if (!$forum->getIsModerated() || $rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            $message->setStatus(ForumMessagePeer::STATUS_VALIDATED);
        } else {
            $message->setStatus(ForumMessagePeer::STATUS_PENDING_VALIDATION);
        }

        $form = $this->createForm(new MessageType(), $message, array('is_subject' => true));

        if ($request->isMethod('post')) {
            $form->bind($request);
            $this->get('bns.media.manager')->bindAttachments($message, $request);
            if ($form->isValid()) {
                $message->save();

                //statistic action
                $this->get("stat.forum")->newTopic();

                //Gestion des PJ
                $this->get('bns.media.manager')->saveAttachments($message, $request, $forum->getGroup());

                if (!$forum->getIsModerated() || $rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
                    $this->sendNotification($message);

                    return $this->redirect($this->generateUrl('BNSAppForumBundle_front_view_subject', array('slug' => $subject->getSlug())));
                }
                if ($rightManager->isChild()) {
                    $this->get('session')->getFlashBag()->set('success', 'Ton sujet doit être validé pour être visible sur le forum.');
                } else {
                    $this->get('session')->getFlashBag()->set('success', 'Votre sujet doit être validé pour être visible sur le forum.');
                }

                return $this->redirect($this->generateUrl('BNSAppForumBundle_front_slug', array('slug' => $forum->getSlug())));
            }
        }

        return array(
                'forum' => $forum,
                'subject' => $subject,
                'message' => $message,
                'form' => $form->createView(),
                );
    }


    /**
     * @Route("/voir-sujet/{slug}/{page}", name="BNSAppForumBundle_front_view_subject", defaults={"page"=1})
     * @Template("BNSAppForumBundle:Front:viewSubject.html.twig")
     * @ParamConverter("subject", options={"with"={"Forum"}})
     */
    public function viewSubjectAction(ForumSubject $subject, $page = 1)
    {
        $forum = $subject->getForum();
        if (!$this->hasForumRight($forum)) {
            throw $this->createNotFoundException('Subject not found with slug : ' . $subject->getSlug());
        }

        $query = ForumMessageQuery::create()
            ->filterByForumSubject($subject)
            ->filterByStatus(ForumMessagePeer::STATUS_VALIDATED)
            ->orderByCreatedAt(\Criteria::ASC)
        ;

        $message = new ForumMessage();
        $form = $this->createForm(new MessageType(), $message, array());

        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        return array(
                'forum'    => $forum,
                'subject'  => $subject,
                'messages' => $pager,
                'form'     => $form->createView(),
                );
    }

    /**
     * @Route("/nouveau-message/{slug}", name="BNSAppForumBundle_front_new_message")
     * @ParamConverter("subject", options={"with"={"Forum"}})
     */
    public function newMessageAction(Request $request, ForumSubject $subject)
    {
        $rightManager = $this->get('bns.right_manager');
        $forum = $subject->getForum();
        if (!$this->hasForumRight($forum)) {
            throw $this->createNotFoundException('Subject not found with slug : ' . $subject->getSlug());
        }

        if (!$forum->isReadOnly()) {
            $message = new ForumMessage();
            $message->setUser($this->getUser());
            $message->setForumSubject($subject);
            if (!$forum->getIsModerated() || $rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
                $message->setStatus(ForumMessagePeer::STATUS_VALIDATED);
            } else {
                $message->setStatus(ForumMessagePeer::STATUS_PENDING_VALIDATION);
            }
            $form = $this->createForm(new MessageType(), $message, array());

            if ($request->isMethod('post')) {
                $form->bind($request);
                $this->get('bns.media.manager')->bindAttachments($message, $request);
                if ($form->isValid()) {
                    $message->save();

                    //Gestion des PJ
                    $this->get('bns.media.manager')->saveAttachments($message, $request, $forum->getGroup());

                    //statistic action
                    $this->get("stat.forum")->newMessage();

                    if (ForumMessagePeer::STATUS_PENDING_VALIDATION == $message->getStatus()) {
                        if ($rightManager->isChild()) {
                            $this->get('session')->getFlashBag()->set('success', 'Ton sujet doit être validé pour être visible sur le forum.');
                        } else {
                            $this->get('session')->getFlashBag()->set('success', 'Votre sujet doit être validé pour être visible sur le forum.');
                        }
                    } else {
                        $this->sendNotification($message);
                    }
                }
            }
        }

        return $this->redirect($this->generateUrl('BNSAppForumBundle_front_view_subject', array('slug' => $subject->getSlug())));
    }

    /**
     * @Route("/inscription/{slug}", name="BNSAppForumBundle_front_subscribe")
     * @ParamConverter("forum")
     */
    public function subscribeAction(Forum $forum)
    {
        if (!$this->get('bns.right_manager')->hasRight('FORUM_ACCESS', $forum->getGroupId())) {
            throw $this->createNotFoundException('forum not found');
        }
        if ($forum->canSubscribe()) {
            $forumUser = new ForumUser();
            $forumUser->setUser($this->getUser());
            if ($forum->getSubscriptionRequired() && !$this->get('bns.right_manager')->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
                $forumUser->setStatus(ForumUserPeer::STATUS_PENDING_VALIDATION);
            } else {
                $forumUser->setStatus(ForumUserPeer::STATUS_VALIDATED);
            }
            $forumUser->setForum($forum);
            $forumUser->save();
        }

        return $this->redirect($this->generateUrl('BNSAppForumBundle_front_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/desinscription/{slug}", name="BNSAppForumBundle_front_unsubscribe")
     * @ParamConverter("forum")
     */
    public function unsubscribeAction(Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if ($forum->getUnsubscribingAllowed() && $rightManager->hasRight('FORUM_ACCESS', $forum->getGroupId())) {
            ForumUserQuery::create()->filterByUser($this->getUser())->filterByForum($forum)->delete();

            $forum->anonymize($this->getUser());

            if ($rightManager->isChild()) {
                $this->get('session')->getFlashBag()->set('success', "Ta désinscription a été effectuée.");
            } else {
                $this->get('session')->getFlashBag()->set('success', "Votre désinscription a été effectuée.");
            }
        }

        return $this->redirect($this->generateUrl('BNSAppForumBundle_front'));
    }


    /**
     * @Route("/notification/{slug}/{type}", name="BNSAppForumBundle_front_notification")
     * @ParamConverter("forum")
     */
    public function toggleNotificationAction(Request $request, Forum $forum, $type)
    {
        if (!$this->hasForumRight($forum) || !$forum->isSubscribe($this->getUser())) {
            throw $this->createNotFoundException('Forum not found');
        }
        $value = $request->get('value', 1);

        $forumUsers = ForumUserQuery::create()
            ->filterByUser($this->getUser())
            ->filterByForum($forum)
            ->find();

        foreach ($forumUsers as $forumUser) {
            if ('NewMessage' === $type) {
                $forumUser->setNotificationNewMessage(1 == $value);
            } else if ('Reply' === $type) {
                $forumUser->setNotificationReply(1 == $value);
            }
            $forumUser->save();
        }

        return new JsonResponse(array('message' => 'OK'));

    }

    /**
     * @return \PropelObjectCollection $forums
     */
    protected function getForums()
    {
        if (null === $this->forums) {
            $rightManager = $this->get('bns.right_manager');
            $groupAdmins = $rightManager->getGroupsWherePermission('FORUM_ACCESS_BACK');

            $forumsAdmins = ForumQuery::create()
                ->filterByGroup($groupAdmins)
                ->select('Id')
                ->find();

            $groups = $rightManager->getGroupsWherePermission('FORUM_ACCESS');

            $forumsAccess = ForumQuery::create()
                ->filterByGroup($groups)
                ->filterById($forumsAdmins, \Criteria::NOT_IN)
                ->useForumUserQuery()
                    ->filterByUser($this->getUser())
                    ->filterByStatus(ForumUserPeer::STATUS_VALIDATED)
                ->endUse()
                ->select('Id')
                ->find();

            $forumsPublic = ForumQuery::create()
                ->filterByGroup($groups)
                ->filterById($forumsAdmins, \Criteria::NOT_IN)
                ->filterById($forumsAccess, \Criteria::NOT_IN)
                ->filterByIsPublic(true)
                ->select('Id')
                ->find();

            $this->forums = ForumQuery::create()
                ->useGroupQuery()->orderByLabel()->endUse()
                ->joinWith('Group')
                ->orderByTitle()
                ->findPks(array_merge((array)$forumsAdmins, (array)$forumsAccess, (array)$forumsPublic));
        }

        return $this->forums;
    }

    protected function hasForumRight($forum)
    {
        if (null === $forum) {
            return false;
        }

        $rightManager = $this->get('bns.right_manager');

        // User with FORUM_ACCESS_BACK can view all forums of the group
        if ($rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            return true;
        }

        if (!$rightManager->hasRight('FORUM_ACCESS', $forum->getGroupId())) {
            return false;
        }

        if ($forum->isSubscribe($this->getUser()) || ($forum->getIsPublic() && !$forum->getSubscriptionRequired())) {
            return true;
        }

        return false;
    }

    protected function sendNotification(ForumMessage $message)
    {
        // notification for all message
        $forum = $message->getForumSubject()->getForum();
        $users = UserQuery::create()
            ->useForumUserQuery()
                ->filterByForum($forum)
                ->filterByUser($message->getUser(), \Criteria::NOT_EQUAL)
                ->filterByStatus(ForumUserPeer::STATUS_VALIDATED)
                ->filterByNotificationNewMessage(true)
            ->endUse()
        ->find();
        $this->get('notification_manager')->send($users, new ForumNewForumMessageNotification($this->container, $message->getForumSubjectId(), $forum->getGroupId()), $this->getUser());

        // notification for reply
        $subjectUsers = UserQuery::create()
            ->useForumMessageQuery()
                ->filterByForumSubjectId($message->getForumSubjectId())
            ->endUse()
            ->select('Id')
            ->find()
            ->getArrayCopy();

        $users = UserQuery::create()
            ->useForumUserQuery()
                ->filterByForum($forum)
                ->filterByUser($message->getUser(), \Criteria::NOT_EQUAL)
                ->filterByUser($users, \Criteria::NOT_IN)
                ->filterByUserId($subjectUsers, \Criteria::IN)
                ->filterByStatus(ForumUserPeer::STATUS_VALIDATED)
                ->filterByNotificationReply(true)
            ->endUse()
            ->find();
        $this->get('notification_manager')->send($users, new ForumNewForumReplyNotification($this->container, $message->getForumSubjectId(), $forum->getGroupId()), $this->getUser());
    }
}

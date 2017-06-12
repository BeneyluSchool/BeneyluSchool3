<?php
namespace BNS\App\ForumBundle\Controller;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\ForumBundle\Form\Type\ForumType;
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

/**
 * @Route("/gestion")
 *
 * @author Jérémie Augustin
 */
class BackController extends Controller
{
    /**
     * @Route("/", name="BNSAppForumBundle_back")
     * @Route("/list/{slug}/{page}", name="BNSAppForumBundle_back_slug", defaults={"page"=1})
     * @Template()
     * @RightsSomeWhere("FORUM_ACCESS_BACK")
     */
    public function indexAction($slug = null, $page = 1)
    {
        if (null === $slug && 1 === $page) {
            $this->get('stat.forum')->visit();
        }

        $rightManager = $this->get('bns.right_manager');
        $groups = $rightManager->getGroupsWherePermission('FORUM_ACCESS_BACK');

        $forum = null;
        if (null !== $slug) {
            $forum = ForumQuery::create()
                ->filterByGroup($groups)
                ->filterBySlug($slug)
                ->findOne();

            if (!$forum) {
                throw $this->createNotFoundException('forum not found with slug :' . $slug);
            }
        }

        $forums = ForumQuery::create()
            ->filterByGroup($groups)
            ->useGroupQuery()->orderByLabel(\Criteria::ASC)->endUse()
            ->joinWith('Group')
            ->orderBytitle()
            ->find();

        if (!$forum && count($forums) > 0) {
            $forum = $forums->getFirst();
        }

        return array(
                'groups' => $groups,
                'forums' => $forums,
                'forum'  => $forum,
                'page'   => $page,
                );
    }

    /**
     * @Route("/nouveau/", name="BNSAppForumBundle_back_new_forum")
     * @Template("BNSAppForumBundle:Back:formForum.html.twig")
     * @RightsSomeWhere("FORUM_ACCESS_BACK")
     */
    public function newForumAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $groups = $rightManager->getGroupsWherePermission('FORUM_ACCESS_BACK');

        $forum = new Forum();

        $form = $this->createForm(new ForumType(), $forum, array('groups' => $groups, 'validation_groups' => array('Default', 'New')));

        if ($request->isMethod('post')) {
            $form->bind($request);
            if ($form->isValid()) {
                $forum->save();

                return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
            }
        }

        return array(
                'form' => $form->createView(),
                'forum' => $forum,
                'nbGroup' => $groups->count()
                );
    }

    /**
     * @Route("/editer/{slug}", name="BNSAppForumBundle_back_edit_forum")
     * @Template("BNSAppForumBundle:Back:formForum.html.twig")
     * @RightsSomeWhere("FORUM_ACCESS_BACK")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function editForumAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $form = $this->createForm(new ForumType(), $forum, array('is_edit' => true, 'validation_groups' => array('Default', 'Edit')));

        if ($request->isMethod('post')) {
            $form->bind($request);
            if ($form->isValid()) {
                $forum->save();

                return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
            }
        }

        return array(
                'form' => $form->createView(),
                'forum' => $forum,
        );
    }

    /**
     * @Route("/supprimer/{slug}", name="BNSAppForumBundle_back_delete_forum")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function deleteForumAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $forum->delete();

        $this->get('session')->getFlashBag()->set('success', 'Le forum a été supprimé avec succès');

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back'));
    }

    /**
     * @Route("/archiver/{slug}", name="BNSAppForumBundle_back_archive_forum")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function archiveForumAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $forum->setIsArchived(true);
        $forum->save();

        $forum->anonymizeAll();

        $this->get('session')->getFlashBag()->set('success', 'Le forum a été archivé avec succès');

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/moderation/{page}", name="BNSAppForumBundle_back_moderation", defaults={"page"=1})
     * @Template()
     * @RightsSomeWhere("FORUM_ACCESS_BACK")
     */
    public function moderationAction($page = 1)
    {
        $rightManager = $this->get('bns.right_manager');
        $groups = $rightManager->getGroupsWherePermission('FORUM_ACCESS_BACK');

        $query = ForumMessageQuery::create()
            ->filterByStatus(ForumMessagePeer::STATUS_VALIDATED, \Criteria::NOT_EQUAL)
            ->orderByCreatedAt(\Criteria::DESC)
            ->joinWith('User', \Criteria::LEFT_JOIN)
            ->joinWith('ForumSubject')
            ->useForumSubjectQuery()
                ->orderByTitle(\Criteria::ASC)
                ->useForumQuery()
                    ->filterByGroup($groups)
                ->endUse()
            ->endUse()
            ;

        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        return array(
                'messages' => $pager
                );
    }

    /**
     * @Route("/inscription-utilisateur/{slug}", name="BNSAppForumBundle_back_subscribe")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function subscribeUserAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $userIds = explode(',', $request->request->get('inscription-user-id-to-manage', ''));
        // TODO Vérifier les id utilisateurs

        $users = UserQuery::create()->findPks($userIds);

        $con = \Propel::getConnection(ForumUserPeer::DATABASE_NAME);
        $con->beginTransaction();
        try {
            ForumUserQuery::create()->filterByForum($forum)->filterByUser($users)->delete($con);

            foreach ($users as $user) {
                $forumUser = new ForumUser();
                $forumUser->setUser($user);
                $forumUser->setStatus(ForumUserPeer::STATUS_VALIDATED);

                $forum->addForumUser($forumUser);
            }

            $forum->save($con);
        } catch (\Execption $e) {
            $con->rollBack();
            throw $e;
        }
        $con->commit();

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/validation-inscription-utilisateur/{slug}", name="BNSAppForumBundle_back_validate_users")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function validateUsersAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $userIds = explode(',', $request->request->get('validate-users-id', ''));
        // TODO Vérifier les id utilisateurs

        $users = ForumUserQuery::create()
            ->filterByStatus(ForumUserPeer::STATUS_PENDING_VALIDATION)
            ->filterByForum($forum)
            ->filterByUserId($userIds)
            ->find();
        ;

        foreach ($users as $user) {
            $user->setStatus(ForumUserPeer::STATUS_VALIDATED);
        }
        $users->save();

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/refuser-inscription-utilisateur/{slug}", name="BNSAppForumBundle_back_cancel_users")
     * @ParamConverter("forum", options={"with"={"Group"}})
     */
    public function cancelUsersAction(Request $request, Forum $forum)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $userIds = explode(',', $request->request->get('cancel-users-id', ''));

        $users = ForumUserQuery::create()
            ->filterByStatus(ForumUserPeer::STATUS_PENDING_VALIDATION)
            ->filterByForum($forum)
            ->filterByUserId($userIds)
            ->delete();
        ;

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/desinscrire-utilisateur/{slug}/{id}", name="BNSAppForumBundle_back_unsubscribe_user")
     * @ParamConverter("forum", options={"with"={"Group"}, "exclude"={"id"}})
     * @ParamConverter("user")
     */
    public function unsubscribeUserAction(Request $request, Forum $forum, User $user)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        ForumUserQuery::create()
            ->filterByForum($forum)
            ->filterByUser($user)
            ->delete();
        ;

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Template()
     */
    public function getSubjectsAction(Forum $forum, $page = 1)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Forum not found with slug : ' . $forum->getSlug());
        }

        $query = ForumSubjectQuery::create()
            ->filterByForum($forum)
            ->joinWith('User', \Criteria::LEFT_JOIN)
            ->orderByCreatedAt(\Criteria::DESC);

        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        return array(
                'forum' => $forum,
                'subjects' => $pager,
                'page' => $page
                );
    }

    /**
     * @Route("/voir-sujet/{slug}/{page}", name="BNSAppForumBundle_back_view_subject", defaults={"page"=1})
     * @ParamConverter("subject", options={"with"={"Forum"}})
     * @Template()
     */
    public function viewSubjectAction(ForumSubject $subject, $page = 1)
    {
        $forum = $subject->getForum();
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Subject not found with slug : ' . $subject->getSlug());
        }

        $query = ForumMessageQuery::create()
            ->filterByForumSubject($subject)
            ->orderByCreatedAt(\Criteria::ASC)
            ;

        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        $forums = ForumQuery::create()
            ->filterByGroup($rightManager->getGroupsWherePermission('FORUM_ACCESS_BACK'))
            ->useGroupQuery()->orderByLabel(\Criteria::ASC)->endUse()
            ->joinWith('Group')
            ->orderBytitle()
            ->find();

        return array(
                'forums'   => $forums,
                'forum'    => $forum,
                'subject'  => $subject,
                'messages' => $pager,
        );
    }

    /**
     * @Route("/supprimer-sujet/{slug}", name="BNSAppForumBundle_back_delete_subject")
     * @ParamConverter("subject", options={"with"={"Forum"}})
     * @Template()
     */
    public function deleteSubjectAction(ForumSubject $subject)
    {
        $forum = $subject->getForum();
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Subject not found with slug : ' . $subject->getSlug());
        }

        $subject->delete();

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_slug', array('slug' => $forum->getSlug())));
    }

    /**
     * @Route("/supprimer-message/{id}/{page}/{redirectModeration}", name="BNSAppForumBundle_back_delete_message", defaults={"page"=1, "redirectModeration"=0})
     * @ParamConverter("message", options={"with"={"ForumSubject", "ForumSubject.Forum"}})
     * @Template()
     */
    public function deleteMessageAction(ForumMessage $message, $page = 1, $redirectModeration = 0)
    {
        $subject = $message->getForumSubject();
        $forum =  $subject->getForum();
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Message not found');
        }

        $message->delete();

        if ($redirectModeration) {
            return $this->redirect($this->generateUrl('BNSAppForumBundle_back_moderation', array('page' => $page)));
        }

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_view_subject', array('slug' => $subject->getSlug(), 'page' => $page)));
    }

    /**
     * @Route("/valider-message/{id}/{page}/{redirectModeration}", name="BNSAppForumBundle_back_validate_message", defaults={"page"=1, "redirectModeration"=0})
     * @ParamConverter("message", options={"with"={"ForumSubject", "ForumSubject.Forum"}})
     * @Template()
     */
    public function validateMessageAction(ForumMessage $message, $page = 1, $redirectModeration = 0)
    {
        $subject = $message->getForumSubject();
        $forum =  $subject->getForum();
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('FORUM_ACCESS_BACK', $forum->getGroupId())) {
            throw $this->createNotFoundException('Message not found');
        }

        $message->setStatus(ForumMessagePeer::STATUS_VALIDATED);
        $message->save();

        $this->sendNotification($message);

        if ($redirectModeration) {
            return $this->redirect($this->generateUrl('BNSAppForumBundle_back_moderation', array('page' => $page)));
        }

        //statistic action
        $this->get("stat.forum")->newMessage();

        return $this->redirect($this->generateUrl('BNSAppForumBundle_back_view_subject', array('slug' => $subject->getSlug(), 'page' => $page)));
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

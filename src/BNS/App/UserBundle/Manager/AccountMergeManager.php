<?php

namespace BNS\App\UserBundle\Manager;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\LiaisonBookSignatureQuery;
use BNS\App\CoreBundle\Model\ProfileCommentQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\MessagingBundle\Model\MessagingConversationPeer;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessagePeer;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\NotificationBundle\Manager\NotificationManager;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileAccountMergedNotification;
use BNS\App\UserBundle\Model\UserMerge;
use BNS\App\UserBundle\Model\UserMergePeer;
use BNS\App\UserBundle\Model\UserMergeQuery;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class AccountMergeManager
{
    /**
     * @var BNSApi
     */
    protected $api;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var NotificationManager
     */
    protected $notificationManager;

    /**
     * @var ProducerInterface
     */
    protected $producer;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @deprecated do not use!!! only for Notification
     * @var $container ContainerInterface
     */
    protected $container;

    /**
     * AccountMergeManager constructor.
     */
    public function __construct(
        BNSApi $api,
        BNSUserManager $userManager,
        NotificationManager $notificationManager,
        ProducerInterface $producer,
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->api = $api;
        $this->userManager = $userManager;
        $this->notificationManager = $notificationManager;
        $this->producer = $producer;
        $this->logger = $logger;
        $this->container = $container;
    }

    /**
     * @param string $login
     * @param string $password
     * @return bool
     */
    public function isUserAuthenticated($login, $password)
    {
        if (!$login || !$password) {
            return false;
        }
        try {
            $response = $this->api->send(
                'user_authentication',
                array(
                    'values' => array(
                        'username' => $login,
                        'password' => Crypt::encrypt($password)
                    )
                )
            );

            return isset($response['authentication']) && $response['authentication'];
        } catch (\Exception $e) {
            $this->logger->error(
                '[ACCOUNT_MERGE_MANAGER] Cannot authenticate user',
                [
                    'exception' => $e->getMessage(),
                    'user' => $login
                ]
            );
        }

        return false;
    }

    /**
     * @param User $userSource
     * @param User $userDestination
     * @param boolean $checkOldMerge
     * @return bool
     * @throws \PropelException
     */
    public function canMergeUsers(User $userSource, User $userDestination, $checkOldMerge = true)
    {
        $countOldMerges = 0;
        if ($checkOldMerge) {
            $countOldMerges = $this->api->send(
                'get_user_account_merges',
                [
                    'route' => [
                        'sourceId' => $userSource->getId(),
                        'destinationId' => $userDestination->getId()
                    ]
                ],
                false
            );
        }

        $currentMerge = UserMergeQuery::create()
            ->filterByUserRelatedByUserSourceId($userSource)
            ->filterByUserRelatedByUserDestinationId($userDestination)
            ->filterByStatus([
                UserMergePeer::STATUS_NEW,
                UserMergePeer::STATUS_CURRENT
            ], \Criteria::IN)
            ->count();
        if ($currentMerge > 0) {
            // cannot merge there is already a merge request for those user
            return false;
        }

        $parentRoleId = (int)GroupTypeQuery::create()->filterByType("PARENT")->select('id')->findOne();
        $teacherRoleId = (int)GroupTypeQuery::create()->filterByType("TEACHER")->select('id')->findOne();
        $userSourceRoleId = (int)$userSource->getHighRoleId();
        $userDestinationRoleId = (int)$userDestination->getHighRoleId();
        if (
            $userSourceRoleId !== $parentRoleId ||
            !in_array($userDestinationRoleId, [$parentRoleId, $teacherRoleId]) ||
            $userSource->isArchived() ||
            $userDestination->isArchived() ||
            $countOldMerges > 0 ||
            $userSource->getAafId() ||
            UserMergeQuery::create()->filterByUserSourceId($userDestination->getId())->count() > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param User $userSource
     * @param User $userDestination
     * @param bool $mergeEmail
     * @param bool $notify
     * @return bool
     * @throws \Exception
     * @throws \PropelException
     */
    public function createMergeRequest(User $userSource, User $userDestination, $mergeEmail = true, $notify = false, $checkOldMerge = true)
    {
        if ($this->canMergeUsers($userSource, $userDestination, $checkOldMerge)) {
            $userMerge = new UserMerge();
            $userMerge
                ->setStatus(UserMergePeer::STATUS_NEW)
                ->setUserDestinationId($userDestination->getId())
                ->setUserSourceId($userSource->getId())
                ->setMergeEmail($mergeEmail)
                ->setSendNotification($notify)
                ->save();

            // publish the merge to be handle by consumer
            $this->producer->publish(json_encode(['id' => $userMerge->getId()]));

            return true;
        }

        return false;
    }

    /**
     * @param User $userSource
     * @return array
     */
    public function getUserSourceGroups(User $userSource)
    {
        $oldUser = $this->userManager->getUser();
        $this->userManager->setUser($userSource);

        $groupsAndRoles = [];
        foreach ($this->userManager->getFullRightsAndGroups() as $group) {
            $groupsAndRoles[] = [
                'group' => $group['group'],
                'roles' => $group['roles'],
            ];
        }

        if ($oldUser) {
            $this->userManager->setUser($oldUser);
        }

        return $groupsAndRoles;
    }

    /**
     * @param UserMerge $userMerge
     * @return bool|null
     * @throws \Exception
     * @throws \PropelException
     */
    public function executeMergeRequest(UserMerge $userMerge)
    {
        $userSource = $userMerge->getUserRelatedByUserSourceId();
        $userDestination = $userMerge->getUserRelatedByUserDestinationId();

        $userMerge->reload();
        if (!in_array($userMerge->getStatus(), [UserMergePeer::STATUS_NEW, UserMergePeer::STATUS_CURRENT])) {
            $this->logger->error('[ACCOUNT_MERGE_MANAGER] Try to execute a MergeRequest with wrong status', [
                'userMergeId' =>  $userMerge->getId(),
                'status' => $userMerge->getStatus()
            ] );

            return null;
        }
        $userMerge
            ->setStatus(UserMergePeer::STATUS_CURRENT)
            ->addLog('Start merge')
            ->save();

        if (!$this->moveBelong($userSource, $userDestination)) {
            $this->logger->error('[ACCOUNT_MERGE_MANAGER] Cannot move user belong', [
                'userMergeId' => $userMerge->getId()
            ]);
            $userMerge
                ->setStatus(UserMergePeer::STATUS_ERROR)
                ->addLog('Cannot move user belong')
                ->save();

            return false;
        }

        // get all children even archived
        $children = $userSource->getChildren();
        try {
            foreach ($children as $child) {
                // move children links
                $this->userManager->addParent($child, $userDestination);
                $this->userManager->removeParent($child, $userSource);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ACCOUNT_MERGE_MANAGER] Cannot affect children', [
                'userMergeId' => $userMerge->getId()
            ]);
            $userMerge
                ->setStatus(UserMergePeer::STATUS_ERROR)
                ->addLog("Cannot affect Children")
                ->addLog($e->getMessage())
                ->save();

            // clean rights
            if ($this->userManager->getUser()) {
                $this->userManager->resetRights();
            }
            $this->api->resetUser($userSource->getLogin());
            $this->api->resetUser($userDestination->getLogin());

            return false;
        }

        try {
            $this->mergeMessages($userSource, $userDestination);
            BlogArticleCommentQuery::create()->filterByAuthorId($userSource->getId())->update(['AuthorId' => $userDestination->getId()]);
            ProfileCommentQuery::create()->filterByAuthorId($userSource->getId())->update(['AuthorId' => $userDestination->getId()]);
            LiaisonBookSignatureQuery::create()->filterByUserId($userSource->getId())->update(['UserId' => $userDestination->getId()]);
        } catch (\Exception $e) {
            $this->logger->error("[ACCOUNT_MERGE_MANAGER] Cannot change owner of comments and messages", [
                'userMergeId' => $userMerge->getId()
            ]);
            $userMerge
                ->addLog("Cannot change owner of comments and messages")
                ->addLog($e->getMessage())
                ->save();
        }

        if ($userMerge->getMergeEmail()) {
            if ($userSource->getEmail()) {
                $userDestination->setEmail($userSource->getEmail());
            }
            if ($userSource->getEmailPrivate()) {
                $userDestination->setEmailPrivate($userSource->getEmailPrivate());
            }
        }
        // remove email on source account
        $userSource->setEmail('');
        $userSource->setEmailPrivate('');

        // make sure auth values are sync'ed
        $this->userManager->updateUser($userSource);
        $this->userManager->updateUser($userDestination);

        $this->userManager->deleteUser($userSource);

        $userDestination->save();
        $userSource->save();

        // clean all rights
        if ($this->userManager->getUser()) {
            $this->userManager->resetRights();
        }
        $this->api->resetUser($userSource->getLogin());
        $this->api->resetUser($userDestination->getLogin());

        $classrooms = $this->userManager->setUser($userDestination)->getClassroomUserBelong();
        foreach ($classrooms as $classroom) {
            $this->api->resetGroup($classroom->getId(), false);
            $this->api->resetGroupUsers($classroom->getId(), true, true);
        }

        $userMerge
            ->setStatus(UserMergePeer::STATUS_FINISHED)
            ->addLog('Merge finished')
            ->save();
        if ($userMerge->getSendNotification()) {
            $this->notificationManager->send([$userDestination], new ProfileAccountMergedNotification($this->container));
        }

        return true;
    }

    /**
     * @param User $userSource
     * @param User $userDestination
     * @return bool
     */
    public function deleteOldMerge(User $userSource, User $userDestination)
    {
        $responseCode = $this->api->send('user_merge_delete', [
            'check' => true,
            'values' => [
                'user_source_id' => $userSource->getId(),
                'user_destination_id' => $userDestination->getId()
            ]
        ]);

        if ($this->userManager->getUser()) {
            $this->userManager->resetRights();
        }
        $this->api->resetUser($userSource->getLogin());
        $this->api->resetUser($userDestination->getLogin());

        return Response::HTTP_OK === $responseCode;
    }

    /**
     * @param UserMerge $userMerge
     * @return bool
     * @throws \Exception
     * @throws \PropelException
     */
    public function cancelMerge(UserMerge $userMerge)
    {
        $userMerge
            ->setStatus(UserMergePeer::STATUS_CANCELED)
            ->addLog('Merge canceled');

        return $userMerge->save() > 0;
    }

    /**
     * @param UserMerge $userMerge
     * @return bool
     * @throws \PropelException*
     */
    public function retryMerge(UserMerge $userMerge)
    {
        $userMerge
            ->setStatus(UserMergePeer::STATUS_NEW)
            ->addLog('Merge retry')
            ->save();

        $this->producer->publish(json_encode(['id' => $userMerge->getId()]));;

        return true;
    }

    protected function moveBelong(User $userSource, User $userDestination)
    {
        $responseCode = $this->api->send('user_move_belong', [
            'check' => true,
            'values' => [
                'user_source_id' => $userSource->getId(),
                'user_destination_id' => $userDestination->getId()
            ]
        ]);

        if ($this->userManager->getUser()) {
            $this->userManager->resetRights();
        }
        $this->api->resetUser($userSource->getLogin());
        $this->api->resetUser($userDestination->getLogin());

        return Response::HTTP_OK === $responseCode;
    }

    protected function mergeMessages(User $userSource, User $userDestination)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);

        $queries = [
            MessagingMessagePeer::AUTHOR_ID => MessagingMessageQuery::create()->filterByAuthorId($userSource->getId()),
            MessagingConversationPeer::USER_ID => MessagingConversationQuery::create()->filterByUserId($userSource->getId()),
            MessagingConversationPeer::USER_WITH_ID => MessagingConversationQuery::create()->filterByUserWithId($userSource->getId()),
        ];
        foreach ($queries as $column => $select) {
            $update = new \Criteria();
            $update->add($column, $userDestination->getId());
            \BasePeer::doUpdate($select, $update, $con);
        }
    }
}

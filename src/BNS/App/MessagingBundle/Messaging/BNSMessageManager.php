<?php

namespace BNS\App\MessagingBundle\Messaging;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingConversationPeer;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\NotificationBundle\Notification\MessagingBundle\MessagingNewMessageReceivedNotification;
use BNS\App\NotificationBundle\Notification\MessagingBundle\MessagingNewAnswerReceivedNotification;
use BNS\App\NotificationBundle\Notification\MessagingBundle\MessagingMessagePendingModerationNotification;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Taelman Eymeric
 * Classe permettant la gestion des messages
 */
class BNSMessageManager
{

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @deprecated do not use this
     * @var User
     */
    protected $user;

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var int
     */
    public static $paginateLimit = 10;

    /**
     * Status des conversations
     */
    public static $messagesConversationStatus = array(
        'CAMPAIGN_READ' => 6,
        'CAMPAIGN' => 5,
        'SENT' => 4,
        'IN_MODERATION' => 3,
        'NONE_READ' => 2,
        'READ' => 1,
        'DELETED' => 0,
        'DELETED_CAMPAIGN' => -1,
    );
    /**
     * Status des messages
     */
    public static $messagesStatus = array(
        'CAMPAIGN_READ' => 5,
        'CAMPAIGN' => 4,
        'DRAFT' => 3,
        'IN_MODERATION' => 2,
        'ACCEPTED' => 1,
        'REJECTED' => 0,
        'DELETED' => -1,
        'DELETED_CAMPAIGN' => -2,
    );

    /**
     * @param ContainerInterface $container
     * @param  $mediaManager
     */
    public function __construct(ContainerInterface $container, MediaManager $mediaManager)
    {
        $this->container = $container;
        $this->setUser($this->getCurrentUser());
        $this->mediaManager = $mediaManager;
    }

    /**
     * @deprecated do not use this
     * @return User|bool
     */
    public function getCurrentUser()
    {
        if(BNSAccess::isConnectedUser())
        {
            return BNSAccess::getUser();
        }else{
            return false;
        }

    }

    /**
     * @deprecated do not use this
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @deprecated do not use this
     * @param $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    ////////  Actions sur les conversations  \\\\\\\\\\

    /**
     * Gets the last read id for users in the given conversation or message.
     *
     * @param MessagingConversation|MessagingMessage $object
     * @return array|mixed|\PropelObjectCollection
     */
    public function getLastReads($object)
    {
        if ($object instanceof MessagingConversation) {
            $conversation = $object;
        } elseif ($object instanceof MessagingMessage) {
            $conversation = MessagingConversationQuery::create()
                ->filterByUserId($object->getAuthorId())
                ->useMessagingMessageConversationQuery()
                    ->filterByMessagingMessage($object)
                ->endUse()
                ->findOne();
            if (!$conversation) {
                return null;
            }
        } else {
            throw new \InvalidArgumentException('Not a message or a conversation');
        }

        $data = MessagingConversationQuery::create()
            ->filterByMessageParentId($conversation->getMessageParentId())
            ->filterByUserWithId($conversation->getUserId())
            ->select(['UserId', 'LastReadId'])
            ->find();

        $lastReads = [];
        foreach ($data as $datum) {
            $lastReads[$datum['UserId']] = null === $datum['LastReadId'] ? null : (int)$datum['LastReadId'];
        }

        return $lastReads;
    }

    /**
     * Renvoie le string correspondant au status de la conversation
     * @param MessagingConversation $conversation
     * @return string
     */
    public function getStatus(MessagingConversation $conversation)
    {
        $conversationStatus = array_flip(self::$messagesConversationStatus);

        return $conversationStatus[$conversation->getStatus()];
    }

    /**
     * Save la conversation en "lue"
     * @param MessagingConversation $conversation
     */
    public function setRead(MessagingConversation $conversation)
    {
        $status = 'READ';
        if (in_array(
            $conversation->getStatus(),
            [
                self::$messagesConversationStatus['CAMPAIGN'],
                self::$messagesConversationStatus['CAMPAIGN_READ'],
                self::$messagesConversationStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            $status = 'CAMPAIGN_READ';
        }

        $lastMessageId = MessagingMessageQuery::create()
            ->filterChildrenForConversation($conversation)
            ->select(['Id'])
            ->find()
            ->getLast();
        $conversation->setLastReadId($lastMessageId);
        $conversation->setStatus(self::$messagesConversationStatus[$status]);
        $conversation->save();
    }

    /**
     * Save la conversation en "non lue"
     * @param MessagingConversation $conversation
     */
    public function setUnread(MessagingConversation $conversation)
    {
        $status = 'NONE_READ';
        if (in_array(
            $conversation->getStatus(),
            [
                self::$messagesConversationStatus['CAMPAIGN'],
                self::$messagesConversationStatus['CAMPAIGN_READ'],
                self::$messagesConversationStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            $status = 'CAMPAIGN';
        }
        $conversation->setStatus(self::$messagesConversationStatus[$status]);
        $conversation->save();
    }

    /**
     * Save la conversation en "supprimée"
     * @param MessagingConversation $conversation
     */
    public function setDeleted(MessagingConversation $conversation)
    {
        $status = 'DELETED';
        if (in_array(
            $conversation->getStatus(),
            [
                self::$messagesConversationStatus['CAMPAIGN'],
                self::$messagesConversationStatus['CAMPAIGN_READ'],
                self::$messagesConversationStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            $status = 'DELETED_CAMPAIGN';
        }

        $conversation->setStatus(self::$messagesConversationStatus[$status]);
        $conversation->save();
    }

    ///// Actions sur les messages \\\\\
    /**
     * Passe le message en status 'à modérer'
     * @param MessagingMessage $message
     * @return MessagingMessage $message
     */
    public function moderate(MessagingMessage $message)
    {
        if (in_array(
            $message->getStatus(),
            [
                self::$messagesStatus['CAMPAIGN'],
                self::$messagesStatus['CAMPAIGN_READ'],
                self::$messagesStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            return $message;
        }

        $message->setStatus(self::$messagesStatus['IN_MODERATION']);
        $message->save();
        return $message;
    }

    /**
     * Passe le message en status 'validé'
     * @param MessagingMessage $message
     * @return MessagingMessage $message
     */
    public function accept(MessagingMessage $message)
    {
        if (in_array(
            $message->getStatus(),
            [
                self::$messagesStatus['CAMPAIGN'],
                self::$messagesStatus['CAMPAIGN_READ'],
                self::$messagesStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            return $message;
        }

        $oldStatus = $message->getStatus();
        $message->setStatus(self::$messagesStatus['ACCEPTED']);
        $message->save();

        if ($oldStatus !== self::$messagesStatus['ACCEPTED']) {
            $conversations = MessagingConversationQuery::create()
                ->useMessagingMessageConversationQuery()
                    ->filterByMessageId($message->getId())
                ->endUse()
            ->find();

            foreach ($conversations as $conv) {
                if ($conv->getUserWithId() == $message->getAuthorId()) {
                    $conv->setStatus(self::$messagesConversationStatus['NONE_READ']);
                    $conv->save();
                }
            }

            // Notification answer process
            if (count($conversations) > 1 && $conversations[0]->getMessageParentId() != $message->getId()) {
                // Notification process
                $this->container->get('notification_manager')->send(
                    $message->getTos(),
                    new MessagingNewAnswerReceivedNotification($this->container, $message->getAuthorId())
                );
            } else {
                // Notification new message process
                $this->container->get('notification_manager')->send(
                    $message->getTos(),
                    new MessagingNewMessageReceivedNotification($this->container, $message->getAuthorId())
                );
            }
        }

        return $message;
    }

    /**
     * Passe le message en status 'refusé'
     * @param MessagingMessage $message
     * @return MessagingMessage $message
     */
    public function reject(MessagingMessage $message)
    {
        if (in_array(
            $message->getStatus(),
            [
                self::$messagesStatus['CAMPAIGN'],
                self::$messagesStatus['CAMPAIGN_READ'],
                self::$messagesStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            return $message;
        }

        $message->setStatus(self::$messagesStatus['REJECTED']);
        $message->save();

        return $message;
    }

    /**
     * Passe le message en status 'supprimé' : un message supprimé n'est plus visible nul part
     * @param MessagingMessage $message
     * @return MessagingMessage $message
     */
    public function delete(MessagingMessage $message)
    {
        $status = 'DELETED';
        if (in_array(
            $message->getStatus(),
            [
                self::$messagesStatus['CAMPAIGN'],
                self::$messagesStatus['CAMPAIGN_READ'],
                self::$messagesStatus['DELETED_CAMPAIGN'],
            ]
        )) {
            $status = 'DELETED_CAMPAIGN';
        }

        $message->setStatus(self::$messagesStatus[$status]);
        $message->save();

        return $message;
    }

    /**
     * Renvoie les conversations selon leur statut
     * @param string|array $status
     * @param int $page
     * @param bool $doCount
     * @return MessagingConversationQuery|int|\PropelModelPager
     */
    public function getMessagesConversationsByStatus($status = "NONE_READ", $page = 0, $doCount = false)
    {
        if (!is_array($status)) {
            $status = [$status];
        }
        $statusFilter = [];
        foreach ($status as $s) {
            $statusFilter[] = self::$messagesConversationStatus[$s];
        }

        $arrayStatus = self::$messagesStatus;
        $visibleMessageStatusesToSort = [
            $arrayStatus['ACCEPTED'],
            $arrayStatus['CAMPAIGN'],
            $arrayStatus['CAMPAIGN_READ'],
        ];

        ;
        /** @var MessagingConversationQuery $query */
        $query = MessagingConversationQuery::create()
            // join all visible messages to sort by last sent
            ->join('MessagingMessageConversation lmmc')
            ->join('lmmc.MessagingMessage lmm')
            ->addJoinCondition('lmm', '`lmm`.`status` IN ('.implode(',', $visibleMessageStatusesToSort).')')
            ->withColumn('MAX(`lmm`.`updated_at`)', 'lastdate')
            ->addDescendingOrderByColumn('lastdate')
            // filter conversation as usual
            ->useMessagingMessageQuery()
                ->filterByStatus(array($arrayStatus['ACCEPTED'], $arrayStatus['CAMPAIGN']))
            ->endUse()
            ->filterByUserId($this->getUser()->getId())
            ->groupById() // group by conversation id, to use mysql MAX()
            ->filterByStatus($statusFilter);

        if ($doCount) {
            return $query->count();
        }

        if ($page == 0) {
            return $query;
        } else {
            return $query->paginate($page, self::$paginateLimit);
        }
    }

    /**
     * @param int $page
     * @param bool $doCount
     * @return MessagingConversationQuery|int|\PropelModelPager
     */
    public function getNoneReadConversations($page = 0, $doCount = false)
    {
        return $this->getMessagesConversationsByStatus(["NONE_READ", 'CAMPAIGN'], $page, $doCount);
    }

    /**
     * @param int $page
     * @param bool $doCount
     * @return MessagingConversationQuery|int|\PropelModelPager
     */
    public function getReadConversations($page = 0, $doCount = false)
    {
        return $this->getMessagesConversationsByStatus(["READ", 'CAMPAIGN_READ'], $page, $doCount);
    }

    /**
     * @param int $page
     * @param bool $doCount
     * @return MessagingConversationQuery|int|\PropelModelPager
     */
    public function getDeletedConversations($page = 0, $doCount = false)
    {
        return $this->getMessagesConversationsByStatus(['DELETED', 'DELETED_CAMPAIGN' ], $page, $doCount);
    }

    /**
     * Renvoie les messages envoyés de l'utilisateur en cours
     * @param int $page
     * @param bool $doCount
     * @return MessagingMessageQuery|int|\PropelModelPager
     */
    public function getSentMessages($page = 0,$doCount = false)
    {
        //TODO : mieux join pour éviter des requètes supplémenatires sur la page "messages envoyés"
        $status = self::$messagesStatus;

        $query = MessagingMessageQuery::create()
            ->filterByAuthorId($this->getUser()->getId())
            ->orderByCreatedAt(\Criteria::DESC)
            ->filterByStatus(array($status['DRAFT'], $status['DELETED']), \Criteria::NOT_IN)
            ->useMessagingMessageConversationQuery()
                ->groupByMessageId()
            ->endUse();
        if ($doCount) {
            return $query->count();
        } else {
            if ($page == 0) {
                return $query;
            } else {
                return $query->paginate($page,self::$paginateLimit);
            }
        }
    }

    /**
     * Renvoie les messages en brouillon de l'utilisateur en cours
     * @param int $page
     * @param bool $doCount
     * @return MessagingMessageQuery|int|\PropelModelPager
     */
    public function getDraftMessages($page = 0,$doCount = false)
    {    $status = self::$messagesStatus;
        //TODO : mieux join pour éviter des requètes supplémenatires sur la page "messages envoyés"
        $query = MessagingMessageQuery::create()->filterByAuthorId($this->getUser()->getId())
            ->filterByStatus($status['DRAFT'])
            ->orderByCreatedAt(\Criteria::DESC);

        if ($doCount) {
            return $query->count();
        } else if ($page == 0) {
            return $query;
        }

        return $query->paginate($page,self::$paginateLimit);
    }

    /**
     * @param string $subject
     * @param string $content
     * @param string $status
     * @return MessagingMessage
     */
    public function initMessage($subject, $content, $status, $usersTo = null, $groupTo = null)
    {
        $statusArray = self::$messagesStatus;
        $message = new MessagingMessage();
        $message->setSubject($subject)
            ->setContent($content)
            ->setAuthorId($this->getUser()->getId())
            ->setStatus($statusArray[$status])
            ->setTosTempList($usersTo)
            ->setGroupTos($groupTo);

        $message->save();

        return $message;
    }

    /**
     * Envoi d'un message
     *
     * @param MessagingMessage $message le message
     * @param string $status le statut du message
     * @param int $parentId le parent du message (si pas de parent => null)
     * @param array|User[] $validatedUsers les utilisateurs ayant le droit vérifié de recevoir le message
     * @param Request $request la reques pour gérer les pièces jointes
     * @return bool Si le message a bien été envoyé
     */
    public function sendMessage($message, $status, $parentId = null, $validatedUsers, Request $request = null)
    {
        if ($request != null) {
            $this->mediaManager->saveAttachments($message, $request, $validatedUsers);
        }

        $notifiedUsers = array();
        $sendSuccess = false;

        foreach ($validatedUsers as $user) {
            $conversation = new MessagingConversation();
            $conversation->setUserId($user->getId());
            $conversation->setUserWithId($this->getUser()->getId());
            $conversation->setMessageParentId($message->getId());

            if ($status == "ACCEPTED") {
                $conversation->setStatus(self::$messagesConversationStatus['NONE_READ']);
                $notifiedUsers[] = $user;
                $sendSuccess = true;
            } else {
                $conversation->setStatus(self::$messagesConversationStatus['IN_MODERATION']);
                $sendSuccess = false;
            }

            $conversation->save();
            $conversation->link($message);

            // Si on écrit à soi-même pas de double conversation
            if ($this->getUser()->getId() != $user->getId()) {
                $myConversation = new MessagingConversation();
                $myConversation->setUserId($this->getUser()->getId());
                $myConversation->setUserWithId($user->getId());
                $myConversation->setMessageParentId($message->getId());
                $myConversation->setStatus(self::$messagesConversationStatus['SENT']);
                $myConversation->save();
                $myConversation->link($message);
            }
        }

        // Notification process

        if(BNSAccess::isConnectedUser())
        {
            if (count($notifiedUsers) > 0) {
                // Nouveau message reçu PAR user POUR user(s)
                $this->container->get('notification_manager')->send(
                    $notifiedUsers,
                    new MessagingNewMessageReceivedNotification($this->container, $message->getAuthorId())
                );
            } else {
                // Nouveau message à modérer PAR user POUR enseignants (via permission) -> Forcément élève dans une classe
                $group = $this->container->get('bns.right_manager')->getUserManager()->getClassroomUserBelong(true);
                if ($group != null) {
                    $this->container->get('notification_manager')->send(
                        $this->container->get('bns.group_manager')
                            ->setGroup($group)
                            ->getUsersByPermissionUniqueName('MESSAGING_ACCESS_BACK', true),
                        new MessagingMessagePendingModerationNotification($this->container, $message->getAuthorId())
                    );
                }
            }
        }

        return $sendSuccess;
    }

    /**
     * Enregistrement d'un brouillon + pièces jointes
     * TODO : gestion de l'enregistrement des destinataires potentiels
     *
     * @param string $subject
     * @param string $content
     * @return MessagingMessage
     */
    public function createDraft($subject, $content)
    {
        $message = $this->initMessage($subject, $content, "DRAFT");

        return $message;
    }

    /**
     * Répondre à un message dans une converation
	 *
     * @param MessagingConversation $conversation la conversation en cours
     * @param string $content le contenu du message
     * @param string $status le statut de la réponse
     * @param Request $request
     * @return MessagingMessage the created answer
     */
    public function answerMessage(MessagingConversation $conversation, $content, $status, Request $request)
    {
        // Création du message
        $parentMessage = $conversation->getMessage();
        if ($request->get('toAll')) {
            $usersTo = array_unique(array_merge([$parentMessage->getAuthorId()], unserialize($parentMessage->getTosTempList())));
            $answer = $this->initMessage($parentMessage->getSubject(), $content, $status, serialize($usersTo), $parentMessage->getGroupTos());
        } else {
            $answer = $this->initMessage($parentMessage->getSubject(), $content, $status);
        }
        $this->mediaManager->saveAttachments($answer, $request, $conversation->getUserRelatedByUserWithId());

        // Mise à jour des conversations
        $oppositeConversation = $conversation->getOpposite();
        if ($status == "ACCEPTED") {
            if( $request->get('toAll')) {
                if (is_array($parentMessage->getGroupTos())) {
                    foreach ($parentMessage->getGroupTos() as $groupTo) {
                        $usersTo = array_unique(array_merge($usersTo, $this->container->get('bns.group_manager')->setGroupById($groupTo)->getUserIdsWithPermission('MESSAGING_ACCESS')));
                    }
                }
                $usersTo = array_diff($usersTo, [$conversation->getUserWithId()]);
                MessagingConversationQuery::create()->filterByMessageParentId($conversation->getMessageParentId())
                    ->filterByUserId($usersTo)
                    ->filterByUserWithId($conversation->getUserWithId())
                    ->update(['Status' => self::$messagesConversationStatus['NONE_READ']]);
                $oppositeConversations = MessagingConversationQuery::create()->filterByMessageParentId($conversation->getMessageParentId())
                    ->filterByUserId($usersTo)
                    ->filterByUserWithId($conversation->getUserWithId())->find();
                foreach ($oppositeConversations as $conversationOpposite) {
                    $conversationOpposite->link($answer);
                }
            }
            $oppositeConversation->setStatus(self::$messagesConversationStatus['NONE_READ']);
            $oppositeConversation->save();

            // Notification process
            // Nouvelle réponse reçue PAR user POUR user
            $this->container->get('notification_manager')->send(
                $conversation->getUserRelatedByUserWithId(),
                new MessagingNewAnswerReceivedNotification($this->container, $conversation->getUserId())
            );
        } else {
            // Notification process
            // Nouveau message à modérer PAR user POUR enseignants (via permission)

            $group = $this->container->get('bns.right_manager')->getCurrentGroup();
            if ($group->getType() === 'CLASSROOM') {
                $this->container->get('notification_manager')->send(
                    $this->container->get('bns.group_manager')
                        ->setGroup($group)
                        ->getUsersByPermissionUniqueName('MESSAGING_ACCESS_BACK', true), new MessagingMessagePendingModerationNotification($this->container, $conversation->getUserId()));
            } else {
                $user = $this->getUser();
                $classrooms = $this->container
                    ->get('bns.user_manager')
                    ->setUser($user)
                    ->getGroupsUserBelong('CLASSROOM');

                if (count($classrooms) > 0) {
                    $classroom = $classrooms[0];
                    $this->container->get('notification_manager')->send(
                        $this->container->get('bns.group_manager')
                            ->setGroup($classroom)
                            ->getUsersByPermissionUniqueName('MESSAGING_ACCESS_BACK', true), new MessagingMessagePendingModerationNotification($this->container, $conversation->getUserId()));
                }
            }
        }
        if (!$request->get('toAll')) {
        $conversation->link($answer);
        }
        // Pour les correspondance à soi-même, pas de doublon
        if ($conversation->getId() != $oppositeConversation->getId()) {
            $oppositeConversation->link($answer);
        }

        return $answer;
    }

    /**
     * L'User en cours est-il un destinataire du message en paramètre
     * @param MessagingMessage $message le message
     * @return bool
     */
    public function isTo(MessagingMessage $message)
    {
        return MessagingConversationQuery::create()
                ->filterByUserWithId($this->getUser()->getId())
                ->useMessagingMessageConversationQuery()
                    ->filterByMessagingMessage($message)
                ->endUse()
            ->count() > 0
        ;
    }

    /**
     * L'User en cours est-il l'auetur du message en paramètre
     * @param MessagingMessage $message le message
     * @return bool
     */
    public function isAuthor(MessagingMessage$message)
    {
        return $message->getAuthorId() == $this->getUser()->getId();
    }

    /**
     * L'User en cours peut-il lire le message en paramètre
     * @param MessagingMessage $message le message
     * @return bool
     */
    public function canRead($message)
    {
        return $this->isTo($message)
            || $this->isAuthor($message)
            || in_array($message->getAuthorId(), $this->getAuthorisedUsersIds())
        ;
    }

    /**
     * Renvoie les "enfants" d'un message
     * @param MessagingMessage $message
     * @param User $userWith
     * @return MessagingMessageQuery
     *
     * @deprecated Use method from Query class instead.
     * @see MessagingMessageQuery::filterChildrenForConversation()
     */
    public function getChildren(MessagingMessage $message, User $userWith = null)
    {
        //if we havent AuthorId (for campaignMessage)
        if (!$userWith) {
            return MessagingMessageQuery::create()->where('1 <> 1',null, \Criteria::CUSTOM);
        }

        //Correspond au destinataire avec lequel on a la conversation
        return MessagingMessageQuery::create()
            ->useMessagingMessageConversationQuery()
                ->useMessagingConversationQuery()
                    ->filterByUserId($this->getUser()->getId())
                    ->filterByUserWithId($userWith->getId())
                    ->filterByMessageParentId($message->getId())
                ->endUse()
            ->endUse()
            ->orderByCreatedAt(\Criteria::ASC)
            ->filterById($message->getId(),\Criteria::NOT_EQUAL)
        ;
    }

    /**
     * Recherche de messages depuis un terme
     * @param string $word
     * @return MessagingConversationQuery
     */
    public function getSearchQuery($word)
    {
        return MessagingConversationQuery::create()
            ->groupByMessageParentId()
            ->join('MessagingMessage')
            //Fait par @Ben !! (mais je sais pas ce que ça fait)
            ->where('MessagingMessage.status IN ?', array(
                self::$messagesConversationStatus['NONE_READ'],
                self::$messagesConversationStatus['READ'],
                self::$messagesConversationStatus['DELETED']
            ))
            //Fin du @Ben
            ->where('MessagingMessage.subject like ?', '%'. $word. '%')
            ->_or()
            ->where('MessagingMessage.content like ?', '%'. htmlentities($word) . '%')
            ->filterByUserId($this->getUser()->getId())
            ->orderByCreatedAt(\Criteria::DESC)
        ;
    }

    /**
     * Gets an array of ids of users that can be managed by the current user.
     * Optionally users can be restricted to the given groups.
     *
     * @param array|\BNS\App\CoreBundle\Model\Group[] $groups
     * @return array
     */
    public function getAuthorisedUsersIds($groups = array())
    {
        $groupManager = $this->container->get('bns.group_manager');
        $rightManager = $this->container->get('bns.right_manager');
        $mustCheckRights = false;
        if (count($groups)) {
            $mustCheckRights = true;
        } else {
            $groups = $rightManager->getGroupsWherePermission("MESSAGING_ACCESS_BACK");
        }
        $moderationUsers = array();

        foreach ($groups as $group) {
            $groupId = is_numeric($group) ? $group : $group->getId();
            if ($mustCheckRights && !$rightManager->hasRight('MESSAGING_ACCESS_BACK', $groupId)) {
                continue;
            }
            if (is_numeric($group)) {
                $groupManager->setGroupById($group);
            } else {
                $groupManager->setGroup($group);
            }

            // keep only pupils: parents should not be moderated
            $usersObjects = $groupManager->getUsersByRoleUniqueName('PUPIL', true);
            if (count($usersObjects) > 0) {
                foreach ($usersObjects as $userObject) {
                    $moderationUsers[] = $userObject->getId();
                }
            }
        }

        return array_unique($moderationUsers);
    }

}

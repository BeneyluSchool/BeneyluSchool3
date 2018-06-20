<?php

namespace BNS\App\MessagingBundle\Model;

use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\MessagingBundle\Model\om\BaseMessagingMessageQuery;

class MessagingMessageQuery extends BaseMessagingMessageQuery
{

    /**
     * Filters for the given conversation, keeping only messages visible to the sender.
     *
     * @param MessagingConversation $conversation
     * @param bool $removeFirstMessage Whether to remove first message from collection
     * @return MessagingMessageQuery|\ModelCriteria
     */
    public function filterChildrenForConversation(MessagingConversation $conversation, $removeFirstMessage = false)
    {
        // in a conversation, I can see:
        //  1. my accepted and my moderated messages
        $criteria = MessagingMessageQuery::create();
        $criteria->condition('sender', MessagingMessagePeer::AUTHOR_ID.' = ?', $conversation->getUserId());
        $criteria->condition('my_status', MessagingMessagePeer::STATUS.' IN ?', [
            BNSMessageManager::$messagesStatus['ACCEPTED'],
            BNSMessageManager::$messagesStatus['IN_MODERATION'],
        ]);
        $criteria->combine(['sender', 'my_status'], \Criteria::LOGICAL_AND, 'mine');
        //  2. their accepted messages
        $criteria->condition('not_sender', MessagingMessagePeer::AUTHOR_ID.' <> ?', $conversation->getUserId());
        $criteria->condition('their_status', MessagingMessagePeer::STATUS.' IN ?', [
            BNSMessageManager::$messagesStatus['ACCEPTED'],
        ]);
        $criteria->combine(['not_sender', 'their_status'], \Criteria::LOGICAL_AND, 'theirs');
        // 3. it's a campaign
        $criteria->condition('no_sender', MessagingMessagePeer::AUTHOR_ID.' IS NULL');
        $criteria->condition('campaign_status', MessagingMessagePeer::STATUS.' IN ?', [
            BNSMessageManager::$messagesStatus['CAMPAIGN'],
        ]);
        $criteria->combine(['no_sender', 'campaign_status'], \Criteria::LOGICAL_AND, 'campaigns');

        $criteria->combine(['mine', 'theirs', 'campaigns'], \Criteria::LOGICAL_OR);

        /** @var MessagingMessageQuery $query */
        $query = $this->mergeWith($criteria);

        return $query
            ->useMessagingMessageConversationQuery()
                ->filterByMessagingConversation($conversation)
            ->endUse()
            ->_if($removeFirstMessage)
                ->filterById($conversation->getMessageParentId(), \Criteria::NOT_EQUAL)
            ->_endif()
        ;
    }

}

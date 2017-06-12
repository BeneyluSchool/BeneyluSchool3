<?php

namespace BNS\App\MessagingBundle\Model;

use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageConversation;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MessagingBundle\Model\MessagingMessagePeer;
use BNS\App\MessagingBundle\Model\om\BaseMessagingConversation;
use Criteria;
class MessagingConversation extends BaseMessagingConversation
{
	
	/**
	 * Raccourci vers le message associé en parent
	 * @return MessagingMessage
	 */
	public function getMessage(){
		return $this->getMessagingMessage();
	}
	
	/**
	 * Raccourci vers le dernier message associé en parent
	 * @return MessagingMessage
	 */
	public function getLastMessage(){
		if(!isset($this->last_message)){
		$this->last_message = MessagingMessageQuery::create()
			->useMessagingMessageConversationQuery()
				->filterByConversationId($this->getId())
			->endUse()
			->orderByCreatedAt(Criteria::DESC)
			->findOne();
		}
		return $this->last_message;
	}

	public function getVisibleLastMessage()
	{
		return MessagingMessageQuery::create()
			->filterChildrenForConversation($this)
			->orderByCreatedAt(\Criteria::DESC)
			->findOne()
		;
	}

	public function getVisibleChildren()
	{
		return MessagingMessageQuery::create()
			->filterChildrenForConversation($this, true)
			->orderByCreatedAt(\Criteria::ASC)
			->find()
		;
	}

	/**
	 * Renvoie l'opposée : même user (inversés) et même parent ID 
	 * @return MessagingConversation
	 */
	public function getOpposite(){
		return MessagingConversationQuery::create()
			->filterByMessageParentId($this->getMessageParentId())
			->filterByUserId($this->getUserWithId())
			->filterByUserWithId($this->getUserId())
			->findOne();
	}
	/**
	 * Lie un message et une conversation
	 * @param MessagingMessage $message
	 */
	public function link($message)
	{
		$linking = new MessagingMessageConversation();
		$linking->setMessageId($message->getId());
		$linking->setConversationId($this->getId());
		$linking->save();
	}
	
	public function countMessages($status = "1")
	{
		$c = new Criteria();
		$c->add(MessagingMessagePeer::STATUS,$status);
		return count($this->getMessagingMessageConversationsJoinMessagingMessage($c));
	
	}
}

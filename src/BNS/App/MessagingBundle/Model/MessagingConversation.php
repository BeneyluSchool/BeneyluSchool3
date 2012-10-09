<?php

namespace BNS\App\MessagingBundle\Model;

use BNS\App\MessagingBundle\Model\om\BaseMessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessageConversation;

class MessagingConversation extends BaseMessagingConversation
{
	/**
	 * Raccourci vers le message associé en parent
	 * @return MessagingConversation
	 */
	public function getMessage(){
		return $this->getMessagingMessage();
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
}

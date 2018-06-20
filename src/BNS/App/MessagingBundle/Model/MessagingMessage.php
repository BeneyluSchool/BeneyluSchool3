<?php

namespace BNS\App\MessagingBundle\Model;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\MessagingBundle\Model\om\BaseMessagingMessage;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Utils\StringUtil;

class MessagingMessage extends BaseMessagingMessage implements AutosaveInterface
{

	use RichTextParser;

	public function getRichContent()
	{
		return $this->parse($this->getContent());
	}

	/**
	 * Renvoie un extrait du message
	 */
	public function getExtract($size = 50)
	{
		$extract = StringUtil::substrws($this->getContent(), $size, true);
		$end = strlen($extract) > $size ? " ..." : "";
		return $extract . $end;
	}

	/**
	 * Renvoie les destinataires d'un message
	 */
	public function getTos()
	{
		return UserQuery::create()
			->useMessagingConversationRelatedByUserWithIdQuery()
				->filterByUserWithId($this->getAuthorId(),\Criteria::NOT_EQUAL)
				->useMessagingMessageConversationQuery()
					->filterByMessageId($this->getId())
				->endUse()
			->endUse()
		->find();
	}

	/**
	 * Renvoie les destinataires d'un message pour affiche (avec auteur si dans la liste)
	 */
	public function showTos()
	{
	    if (count($this->getGroupTos()) === 0) {
            return UserQuery::create()
                ->useMessagingConversationRelatedByUserWithIdQuery()
                ->filterByUserId($this->getAuthorId())
                ->useMessagingMessageConversationQuery()
                ->filterByMessageId($this->getId())
                ->endUse()
                ->endUse()
                ->find();
        } else {
	        return UserQuery::create()->filterById(unserialize($this->getTosTempList()))->find();
        }
	}

	/**
	 * Compte le nombre de destinataires
	 * @return type
	 */
	public function countTos(){
		//On divise par deux car 2 conversations à chaque fois, du coté de chaque utilisateur
		return round (count($this->getMessagingMessageConversations()) / 2,0,PHP_ROUND_HALF_UP);
	}

	/**
	 * Imprime le statut d'un message
	 * @return string
	 */
	public function printStatus()
	{
		$availableStatus = array_flip(BNSMessageManager::$messagesStatus);
		return strtolower($availableStatus[$this->getStatus()]);
	}

	/**
	 * Le message est il encore en brouillon
	 * @return boolean
	 */
	public function isDraft()
	{
		$availableStatus = BNSMessageManager::$messagesStatus;
		return $this->getStatus() == $availableStatus['DRAFT'];
	}

	/**
	 * Méthode pour l'autosave
	 * @param array $objects
	 * @return type
	 * @throws AccessDeniedHttpException
	 */
	public function autosave(array $objects)
	{
		$container = BNSAccess::getContainer();
		$rightManager = $container->get('bns.right_manager');

		// Check rights
		$rightManager->forbidIf(!$rightManager->hasRightSomeWhere('MESSAGING_ACCESS'));

		// New object : save into database and return his new primary key

		$this->setSubject($objects['subject']);
		$this->setContent($objects['content']);
		$status = BNSMessageManager::$messagesStatus;
		$this->setStatus($status['DRAFT']);
		$this->setAuthorId($rightManager->getUserSession()->getId());
		$this->save();

		return $this->getId();
	}

	public function showGroupTos()
    {
        return GroupQuery::create()
            ->filterById($this->getGroupTos())
            ->find();
    }
}

<?php

namespace BNS\App\MessagingBundle\Messaging;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\NotificationBundle\Notification\MessagingBundle\MessagingNewMessageReceivedNotification;

/**
 * @author Taelman Eymeric
 * Classe permettant la gestion des messages
 */
class BNSMessageManager
{
	
	protected $user;
	protected $resourceManager;
	
	public static $paginateLimit = 10;
	
	/**
	 * Status des conversations
	 */
	protected $messagesConversationStatus = array(
		'SENT' => 4,
		'IN_MODERATION' => 3,
		'NONE_READ' => 2,
		'READ' => 1,
		'DELETED' => 0
	);
	/**
	 * Status des messages
	 */
	public static $messagesStatus = array(
		'DRAFT' => 3,
		'IN_MODERATION' => 2,
		'ACCEPTED' => 1,
		'REJECTED' => 0,
		'DELETED' => -1
	);
	
	public function __construct($resourceManager)
    {
        $this->setUser($this->getCurrentUser());
		$this->resourceManager = $resourceManager;
    }
	
	public function getCurrentUser()
	{
		return BNSAccess::getUser();
	}
	
	public function getUser()
	{
		return $this->user;
	}
	
	public function setUser($user)
	{
		$this->user = $user;
	}
	
	////////  Actions sur les conversations  \\\\\\\\\\
	
	/**
	 * Renvoie le string correspondant au status de la conversation
	 * @param MessagingConversation $conversation
	 * @return string
	 */
	public function getStatus($conversation){
		$conversationStatus = array_flip($this->messagesConversationStatus);
		return $conversationStatus[$conversation->getStatus()];
	}
	
	/**
	 * Save la conversation en "lue"
	 * @param type $conversation
	 */
	public function setRead($conversation)
	{
		$conversation->setStatus($this->messagesConversationStatus['READ']);
		$conversation->save();
	}
	
	/**
	 * Save la conversation en "supprimée"
	 * @param type $conversation
	 */
	public function setDeleted($conversation){
		$conversation->setStatus($this->messagesConversationStatus['DELETED']);
		$conversation->save();
	}
	
	///// Actions sur les messages \\\\\
	/**
	 * Passe le message en status 'à modérer'
	 * @param MessagingMessage $message
	 * @return MessagingMessage $message
	 */
	public function moderate($message)
	{
		$message->setStatus(self::$messagesStatus['IN_MODERATION']);
		$message->save();
		return $message;
	}
	
	/**
	 * Passe le message en status 'validé'
	 * @param MessagingMessage $message
	 * @return MessagingMessage $message
	 */
	public function accept($message)
	{
		$oldStatus = $message->getStatus();
		$message->setStatus(self::$messagesStatus['ACCEPTED']);
		$message->save();
		
		if ($oldStatus == self::$messagesStatus['IN_MODERATION']) {
			foreach ($message->getTos() as $user) {
				$notification = new MessagingNewMessageReceivedNotification($user, $message->getAuthorId());
				$notification->send();
			}
		}
		
		return $message;
	}
	
	/**
	 * Passe le message en status 'refusé'
	 * @param MessagingMessage $message
	 * @return MessagingMessage $message
	 */
	public function reject($message)
	{
		$message->setStatus(self::$messagesStatus['REJECTED']);
		$message->save();
		return $message;
	}
	
	/**
	 * Passe le message en status 'supprimé' : un message supprimé n'est plus visible nul part
	 * @param MessagingMessage $message
	 * @return MessagingMessage $message
	 */
	public function delete($message)
	{
		$message->setStatus(self::$messagesStatus['DELETED']);
		$message->save();
		return $message;
	}

	
	/**
	 * Renvoie les conversations selon leur statut
	 * @param string $status
	 * @return MessageConversation
	 */
	public function getMessagesConversationsByStatus($status = "NONE_READ",$page = 0,$doCount = false)
	{	$arrayStatus = self::$messagesStatus;
		$query = MessagingConversationQuery::create()
			->useMessagingMessageQuery()
				->filterByStatus($arrayStatus['ACCEPTED'])
			->endUse()
			->filterByUserId($this->getUser()->getId())
			->orderByCreatedAt(\Criteria::DESC)
			->filterByStatus($this->messagesConversationStatus[$status]);
		
		if($doCount == true)
			return $query->count();
		if($page == 0)
			return $query->find();
		else
			return $query->paginate($page,self::$paginateLimit);
	}
	
	public function getNoneReadConversations($page = 0, $doCount = false)
	{
		return $this->getMessagesConversationsByStatus("NONE_READ",$page,$doCount);
	}
	
	public function getReadConversations($page = 0, $doCount = false)
	{
		return $this->getMessagesConversationsByStatus("READ",$page,$doCount);
	}
	
	public function getDeletedConversations($page = 0, $doCount = false)
	{
		return $this->getMessagesConversationsByStatus("DELETED",$page,$doCount);
	}
	
	/**
	 * Renvoie les messages envoyés de l'utilisateur en cours
	 */
	public function getSentMessages($page = 0,$doCount = false)
	{
		//TODO : mieux join pour éviter des requêtes supplémenatires sur la page "messages envoyés"
		$status = self::$messagesStatus;
		
		$query = MessagingMessageQuery::create()->filterByAuthorId($this->getUser()->getId())
			->orderByCreatedAt(\Criteria::DESC)
			->filterByStatus(array($status['DRAFT'],$status['DELETED']),\Criteria::NOT_IN)
			->useMessagingMessageConversationQuery()
				->groupByMessageId()
			->endUse();
		if($doCount)
			return $query->count();
		else{
			if($page == 0){
				return $query->find();
			}else{
				return $query->paginate($page,self::$paginateLimit);
			}
		}
	}
	
	/**
	 * Renvoie les messages en brouillon de l'utilisateur en cours
	 */
	public function getDraftMessages($page = 0,$doCount = false)
	{	$status = self::$messagesStatus;
		//TODO : mieux join pour éviter des requêtes supplémenatires sur la page "messages envoyés"
		$query = MessagingMessageQuery::create()->filterByAuthorId($this->getUser()->getId())
			->filterByStatus($status['DRAFT'])
			->orderByCreatedAt(\Criteria::DESC);
		
		if($doCount)
			return $query->count();
		else{
			if($page == 0){
				return $query->find();
			}else{
				$o = $query->paginate($page,self::$paginateLimit);
				return $o;
			}
		}
	}
	
	public function initMessage($subject,$content,$status)
	{	$statusArray = self::$messagesStatus;
		$message = new MessagingMessage();
		$message->setSubject($subject);
		$message->setContent($content);
		$message->setAuthorId($this->getUser()->getId());
		$message->setStatus($statusArray[$status]);
		$message->save();
		return $message;
	}
	
	/**
	 * Envoi d'un message
	 * @param type $subject le sujet du message
	 * @param type $content le contenu du message
	 * @param type $status le statut du message (bool)
	 * @param type $parentId le parent du message (si pas de parent => null)
	 * @param type $validatedUsers les utilisateurs ayant le droit vérifié de recevoir le message
	 * @param type $request la reques pour gérer les pièces jointes
	 */
	public function sendMessage($message,$status, $parentId = null,$validatedUsers,$request)
	{
		$this->resourceManager->setUser($this->getUser());
		$this->resourceManager->saveAttachments($message,$request,$validatedUsers);
		
		foreach ($validatedUsers as $user) {
			$conversation = new MessagingConversation();
			$conversation->setUserId($user->getId());
			$conversation->setUserWithId($this->getUser()->getId());
			$conversation->setMessageParentId($message->getId());
			if ($status == "ACCEPTED") {
				$conversation->setStatus($this->messagesConversationStatus['NONE_READ']);
				
				$notification = new MessagingNewMessageReceivedNotification($user, $message->getAuthorId());
				$notification->send();
			}
			else {
				$conversation->setStatus($this->messagesConversationStatus['IN_MODERATION']);
			}
			
			$conversation->save();
			
			$conversation->link($message);
			
			//Si on écrit à soi même pas de double conversation
			if($this->getUser()->getId() != $user->getId()){
				$myConversation = new MessagingConversation();
				$myConversation->setUserId($this->getUser()->getId());
				$myConversation->setUserWithId($user->getId());
				$myConversation->setMessageParentId($message->getId());
				$myConversation->setStatus($this->messagesConversationStatus['SENT']);
				$myConversation->save();
				$myConversation->link($message);
			}
		}
	}
	
	/**
	 * Enregistrement d'un brouillon + pièces jointes
	 * TODO : gestion de l'enristrement des destinataires potentiels
	 */
	public function createDraft($subject,$content)
	{
		$message = $this->initMessage($subject,$content,"DRAFT");
		return $message;
	}
	
	/**
	 * Répondre à un message dans une converation
	 * @param type $conversation la conversation en cours
	 * @param type $content le contenu du message
	 * @param type $status le statut de la réponse
	 */
	public function answerMessage(MessagingConversation $conversation,$content,$status,$request)
	{	
		//Création du message
		$parentMessage = $conversation->getMessage();
		$answer = $this->initMessage($parentMessage->getSubject(), $content, $status);
		$this->resourceManager->setUser($this->getUser());
		$this->resourceManager->saveAttachments($answer,$request,$conversation->getUserRelatedByUserWithId());
		
		//Mise à jour des conversations
		$oppositeConversation = $conversation->getOpposite();
		if($status == "ACCEPTED"){
			$oppositeConversation->setStatus($this->messagesConversationStatus['NONE_READ']);
			$oppositeConversation->save();
		}
		$conversation->link($answer);
		//Pour les correspondance à soi même, pas de doublon
		if($conversation->getId() != $oppositeConversation->getId()){
			$oppositeConversation->link($answer);
		}
	}

	/**
	 * L'User en cours est-il un déstinataire du message en paramètre
	 * @param type $message le message
	 * @return type boolean
	 */
	public function isTo($message)
	{
		return MessagingConversationQuery::create()->filterByMessageParentId($message->getId())->filterByUserId($this->getUser()->getId())->findOne() != null;
	}
	
	/**
	 * L'User en cours est-il l'auetur du message en paramètre
	 * @param type $message le message
	 * @return type boolean
	 */
	public function isAuthor($message)
	{
		return $message->getAuthorId() == $this->getUser()->getId();
	}
	
	/**
	 * L'User en cours peut-il lire le message en paramètre
	 * @param type $message le message
	 * @return type boolean
	 */
	public function canRead($message){
		return $this->isTo($message) || $this->isAuthor($message);
	}
	
	/**
	 * Renvoie les "enfants" d'un message 
	 * @param type $message
	 * @return type
	 */
	public function getChildren($message)
	{
		//Correspond au destinataire avec lequel on a la conversation
		return MessagingMessageQuery::create()
			->useMessagingMessageConversationQuery()
				->useMessagingConversationQuery()
					->filterByUserId($this->getUser()->getId())
					->filterByMessageParentId($message->getId())
				->endUse()
			->endUse()
			->orderByCreatedAt(\Criteria::ASC)
			->filterById($message->getId(),\Criteria::NOT_EQUAL)
			->find();
	}
	
	/**
	 * Recherche de messages depuis un terme
	 */
	public function search($word,$page = 0)
	{
		$query = MessagingConversationQuery::create()
			->groupByMessageParentId()
			->join('MessagingMessage')
			//Fait par @Ben !! (mais je sais pas ce que ça fait)
			->where('MessagingMessage.status IN ?',array($this->messagesConversationStatus['NONE_READ'],$this->messagesConversationStatus['READ'],$this->messagesConversationStatus['DELETED']))
			//Fin du @Ben
			->where('MessagingMessage.subject like ?', '%'. $word. '%')
			->_or()->where('MessagingMessage.content like ?', '%'. $word. '%')
			->filterByUserId($this->getUser()->getId())
			->orderByCreatedAt(\Criteria::DESC);
		return array(
			'messages' => $query->paginate(0,self::$paginateLimit),
			'count' => $query->count()
		);
	}
}
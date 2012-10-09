<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use BNS\App\MessagingBundle\Controller\FrontController;
use BNS\App\MessagingBundle\Form\Type\MessageType;
use BNS\App\MessagingBundle\Form\Type\AnswerType;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;

class FrontLightAjaxController extends FrontController
{	
	/**
	 * Reole que l'on affiche dans le User Picker
	 * @return Array
	 */
	protected function getShownRoles()
	{
		$rightManager = $this->get('bns.right_manager');
		$shownRoles = array();
		if($rightManager->hasRightSomeWhere('MESSAGING_SEND_PUPILS')){
			$shownRoles[] = "PUPIL";
		}
		if($rightManager->hasRightSomeWhere('MESSAGING_SEND_PARENTS')){
			$shownRoles[] = "PARENT";
		}
		if($rightManager->hasRightSomeWhere('MESSAGING_SEND_TEACHERS')){
			$shownRoles[] = "TEACHER";
		}
		return $shownRoles;
	}
	
	/**
	 * Boîte de réception
	 * @Route("/light-inbox", name="BNSAppMessagingBundle_front_light_ajax_inbox", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:inbox.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function inboxAction()
	{	
		$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 0;
		$messageManager = $this->get('bns.message_manager');
		//Récupération des messages non lus
		$conversationsNoneRead = $messageManager->getNoneReadConversations();
		//Récupération des messages déjà lus
		$conversationsRead = $messageManager->getReadConversations($page);
		$nbConversationsRead = $messageManager->getReadConversations($page,true);
		
		$nbDraftMessages = $messageManager->getDraftMessages($page,true);
		
		return array(
			'conversations_none_read' => $conversationsNoneRead,
			'conversations_read' => $conversationsRead,
			'nbConversationsRead' => $nbConversationsRead,
			'paginateLimit' => BNSMessageManager::$paginateLimit,
			'page' => $page,
			'nb_draft_messages' => $nbDraftMessages
		);
	}
	/**
	 * Boîte d'envoi
	 * @Route("/light-outbox", name="BNSAppMessagingBundle_front_light_ajax_outbox", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:outbox.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function outboxAction()
	{
		$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 0;
		$messageManager = $this->get('bns.message_manager');
		$sentMessages = $messageManager->getSentMessages($page);
		$nbSentMessages = $messageManager->getSentMessages(null,true);
		
		return array(
			'messages' => $sentMessages,
			'nb_sent_messages' => $nbSentMessages,
			'paginate_limit' => BNSMessageManager::$paginateLimit,
			'page' => $page
		);
	}
	/**
	 * Boîte de brouillons
	 * @Route("/light-draftbox", name="BNSAppMessagingBundle_front_light_ajax_draftbox", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:draftbox.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function draftboxAction()
	{
		$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 0;
		$messageManager = $this->get('bns.message_manager');
		$draftMessages = $messageManager->getDraftMessages($page);
		$nbDraftMessages = $messageManager->getDraftMessages($page,true);
		
		return array(
			'messages' => $draftMessages,
			'nb_draft_messages' => $nbDraftMessages,
			'paginate_limit' => BNSMessageManager::$paginateLimit,
			'page' => $page,
			'nb_none_read_messages' => $messageManager->getNoneReadConversations(0,true)
		);
	}
	/**
	 * Boîte des messafes supprimés
	 * @Route("/light-deletedbox", name="BNSAppMessagingBundle_front_light_ajax_deletedbox", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:deletedbox.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function deletedboxAction()
	{
		$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 0;
		$messageManager = $this->get('bns.message_manager');
		//Récupération des messages non lus
		$conversationsDeleted = $messageManager->getDeletedConversations($page);
		$nbConversationsDeleted = $messageManager->getDeletedConversations($page,true);
		return array(
			'conversations_deleted' => $conversationsDeleted,
			'nb_conversations_deleted' => $nbConversationsDeleted,
			'page' => $page,
			'paginateLimit' => BNSMessageManager::$paginateLimit
		);
	}
	
	/**
	 * Vue du message
	 * @Route("/light-conversation/{conversationId}", name="BNSAppMessagingBundle_front_light_ajax_conversation", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:conversation.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function conversationAction($conversationId)
	{
		$conversation = MessagingConversationQuery::create()->joinMessagingMessage()->findPk($conversationId);
		$message = $conversation->getMessage();
		//Vérification du droit de lecture
		$messageManager = $this->get('bns.message_manager');
		
		if($messageManager->getStatus($conversation) == "NONE_READ"){
			$messageManager->setRead($conversation);
		}
		
		$rightManager = $this->get('bns.right_manager');
		$rightManager->forbidIf(!$messageManager->canRead($message));
		
		$children = $messageManager->getChildren($message);
		//Formulaire de réponse auquel on passe l'id de conversation
		$form = $this->createForm(new AnswerType(),array('conversation_id' => $conversation->getId()));
		
		return array(
			'message' => $message,
			'children' => $children,
			'form' => $form->createView(),
			'conversation_id' => $conversation->getId(),
			'status' => $messageManager->getStatus($conversation)
		);
	}
	
	/**
	 * Suppression d'une conversation
	 * @Route("/light-conversation-delete/{conversationId}", name="BNSAppMessagingBundle_front_light_ajax_conversation_delete", options={"expose"=true}))
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function conversationDeleteAction($conversationId)
	{
		$conversation = MessagingConversationQuery::create()->joinMessagingMessage()->findPk($conversationId);
		
		$rightManager = $this->get('bns.right_manager');
		$messageManager = $this->get('bns.message_manager');
		//Vérification des droits de suppressions
		$rightManager->forbidIf($conversation->getUserId() != $rightManager->getUserSession()->getId());
		
		$messageManager->setDeleted($conversation);
		
		return $this->forward('BNSAppMessagingBundle:FrontLightAjax:inbox');
	}
	
	/**
	 * Suppression d'une conversation
	 * @Route("/light-conversation-restore/{conversationId}", name="BNSAppMessagingBundle_front_light_ajax_conversation_restore", options={"expose"=true}))
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function conversationRestoreAction($conversationId)
	{
		$conversation = MessagingConversationQuery::create()->joinMessagingMessage()->findPk($conversationId);
		
		$rightManager = $this->get('bns.right_manager');
		$messageManager = $this->get('bns.message_manager');
		//Vérification des droits de suppressions
		$rightManager->forbidIf($conversation->getUserId() != $rightManager->getUserSession()->getId());
		
		$messageManager->setRead($conversation);
		
		return $this->forward('BNSAppMessagingBundle:FrontLightAjax:deletedbox');
	}
	
	
	/**
	 * Voir un message (envoyé)
	 * @Route("/light-message/{messageId}", name="BNSAppMessagingBundle_front_light_ajax_message", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:message.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function messageAction($messageId)
	{
		$message = MessagingMessageQuery::create()->findOneById($messageId);
		//Vérification du droit de lecture
		$messageManager = $this->get('bns.message_manager');
		$rightManager = $this->get('bns.right_manager');
		$rightManager->forbidIf(!$messageManager->canRead($message));
		
		return array(
			'message' => $message
		);
	}
	
	/**
	 * Nouveau message
	 * @Route("/light-new-message", name="BNSAppMessagingBundle_front_light_ajax_new_message", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:new_message.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function newMessageAction()
	{
		$form = $this->createForm(new MessageType());
		$rightManager = $this->get('bns.right_manager');
		
		if($this->getRequest()->isMethod('post')){
			
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()){
				$data = $form->getData();
				$toList = array_unique(explode(',',$data['to']));
				if(!is_array($toList)){
					$toList = array($data['to']);
				}
				$users = UserQuery::create()->findById($toList);
				
				//Vérification des droits
				$userManager = $this->get('bns.user_manager');
				
				$myRights['ALL'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_ALL');
				$myRights['CHILD'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_CHILD');
				$myRights['PARENT'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_PARENT');
				$myRights['PUPILS'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_PUPILS');
				$myRights['PARENTS'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_PARENTS');
				$myRights['TEACHERS'] = $rightManager->getGroupIdsWherePermission('MESSAGING_SEND_TEACHERS');
				$myRights['NO_EXTERNAL_MODERATION'] = $rightManager->getGroupIdsWherePermission('MESSAGING_NO_EXTERNAL_MODERATION');
				$myRights['NO_GROUP_MODERATION'] = $rightManager->getGroupIdsWherePermission('MESSAGING_NO_GROUP_MODERATION');
				
				$pupilsGroupTypeId = GroupTypeQuery::create()->findOneByType("PUPIL")->getId();
				$parentsGroupTypeId = GroupTypeQuery::create()->findOneByType("PARENT")->getId();
				$teachersGroupTypeId = GroupTypeQuery::create()->findOneByType("TEACHER")->getId();
				
				$validatedUsers = array();
				$concernedGroups = array();
				foreach($users as $user){
					$validated = false;
					$userManager->setUser($user);
					$rights = $userManager->getRights();
					
					foreach($rights as $groupId => $groupRights){
						//Test du ALL
						if(in_array($groupId,$myRights['ALL'])){
							$validated = true;
							$concernedGroups[] = $groupId;
						}elseif(in_array($groupId,$myRights['PUPILS']) && in_array($pupilsGroupTypeId,$groupRights['roles'])){
							$validated = true;
							$concernedGroups[] = $groupId;
						}elseif(in_array($groupId,$myRights['TEACHERS']) && in_array($teachersGroupTypeId,$groupRights['roles'])){
							$validated = true;
							$concernedGroups[] = $groupId;
						}elseif(in_array($groupId,$myRights['PARENTS']) && in_array($parentsGroupTypeId,$groupRights['roles'])){
							$validated = true;
							$concernedGroups[] = $groupId;
						}
						//Gestion des parents : enfants
					}
					if($validated == true){
						$validatedUsers[] = $user;
					}
				}
				
				$status = "IN_MODERATION";
				
				//ConcernedGroups = groupes concernés par le message
				$concernedGroups = array_unique($concernedGroups);
				//Calcul pour savoir si le message part en modération ou pas
				//J'ai forcément le droit
				if(count($myRights['ALL']) > 0){
					$status = "ACCEPTED";
				}elseif(count($concernedGroups) == 1){
					//1 groupe et pas e modération dans ce groupe
					if(in_array($concernedGroups[0],$myRights['NO_GROUP_MODERATION'])){
						$status = "ACCEPTED";
					}
				}elseif(count($concernedGroups) > 0){
					if(count($myRights['NO_EXTERNAL_MODERATION']) > 0){
						$status = "ACCEPTED";
					}
				}				
				
				$parentId = null;
				if(isset($data['draftId'])){
					$message = MessagingMessageQuery::create()->findPk($data['draftId']);
					$rightManager->forbidIf($message->getUser()->getId() != $rightManager->getUserSession()->getId());
					$message->setSubject($data['subject']);
					$message->setContent($data['content']);
					$arrayStatus = BNSMessageManager::$messagesStatus;
					$message->setStatus($arrayStatus[$status]);
					$message->save();
				}else{
					$message = $this->get('bns.message_manager')->initMessage($data['subject'],$data['content'],$status);
				}
				$this->get('bns.message_manager')->sendMessage($message,$status,$parentId,$validatedUsers,$this->getRequest());
				
				return new Response(1);
			}
		}
		return array(
			'form' => $form->createView(),
			'shownRoles' => $this->getShownRoles()
		);
	}
	
	/**
	 * Enregistrement du message en brouillon
	 * @Route("/light-save-message-draft", name="BNSAppMessagingBundle_front_light_ajax_message_save_draft", options={"expose"=true}))
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function saveMessageDraftAction()
	{
		$form = $this->createForm(new MessageType());
		
		if($this->getRequest()->isMethod('post')){
			
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()){
				$data = $form->getData();
				$toList = array_unique(explode(',',$data['to']));
				$messageManager = $this->get('bns.message_manager');
				$rightManager = $this->get('bns.right_manager');
				if(isset($data['draftId'])){
					$draft = MessagingMessageQuery::create()->findPk($data['draftId']);
					$rightManager->forbidIf($draft->getUser()->getId() != $rightManager->getUserSession()->getId());
					$draft->setSubject($data['subject']);
					$draft->setContent($data['content']);
				}else{
					$draft = $messageManager->createDraft($data['subject'],$data['content'],$this->getRequest());
				}
				//Enregistrement des destinataires temporaires
				$draft->setTosTempList(serialize($toList));
				$draft->save();
				//Enregistrement des pièces jointes
				$this->get('bns.resource_manager')->saveAttachments($draft,$this->getRequest());
				
				return $this->forward('BNSAppMessagingBundle:FrontLightAjax:editMessageDraft',array('draftId' => $draft->getId()));
			}
		}
		return new Response(false);
	}
	
	/**
	 * Enregistrement du message en brouillon
	 * @Route("/light-edit-message-draft/{draftId}", name="BNSAppMessagingBundle_front_light_ajax_message_edit_draft", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:new_message.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function editMessageDraftAction($draftId)
	{
		$draft = MessagingMessageQuery::create()->findPk($draftId);
		$rightManager = $this->get('bns.right_manager');
		$form = $this->createForm(
			new MessageType(),
			array(
				'draftId' => $draft->getId(),
				'subject' => $draft->getSubject(),
				'content' => $draft->getContent(),
				'to'	  => implode(',',unserialize($draft->getTosTempList()))
			)
		);
		$rightManager->forbidIf($draft->getUser()->getId() != $rightManager->getUserSession()->getId());
		return array(
			'form' => $form->createView(),
			'shownRoles' => $this->getShownRoles(),
			'attachements' => $draft->getResourceAttachments()
		);
	}
	
	
	/**
	 * Envoi d'une réponse
	 * @Route("/light-answer-message", name="BNSAppMessagingBundle_front_light_ajax_answer_message", options={"expose"=true}))
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function answerMessageAction()
	{
		$form = $this->createForm(new AnswerType());
		
		if($this->getRequest()->isMethod('post')){
			$form->bindRequest($this->getRequest());
			if ($form->isValid()){
				$data = $form->getData();
				//Vérification des droits
				$conversation = MessagingConversationQuery::create()->findPk($data['conversation_id']);
				$rightManager = $this->get('bns.right_manager');
				$rightManager->forbidIf($rightManager->getUserSession()->getId() != $conversation->getUserId());
				//TODO récupération du statut du message
				$status = "ACCEPTED";
				//Ok on peut ajouter la réponse
				$this->get('bns.message_manager')->answerMessage($conversation,$data['answer'],$status,$this->getRequest());
				return new Response(1);
			}
		}
	}
	
	/**
	 * Recherche de message
	 * @Route("/search-message/{word}", name="BNSAppMessagingBundle_front_light_ajax_search_message", options={"expose"=true}, defaults={"word" = "emptySearch"})
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:search.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function searchMessageAction($word = "emptySearch")
	{
		if($word == "emptySearch"){
			$results = null;
			$page = 0;
		}else{
			$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 0;
			$messageManager = $this->get('bns.message_manager');
			$results = $messageManager->search($word,$page);
		}
		return array(
			'results' => $results,
			'paginate_limit' => BNSMessageManager::$paginateLimit,
			'page' => $page
		);
	}
	
	/**
	 * Affiche les destinataires du message
	 * @Route("/tos", name="BNSAppMessagingBundle_front_light_ajax_show_tos", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Front/Light/Ajax:show_tos.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function showTosAction()
	{
		$tos = $this->getRequest('tos');
		$check = $this->getRequest('check');
		if($check )
		$tos = explode(',',$tos);
		$tos = UserQuery::create()->findById($tos);
		
		return array('tos' => $tos);
	}
}


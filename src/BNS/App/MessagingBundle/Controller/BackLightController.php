<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use BNS\App\MessagingBundle\Controller\BackController;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

/**
 * @Route("/gestion")
 */

class BackLightController extends BackController
{
	/**
	 * Renvoie un tableau d'Ids des utilisateurs autorisées (sur lesquels je peux agir en modération)
	 * @return array $moderationUsers
	 */
	protected function getAuthorisedUsersIds()
	{
		$groupManager = $this->get('bns.group_manager');
		$moderationGroups = $this->get('bns.right_manager')->getGroupsWherePermission("MESSAGING_ACCESS_BACK");
		$moderationUsers = array();
		foreach($moderationGroups as $group){
			$groupManager->setGroup($group);
			$users = $groupManager->getUsersIds();
			$moderationUsers = array_unique(array_merge($moderationUsers,$users));
		}
		return $moderationUsers;
	}
	
	protected function getMessagesQuery($type)
	{	
		$status = BNSMessageManager::$messagesStatus;
		return MessagingMessageQuery::create()
			->filterByAuthorId($this->getAuthorisedUsersIds())
			->filterByStatus($status[$type])
			->orderByCreatedAt(\Criteria::DESC);
	}
	
	/**
	 * Page d'accueil : par défaut messages à modérer
	 * @Route("/moderation", name="BNSAppMessagingBundle_back_light_moderation")
	 * @Template("BNSAppMessagingBundle:Back/Light:index.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function lightIndexAction($type)
	{
		return array();
	}
	
	/**
	 * Accès aux messages à modérer selon leurs types
	 * @Route("/messages/{type}", name="BNSAppMessagingBundle_back_light_messages", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Back/Light:messages.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function messagesAction($type)
	{
		$page = $this->getRequest()->get('page') ? $this->getRequest()->get('page') : 1;
		$messagesQuery = $this->getMessagesQuery($type);
		$messages = $messagesQuery->paginate($page,BNSMessageManager::$paginateLimit);
		$nbMessages = $messagesQuery->count();
		return array(
			'messages' => $messages,
			'nbMessages' => $nbMessages,
			'page' => $page,
			'limit' => BNSMessageManager::$paginateLimit,
			'type' => $type
		);
	}
	
	/**
	 * Valide d'un coup tous les messages en attente de modération
	 * @Route("/messages-validation", name="BNSAppMessagingBundle_back_light_messages_validation")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function messagesValidationAction()
	{
		$messages = $this->getMessagesQuery('IN_MODERATION')->find();
		$messageManager = $this->get('bns.message_manager');
		foreach($messages as $message){
			$messageManager->accept($message);
		}
		return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));
	}	
	
	/**
	 * Supprime d'un coup tous les messages rejetés
	 * @Route("/messages-supprimer", name="BNSAppMessagingBundle_back_light_messages_delete")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function messagesDeleteAction()
	{
		$messages = $this->getMessagesQuery('REJECTED')->find();
		$messageManager = $this->get('bns.message_manager');
		foreach($messages as $message){
			$messageManager->delete($message);
		}
		return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));
	}
	
	
	
	
	/**
	 * Règles de modération (CLASSROOM ou EXTERNAL)
	 * @Route("/rule/{type}", name="BNSAppMessagingBundle_back_light_rule", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Back/Light:rule.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function ruleAction()
	{
		$pupilRole = GroupTypeQuery::create()->findOneByType('PUPIL');
		$rightManager = $this->get('bns.right_manager');
		$manageableGroups = $rightManager->getGroupsWherePermission('MESSAGING_ACCESS_BACK');
		$groupManager = $this->get('bns.group_manager');
		$status = array();
		foreach($manageableGroups as $group){
			$groupManager->setGroup($group);
			$status[$group->getId()]['EXTERNAL'] = in_array('MESSAGING_NO_EXTERNAL_MODERATION',$groupManager->getPermissionsForRoleInCurrentGroup($pupilRole));
			$status[$group->getId()]['GROUP'] = in_array('MESSAGING_NO_GROUP_MODERATION',$groupManager->getPermissionsForRoleInCurrentGroup($pupilRole));
		}
		return array('groups'=> $manageableGroups,'status' => $status);
	}
	
	/**
	 * Changement des règles de modération (CLASSROOM ou EXTERNAL)
	 * @Route("/rule-toggle/{groupId}/{type}/{value}", name="BNSAppMessagingBundle_back_light_rule_toggle", options={"expose"=true}))
	 * @Template("BNSAppMessagingBundle:Back/Light:block_moderation_action_button.html.twig")
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function ruleToggleAction($groupId,$type,$value)
	{
		if($value == "true")
			$value = true;
		elseif($value == "false")
			$value = false;
		if($type == "EXTERNAL"){
			$permission = "MESSAGING_NO_EXTERNAL_MODERATION";
			$rank = "MESSAGING_SEND_EXTERNAL";
		}elseif($type == "GROUP"){
			$permission = "MESSAGING_NO_GROUP_MODERATION";
			$rank = "MESSAGING_SEND_INTERNAL";
		}
		if(isset($permission)){
			$rightManager = $this->get('bns.right_manager');
			$groupManager = $this->get('bns.group_manager');
			if($rightManager->hasRight($permission,$groupId)){
				$pupilRole = GroupTypeQuery::create()->findOneByType('PUPIL');
				$groupManager->findGroupById($groupId);
				$groupManager->activationRankRequest($rank, $pupilRole, $value);
				return array(
					'type' => $type,
					'group' => $groupManager->getGroup(),
					'status' => array($type => $value)
				);
			}
		}	
	}
	
	/**
	 * Changement de statut d'un message (IN_MODERATION, ACCEPTED ou REJECTED)
	 * @Route("/message-toggle/{messageId}/{type}/{page}/{currentType}", name="BNSAppMessagingBundle_back_light_message_toggle", options={"expose"=true}))
	 * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
	 */
	public function messageToggleAction($messageId,$type,$page,$currentType)
	{
		$message = MessagingMessageQuery::create()->findPk($messageId);
		if($message){
			if(in_array($message->getAuthorId(),$this->getAuthorisedUsersIds())){
				$messageManager = $this->get('bns.message_manager');
				switch($type){
					case 'IN_MODERATION':
						$messageManager->moderate($message);
					break;
					case 'ACCEPTED':
						$messageManager->accept($message);
					break;
					case 'REJECTED':
						$messageManager->reject($message);
					break;
					case 'DELETE_FOREVER':
						$messageManager->delete($message);
					break;
				}
				return $this->forward('BNSAppMessagingBundle:BackLight:messages',array('type' => $currentType,'page' => $page));
			}
		}	
	}
}


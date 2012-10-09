<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupTypeI18nPeer;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Utils\Crypt;

class InvitationController extends Controller
{
	/**
	 * @Route("/", name="user_invitations")
	 */
	public function indexAction()
	{
		$this->get('bns.right_manager')->initContext();
		
		$redirect = $this->get('bns.user_manager')->onLogon();
		$response = $this->get('bns.user_manager')->setUser($this->getUser())->getInvitations();
		$invitations = array();
		
		if (count($response) <= 0) {
			return $this->redirect($redirect);
		}
		
		foreach($response as $r) {
			$invitation = $r;
			$invitation['author_object'] = UserQuery::create()
				->add(UserPeer::ID, $r['author_id'])
			->findOne();
			$invitation['group_object'] = GroupQuery::create()
				->joinWith('GroupType')
				->joinWith('GroupType.GroupTypeI18n')
				->add(GroupTypeI18nPeer::LANG, BNSAccess::getLocale())
				->add(GroupPeer::ID, $r['group_id'])
			->findOne();
			$invitation['role_object'] = GroupTypeQuery::create()
				->joinWithI18n(BNSAccess::getLocale())
				->add(GroupTypePeer::ID, $r['group_type_role_id'])
			->findOne();
			
			$invitation['invitation_id'] = Crypt::encrypt($r['invitation_id']);
			
			// Finally
			$invitations[] = $invitation;
			
			// Clear memory
			$invitation = null;
		}
		
			
		return $this->render('BNSAppMainBundle:Invitation:invitation_index.html.twig', array(
			'invitations'	=> $invitations,
			'redirect'		=> $redirect
		));
	}
	
	/**
	 * @Route("/accepter", name="invitation_accept", options={"expose"=true})
	 */
	public function acceptInvitationAction()
	{
		$cryptedInvitationId = $this->getRequest()->get('invitation_id', null);
		if (null === $cryptedInvitationId) {
			throw new HttpException(500, 'invitation_id parameter is missing!');
		}
		
		$this->get('bns.user_manager')->setUser($this->getUser())->acceptInvitation(Crypt::decrypt($cryptedInvitationId));
		$this->get('bns.right_manager')->reloadRights();
		$this->get('bns.right_manager')->initContext();
		
		return new Response(json_encode(true));
	}
	
	/**
	 * @Route("/refuser", name="invitation_decline", options={"expose"=true})
	 */
	public function declineInvitationAction()
	{
		$cryptedInvitationId = $this->getRequest()->get('invitation_id', null);
		if (null === $cryptedInvitationId) {
			throw new HttpException(500, 'invitation_id parameter is missing!');
		}
		
		$this->get('bns.user_manager')->setUser($this->getUser())->declineInvitation(Crypt::decrypt($cryptedInvitationId));
		
		return new Response(json_encode(true));
	}
	
	/**
	 * @Route("/toujours-refuser", name="invitation_never_accept", options={"expose"=true})
	 */
	public function neverAcceptInvitationAction()
	{
		$cryptedInvitationId = $this->getRequest()->get('invitation_id', null);
		if (null === $cryptedInvitationId) {
			throw new HttpException(500, 'invitation_id parameter is missing!');
		}
		
		$this->get('bns.user_manager')->setUser($this->getUser())->neverAcceptInvitation(Crypt::decrypt($cryptedInvitationId));
		
		return new Response(json_encode(true));
	}
	
	
}
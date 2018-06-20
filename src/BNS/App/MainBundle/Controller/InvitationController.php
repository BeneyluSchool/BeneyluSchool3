<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Utils\Crypt;

class InvitationController extends Controller
{
	/**
	 * @Route("/", name="user_invitations")
	 */
	public function indexAction()
	{
		$this->get('bns.right_manager')->initContext();

		$response = $this->get('bns.user_manager')->setUser($this->getUser())->getInvitations();
		$invitations = array();

        if (!($redirect = $this->get('bns.user_manager')->onLogon())) {
                    $redirect = $this->generateUrl('home');
        }

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
				->add(GroupPeer::ID, $r['group_id'])
			->findOne();
			$invitation['role_object'] = GroupTypeQuery::create()
				->add(GroupTypePeer::ID, $r['group_type_role_id'])
			->findOne();

			$invitation['invitation_id'] = Crypt::encrypt($r['invitation_id']);

			if ('SCHOOL' === $invitation['group_object']->getType()) {
				$invitation['groups_embedded'] = $this->get('bns.right_manager')->getClassroomsUserBelong($this->getUser());
			} else {
				$invitation['groups_embedded'] = array();
			}

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
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function acceptInvitationAction(Request $request)
	{
		$cryptedInvitationId = $request->get('invitation_id', null);
		if (null === $cryptedInvitationId) {
			throw new HttpException(500, 'invitation_id parameter is missing!');
		}

		$invitationId = Crypt::decrypt($cryptedInvitationId);
		$user = $this->getUser();
		$invitations = $this->get('bns.user_manager')->setUser($user)->getInvitations();
		$invitation = null;
		foreach ($invitations as $i) {
			if ($invitationId == $i['invitation_id']) {
				$invitation = $i;
				break;
			}
		}

		if (!$invitation) {
			return new Response(json_encode(false));
		}

		$targetGroup = GroupQuery::create()->filterByArchived(false)->findPk($invitation['group_id']);
		if (!$targetGroup) {
			return new Response(json_encode(false));
		}

		// if invited in a school, migrate classrooms too
		if ('SCHOOL' === $targetGroup->getType()) {
			$embeddedGroupIds = $request->get('groups_embedded', array());
			$groupManager = $this->get('bns.group_manager');
			$classrooms = $this->get('bns.user_manager')->getClassroomUserBelong();
			foreach ($classrooms as $classroom) {
				if (!in_array($classroom->getId(), $embeddedGroupIds)) {
					continue;
				}
				$parents = $groupManager->setGroup($classroom)->getParentsId();
				foreach ($parents as $parent) {
					$groupManager->deleteParent($classroom->getId(), $parent['id']);
				}
				$groupManager->addParent($classroom->getId(), $targetGroup->getId());
			}
		}

		$this->get('bns.user_manager')->setUser($this->getUser())->acceptInvitation($invitationId);
		$this->get('bns.right_manager')->reloadRights();
		$this->get('bns.right_manager')->initContext();
        $this->get('bns.group_manager')->setGroup($targetGroup)->clearGroupCache();

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

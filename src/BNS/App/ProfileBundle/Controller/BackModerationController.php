<?php

namespace BNS\App\ProfileBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackModerationController extends Controller
{
	/**
	 * @Route("/charger", name="profile_manager_moderation_statuses_load")
	 */
	public function showAction($page = 1, $status = 'PENDING_VALIDATION')
	{
		if ($this->getRequest()->isMethod('POST')) {
			$page	= $this->getRequest()->get('page');
			$status	= $this->getRequest()->get('status');
		}
		
		// Check if status exsits
		$statuses = ProfileFeedPeer::getValueSet(ProfileFeedPeer::STATUS);
		if (!in_array($status, $statuses)) {
			throw new \InvalidArgumentException('The status : ' . $status . ' does NOT exist !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$groupManager = $this->get('bns.group_manager')->setGroupById($context['id']);
		$users = $groupManager->getUsers(true);
		$profileIds = array();
		
		foreach ($users as $user) {
			$profileIds[] = $user->getProfileId();
		}
		
		// Fetch statuses
		$pager = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->joinWith('ProfileFeedStatus')
			->where('Profile.Id IN ?', $profileIds)
			->where('f.Status = ?', $status)
			->orderBy('f.Date', \Criteria::DESC)
		->paginate($page);
		
		$feeds = $pager->getResults();
		foreach ($feeds as $feed) {
			foreach ($users as $user) {
				if ($user->getProfileId() == $feed->getProfile()->getId()) {
					$feed->getProfile()->replaceUser($user);
					break;
				}
			}
		}
		
		return $this->render('BNSAppProfileBundle:Moderation:back_statuses_list.html.twig', array(
			'feeds'		=> $feeds,
			'pager'		=> $pager,
			'status'	=> $status
		));
	}
	
	/**
	 * @Route("/status", name="profile_manager_moderation_statuses_update")
	 */
	public function updateStatusAction()
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$id			= $this->getRequest()->get('id', null);
		$status		= $this->getRequest()->get('status', null);
		$page		= $this->getRequest()->get('page', null);
		
		// Check parameters
		if (null == $id || null == $status || null == $page) {
			throw new \InvalidArgumentException('There is some missing mandatory inputs !');
		}
		
		$feed = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->joinWith('ProfileFeedStatus')
			->where('f.Id = ?', $id)
		->findOne();
		
		if (null == $feed) {
			throw new NotFoundHttpException('The profile feed with id ' . $id . ' is NOT found !');
		}
		
		// Check if status exsits
		$statuses = ProfileFeedPeer::getValueSet(ProfileFeedPeer::STATUS);
		$statuses[] = 'DELETED'; // for delete process
		if (!in_array($status, $statuses)) {
			throw new \InvalidArgumentException('The status : ' . $status . ' does NOT exist !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$groupManager = $this->get('bns.group_manager')->setGroupById($context['id']);
		$users = $groupManager->getUsers(true);
		$profileIds = array();
		
		foreach ($users as $user) {
			$profileIds[] = $user->getProfileId();
		}
		
		// Check if user has the right to manage this feed
		$found = false;
		foreach ($users as $user) {
			if ($user->getProfileId() == $feed->getProfile()->getId()) {
				$found = true;
				break;
			}
		}
		
		if (!$found) {
			throw new AccessDeniedHttpException('The status : ' . $status . ' does NOT exist !');
		}
		
		$lastStatus = $feed->getStatus();
		
		// Delete process
		if ($status == 'DELETED') {
			$feed->delete();
		}
		else {
			$feed->setStatus($status);
			$feed->save();
		}
		
		// Show one feed
		$feed = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->joinWith('ProfileFeedStatus')
			->where('Profile.Id IN ?', $profileIds)
			->where('f.Status = ?', $lastStatus)
			->orderBy('f.Date', \Criteria::DESC)
			->offset(10)
		->findOne();
		
		$feedHtml = null;
		if (null != $feed) {
			foreach ($users as $user) {
				if ($user->getProfileId() == $feed->getProfile()->getId()) {
					$feed->getProfile()->replaceUser($user);
					break;
				}
			}

			$feedHtml = $this->renderView('BNSAppProfileBundle:Moderation:back_statuses_row.html.twig', array(
				'feed'	=> $feed
			));
		}
		
		// Generate the pager
		$pager = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->where('Profile.Id IN ?', $profileIds)
			->where('f.Status = ?', $lastStatus)
		->paginate($page);
		
		if (0 == $pager->count()) {
			$feedHtml = $this->renderView('BNSAppProfileBundle:Moderation:back_statuses_empty.html.twig');
		}
		
		$pagerHtml = $this->renderView('BNSAppProfileBundle:Moderation:back_statuses_pager.html.twig', array(
			'pager'	 => $pager,
			'status' => $lastStatus
		));
		
		return new Response(json_encode(array(
			'feed'	=> $feedHtml,
			'pager'	=> $pagerHtml
		)));
	}
	
	/**
	 * @Route("/tout-valider", name="profile_manager_moderation_statuses_validate_all")
	 */
	public function validateAllAction()
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$groupManager = $this->get('bns.group_manager')->setGroupById($context['id']);
		$users = $groupManager->getUsers(true);
		$profileIds = array();
		
		foreach ($users as $user) {
			$profileIds[] = $user->getProfileId();
		}
		
		$feeds = ProfileFeedQuery::create('f')
			->joinWith('Profile')
			->where('Profile.Id IN ?', $profileIds)
			->where('f.Status = ?', 'PENDING_VALIDATION')
			->orderBy('f.Date', \Criteria::DESC)
		->find();
		
		foreach ($feeds as $feed) {
			$feed->setStatus(ProfileFeedPeer::STATUS_VALIDATED);
		}
		
		$feeds->save();
		
		return new Response();
	}
}
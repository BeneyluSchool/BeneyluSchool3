<?php

namespace BNS\App\ProfileBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\ProfileQuery;
use BNS\App\CoreBundle\Model\ProfileCommentQuery;
use BNS\App\CoreBundle\Model\ProfileCommentPeer;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\CoreBundle\Model\ProfileFeed;

/**
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 */
class FrontController extends Controller
{
	/**
	 * @Route("/profil", name="BNSAppProfileBundle_front")
	 * @RightsSomeWhere("PROFILE_ACCESS")
	 */
    public function indexAction($userSlug = null)
    {
		$user = null;
		if (null == $userSlug) {
			$user = $this->getUser();
		}
		else {
			$user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
		}
		
		// Affiche le profil de l'utilisateur connecté 
		$user->setProfile($this->getProfile($user));

		return $this->render('BNSAppProfileBundle:Front:front_profile_index.html.twig', array(
			'classrooms'		=> $this->get('bns.right_manager')->getClassroomsUserBelong($user),
            'user'				=> $user,
			'nb_feeds_per_load'	=> ProfileFeed::PROFILE_FEED_LIMIT,
        ));
    }
	
	/**
	 * @Route("/profil/voir/{userSlug}", name="BNSAppProfileBundle_view_profile")
	 */
	public function showForeignUserProfile($userSlug)
	{
		return $this->indexAction($userSlug);
	}
	
	/**
	 * @param type $owner
	 * @return 
	 * 
	 * @throws NotFoundHttpException 
	 */
	private function getProfile($owner)
	{
		$profiles = ProfileQuery::create()
			->joinWith('User')
			->joinWith('ProfilePreference', \Criteria::LEFT_JOIN)
			->add(UserPeer::ID, $owner->getId())
		->find();
        		
		// L'utilisateur existe ?
		if (!isset($profiles[0])) 
        {
			throw new NotFoundHttpException('Profile with id : ' . $owner->getId() . ' is not found !');
		}
        
		$profile = $profiles[0];
		$feeds = ProfileFeedQuery::create()
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedResource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(ProfileFeedPeer::DATE)
			->add(ProfileFeedPeer::PROFILE_ID, $profile->getId())
			->limit(ProfileFeed::PROFILE_FEED_LIMIT)
		->find();
		$feeds->populateRelation('ProfileComment', ProfileCommentQuery::create()
			->addDescendingOrderByColumn(ProfileCommentPeer::DATE)
			->limit(10)
		);
		$profile->replaceProfileFeeds($feeds);
		
		return $profile;
	}
	
	/**
	 * @Route("/profil/charger-statut", name="status_load_more", options={"expose"=true})
	 */
	public function loadMoreStatusAction()
	{
		$request = $this->getRequest();
		if (!$request->isXmlHttpRequest())
		{
			throw new \Exception('You can only access to this action by AJAX');
		}
		
		if ('POST' != $request->getMethod() || null == $request->get('profile_id') || null == $request->get('nb_load'))
		{
			throw new \HttpException('500','Request\'s method should be POST; Two parameters (profile_id and nb_load) must be given!');
		}
		
		$feeds = ProfileFeedQuery::create()
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', \Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedResource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(ProfileFeedPeer::DATE)
			->add(ProfileFeedPeer::PROFILE_ID, $request->get('profile_id'))
			->offset(ProfileFeed::PROFILE_FEED_LIMIT * $request->get('nb_load'))
			->limit(ProfileFeed::PROFILE_FEED_LIMIT)
		->find();
		$feeds->populateRelation('ProfileComment', ProfileCommentQuery::create()
			->addDescendingOrderByColumn(ProfileCommentPeer::DATE)
			->limit(5)
		);
		
		return $this->render('BNSAppProfileBundle:Status:front_status_list.html.twig', array(
			'feeds'	=> $feeds,
		));
	}
}
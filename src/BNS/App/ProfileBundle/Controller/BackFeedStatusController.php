<?php

namespace BNS\App\ProfileBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseController;
use \BNS\App\CoreBundle\Model\GroupTypeQuery;
use \BNS\App\CoreBundle\Model\ProfileFeed;
use \BNS\App\CoreBundle\Model\ProfileFeedPeer;
use \BNS\App\CoreBundle\Model\ProfileFeedQuery;
use \BNS\App\CoreBundle\Model\ProfileFeedStatusQuery;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileNewProfileStatusToModerateNotification;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileStatusCreatedNotification;
use \BNS\App\ProfileBundle\Form\Model\ProfileFeedFormModel;
use \BNS\App\ProfileBundle\Form\Type\ProfileFeedType;
use \Criteria;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class BackFeedStatusController extends BaseController
{
	/**
	 * @param \BNS\App\CoreBundle\Model\ProfileFeed $feed
	 *
	 * @return boolean
	 */
	protected function canViewFeed(ProfileFeed $feed)
	{
		$user = $feed->getProfile()->getUser();
		if ($this->getUser()->getId() == $user->getId()) {
			return true;
		}
		else {
			$groupManager	  = $this->get('bns.group_manager');
			$authorisedGroups = $this->get('bns.right_manager')->getGroupsWherePermission('PROFILE_FULL_ACCESS_BACK');

			foreach ($authorisedGroups as $group) {
				$groupManager->setGroup($group);
				$userIds = $groupManager->getUsersIds();

				if (in_array($user->getId(), $userIds)) {
					return true;
				}
			}
		}

		return false;
	}


    /**
     * @Route("/", name="BNSAppProfileBundle_back_status")
	 * @RightsSomeWhere("PROFILE_FULL_ACCESS_BACK")
     *
     * @return type
     */
    public function indexAction()
    {
        if (!$this->hasFeature('profile_status_link')) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();

        // On récupère les 5 dernières publications
        $feeds = ProfileFeedQuery::create()
            ->joinWith('ProfileFeedStatus', Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', Criteria::LEFT_JOIN)
            ->joinWith('ProfileFeedResource', Criteria::LEFT_JOIN)
            ->add(ProfileFeedPeer::PROFILE_ID, $user->getProfileId())
			->add(ProfileFeedPeer::STATUS, 2, \Criteria::NOT_EQUAL)
            ->orderByDate(Criteria::DESC)
        ->find();

		//$feeds = ProfileCommentQuery::populateRelation($feeds);
		$user->getProfile()->replaceProfileFeeds($feeds);

        return $this->render('BNSAppProfileBundle:BackFeedStatus:back_status_index.html.twig', array(
            'user'  => $user,
            'feeds' => $feeds,
        ));
    }

	/**
	 * @Route("/{feedId}/supprimer", name="profile_manager_feed_delete")
	 * @RightsSomeWhere("PROFILE_FULL_ACCESS_BACK")
	 */
	public function deleteFeedAction($feedId)
	{
        if (!$this->hasFeature('profile_status')) {
            throw $this->createNotFoundException();
        }

		$feeds = ProfileFeedQuery::create('f')
			->join('Profile')
			->join('Profile.User')
			->where('User.Id = ?', $this->getUser()->getId())
			->where('f.Id = ?', $feedId)
		->find();

		if (!isset($feeds[0]) || !$this->canViewFeed($feeds[0])) {
			throw new NotFoundHttpException('The feed with id : ' . $feedId . ' is NOT found or not Authorised');
		}

		// Process
		$feeds[0]->delete();
		$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STATUS_DELETED', array(), 'PROFILE'));

		return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
	}

	/**
	 * @param ProfileFeed $feed
	 */
	public function renderDeleteFeedModalAction($feed)
	{
		return $this->render('BNSAppProfileBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'feed' => $feed,
			),
			'footerValues'	=> array(
				'feed'	=> $feed,
				'route'	=> $this->generateUrl('profile_manager_feed_delete', array('feedId' => $feed->getId()))
			)
		));
	}

	/**
	 * @Route("/{feedId}/visualiser", name="profile_manager_feed_visualisation")
	 * @RightsSomeWhere("PROFILE_FULL_ACCESS_BACK")
	 */
	public function visualisationAction($feedId)
	{
        if (!$this->hasFeature('profile_status')) {
            throw $this->createNotFoundException();
        }

		$feeds = ProfileFeedQuery::create('f')
			->joinWith('ProfileFeedStatus', \Criteria::LEFT_JOIN)
			->join('Profile')
			->join('Profile.User')
			->where('f.Id = ?', $feedId)
			->where('f.Status <> ?', 'REFUSED')
		->find();

		if (!isset($feeds[0]) || !$this->canViewFeed($feeds[0])) {
			return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
		}

		return $this->render('BNSAppProfileBundle:Status:back_status_visualisation.html.twig', array(
			'feed'	=> $feeds[0],
			'user'	=> $this->getUser(),
		));
	}

	/**
	 * @Route("/nouveau", name="profile_manager_feed_new")
	 * @RightsSomeWhere("PROFILE_FULL_ACCESS_BACK")
	 */
	public function newAction()
	{
        if (!$this->hasFeature('profile_status')) {
            throw $this->createNotFoundException();
        }

		$form = $this->createForm(new ProfileFeedType(), new ProfileFeedFormModel());
		if ($this->getRequest()->isMethod('POST')) {
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				$rightManager = $this->get('bns.right_manager');

				// Finally
				$form->getData()->save(!$rightManager->hasRight('PROFILE_NO_MODERATE_STATUS'), $rightManager->hasRight('PROFILE_ADMINISTRATION'));

				if ($form->getData()->getFeed()->isPendingValidation()) {
					$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STATUS_AWAITING_MODERATION', array(), 'PROFILE'));
				}
				else {
					$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STATUS_ADDED', array(), 'PROFILE'));
				}

                if ($this->get('bns.user_manager')->isChild()) {
                    $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
                    $managers = $this->get('bns.group_manager')->getUserWithPermission('PROFILE_ADMINISTRATION', $currentGroup);
                    if ($form->getData()->getFeed()->isPendingValidation()) {
                        $this->get('notification_manager')->send($managers, new ProfileNewProfileStatusToModerateNotification(
                            $this->get('service_container'),
                            $form->getData()->getFeed()->getId(),
                            $currentGroup->getId()
                        ));
                    } else {
                        $this->get('notification_manager')->send($managers, new ProfileStatusCreatedNotification(
                            $this->get('service_container'),
                            $form->getData()->getFeed()->getId(),
                            $currentGroup->getId()
                        ));
                    }
                }

                //statistic action
                $this->get("stat.profile")->newStatus();

                if($this->getRequest()->get('redirect') == 'classroom')
                {
                    return $this->redirect($this->generateUrl('BNSAppClassroomBundle_front'));
                }

				return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
			}
		}

		return $this->render('BNSAppProfileBundle:Status:back_status_form.html.twig', array(
			'user'	=> $this->getUser(),
			'form'	=> $form->createView()
		));
	}

	/**
	 * @Route("/editer/{id}", name="profile_manager_feed_edit")
	 * @Rights("PROFILE_ADMINISTRATION")
	 */
	public function editAction($id)
	{
        if (!$this->hasFeature('profile_status')) {
            throw $this->createNotFoundException();
        }

		$feed = ProfileFeedStatusQuery::create('pfs')
			->joinWith('ProfileFeed')
			->joinWith('ProfileFeed.Profile')
			->joinWith('Profile.User')
			->where('pfs.FeedId = ?', $id)
		->findOne();

		// Not found
		if (null == $feed || !$this->canViewFeed($feed->getProfileFeed())) {
			return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
		}

		$form = $this->createForm(new ProfileFeedType(), new ProfileFeedFormModel($feed));
		if ($this->getRequest()->isMethod('POST')) {
			$rightManager = $this->get('bns.right_manager');
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				$form->getData()->save($rightManager->hasRight('PROFILE_NO_MODERATE_STATUS'), $rightManager->hasRight('PROFILE_ADMINISTRATION'));

				$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STATUS_UPDATED', array(), 'PROFILE'));

				return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
			}
		}

		return $this->render('BNSAppProfileBundle:Status:back_status_form.html.twig', array(
			'user'			=> $this->getUser(),
			'form'			=> $form->createView(),
			'feed'			=> $feed,
			'isEditionMode'	=> true
		));
	}
}

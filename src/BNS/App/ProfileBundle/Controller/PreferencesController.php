<?php

namespace BNS\App\ProfileBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ProfilePreference;
use BNS\App\CoreBundle\Model\ProfilePreferenceQuery;
use BNS\App\CoreBundle\Model\ProfilePreferencePeer;

class PreferencesController extends Controller
{
	/**
	 * @param unknown_type $editable
	 */
    public function indexAction(User $user, $editable, $isMyPreferences = true, $fullwidth = false)
    {
        return $this->render('BNSAppProfileBundle:Preferences:preference_block.html.twig', array(
            'user'      		=> $user,
            'editable'			=> $editable,
            'is_my_preferences' => $isMyPreferences,
			'fullwidth' 		=> $fullwidth
        ));
    }

	/**
	 * @Route("/supprimer/{preferenceId}", name="BNSAppProfileBundle_back_preferences_delete")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 */
	public function deleteAction($preferenceId)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException();
		}

		// Suppression de la préférence
		ProfilePreferenceQuery::create()
			->add(ProfilePreferencePeer::ID, $preferenceId)
		->delete();

		return new Response('true');
	}

	/**
	 * @Route("/ajouter/{userSlug}", name="BNSAppProfileBundle_back_preferences_add")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 */
	public function addAction($userSlug)
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest() ||
			null == $this->getRequest()->get('preference_item') ||
			null == $this->getRequest()->get('preference_islike'))
		{
			throw new NotFoundHttpException();
		}

		$user = $this->get('bns.user_manager')->findUserBySlug($userSlug);

		$preference = new ProfilePreference();
		$preference->setIsLike($this->getRequest()->get('preference_islike'));
		$preference->setItem($this->getRequest()->get('preference_item'));
		$preference->setProfileId($user->getProfileId());
		$preference->save();

		return $this->render('BNSAppProfileBundle:Preferences:row_preference_item.html.twig', array(
            'preference' => $preference,
            'editable'   => true,
        ));
    }
}

<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use FOS\RestBundle\Util\Codes;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContextController extends Controller
{

    /**
     * Permet de changer de contexte
     *
     * @Route("/switch-context/{id}", name="BNSAppMainBundle_switch_context_id")
     * @Route("/changer-de-contexte/{slug}", name="BNSAppMainBundle_switch_context")
     * @param Group $group
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function switchContextAction(Group $group, Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $rightManager->switchContext($group);

        $this->get('bns.analytics.manager')->identifyUser($this->getUser(), $group, $this->get('session'));

        // If AJAX, do NOT redirect the user
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse();
        }

        return $this->redirect($this->generateUrl($rightManager->getRedirectRouteOfCurrentGroup()));
    }

	/**
	 * @Route("/pas-de-classe", name="context_no_group")
	 */
	public function noGroupAction()
	{
		return $this->render('BNSAppMainBundle:Context:no_group.html.twig');
	}

    /**
     * @Route("/restricted-access", name="restricted_access")
     */
    public function hasRestrictedAccessAction()
    {
        return $this->render('BNSAppMainBundle:Context:restricted_access.html.twig');
    }


    /**
     * @Route("/my-avatar", name="bns_my_avatar", options={"expose":true})
     */
    public function myAvatarAction()
    {
        $user = $this->getUser();
        if ($user) {
            $avatar = $this->get('twig.extension.resource')->getAvatar($user);
            if (!empty($avatar)) {
                return $this->redirect($avatar);
            }
        }

        throw $this->createNotFoundException();
    }

    /**
     * Action for login detection by Spot load the image 1px or 404
     *
     * @Route("/is-login", name="bns_is_login", options={"expose":true})
     */
    public function isLoginAction()
    {
        $user = $this->getUser();
        if ($user && $user instanceof User) {
            $response = new Response();
            $response->headers->set('Content-Type', 'image/gif');
            $response->setContent(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='));
            return $response;
        }

        return new Response('false', Codes::HTTP_NOT_FOUND);
    }
}

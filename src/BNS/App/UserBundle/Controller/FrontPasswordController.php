<?php

namespace BNS\App\UserBundle\Controller;

use FOS\UserBundle\Propel\GroupQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\FormError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\UserBundle\Form\Type\PasswordGenerationType;
use BNS\App\UserBundle\Form\Type\PasswordResetType;

/**
 * @Route("/mot-de-passe")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontPasswordController extends Controller
{
    /**
     * @Route("/", name="user_password")
     */
    public function indexAction()
    {
		$userManager = $this->get('bns.user_manager');
		$userManager->setUser($this->getUser());
		$this->getRequest()->getSession()->remove('regeneration_process');
		$form = $this->createForm(new PasswordGenerationType());
		if ($this->getRequest()->isMethod('POST')) {
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				$form->getData()->save($this->get('bns.user_manager'));

				return $this->redirect($this->generateUrl('user_password_confirmation'));
			}
		}

        return $this->render('BNSAppUserBundle:Password:index.html.twig', array(
			'form'	=> $form->createView()
		));
    }

	/**
	 * @Route("/confirmation", name="user_password_confirmation")
	 */
	public function confirmAction()
	{
		$redirect = $this->get('bns.user_manager')->onLogon();
		if (false === $redirect) {
            // TODO translate
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_SUCCESS_PASSWORD_MODIFIED', array(), 'USER'));

			$redirect = $this->generateUrl('home');
		}

        $this->get('stat.main')->regeneratePassword();

		// Force redirection
		return $this->redirect($redirect);
	}

    /**
     * @Route("/reinitialisation", name="user_password_reset")
     * @Anon
     */
    public function resetAction(Request $request)
    {
        $userManager = $this->get('bns.user_manager');
        $form = $this->createForm(new PasswordResetType());

        $form->handleRequest($request);
        if ($form->isValid()) {
            // From central
            $user = $userManager->getUserByEmail($form->getData()->email, false);

            if ($user) {
                $userManager->setUser($user);

                if ($user->getPasswordRequestedAt() && (($user->getPasswordRequestedAt()->getTimestamp() + 1800) >= time())) { // 30 min
                    // Only one reset per 30 minutes
                    return $this->redirect($this->generateUrl('user_password_reset_warn'));
                }

                if ($userManager->hasRightSomeWhere('ADMIN_PRETENDED') || $userManager->hasRightSomeWhere('ADMIN_ACCESS')) {
                    // Admin can't reset their password
                    goto ERROR_INVALID_EMAIL;
                }
                $roles = array_keys($userManager->getRolesByGroup());
                if (count(array_intersect($roles, ['TEACHER', 'PARENT', 'PUPIL', 'DIRECTOR', 'ASSISTANT', 'ENT_REFERENT'])) && !$userManager->hasEnabledSchool()) {
                    // User from not enabled school can't reset their password
                    goto ERROR_INVALID_EMAIL;
                }

                $form->getData()->save($userManager, $this->get('bns.mailer'), $this->get('router'));

                return $this->redirect($this->generateUrl('user_password_reset_confirmation'));
            }

            ERROR_INVALID_EMAIL:
            $form->get('email')->addError(new FormError($this->get('translator')->trans('EMAIL_NOT_FOUND', array(), 'USER')));
        }

        return $this->render('BNSAppUserBundle:Password:reset.html.twig', array(
                'form' => $form->createView()
            )
        );
    }

	/**
	 * @Route("/reinitialisation/confirmation", name="user_password_reset_confirmation")
	 * @Anon
	 */
	public function resetConfirmAction()
	{
		return $this->render('BNSAppUserBundle:Password:reset_confirmation.html.twig', array());
	}

	/**
	 * @Route("/reinitialisation/en-cours", name="user_password_reset_warn")
	 * @Anon
	 */
	public function resetWarnAction()
	{
		return $this->render('BNSAppUserBundle:Password:reset_warn.html.twig', array());
	}

	/**
	 * @Route("/reinitialisation/cle/{confirmationToken}", name="user_password_reset_process")
	 * @Anon
	 */
	public function resetProcessAction($confirmationToken)
	{
		$userManager = $this->get('bns.user_manager');
		$user = $userManager->getUserFromConfirmationToken($confirmationToken);

		if (null == $user) {
			throw new NotFoundHttpException('The confirmation token : ' . $confirmationToken . ' is NOT found !');
		}

		$userManager->resetUserPassword($user);
		$this->get('bns.api')->getRedisConnection()->del($this->get('bns.api')->getRoute(array(
			'confirmation_token' => $confirmationToken
		), 'user_confirmation_token'));

		return $this->render('BNSAppUserBundle:Password:reset_confirmation_success.html.twig');
	}

	/**
	 * @Route("/imprimer", name="user_password_print")
	 */
	public function printPasswordAction()
	{
		if ($this->getRequest()->isMethod('POST') && $this->getRequest()->isXmlHttpRequest()) {
			if (false === $this->getRequest()->get('user_password', false)) {
				return $this->redirect($this->generateUrl('home'));
			}

			$this->getRequest()->getSession()->set('user_password', $this->getRequest()->get('user_password'));

			return new Response();
		}

		// Password exists ?
		if (false === $this->getRequest()->getSession()->get('user_password', false)) {
			return $this->redirect($this->generateUrl('home'));
		}

		$password = $this->getRequest()->getSession()->get('user_password');
		$this->getRequest()->getSession()->remove('user_password');

		return $this->render('BNSAppUserBundle:Password:print_password.html.twig', array(
			'user'		=> $this->getUser(),
			'password'	=> $password,
			'base_url' => $this->container->getParameter('application_base_url'),
			'role'		=> $this->get('bns.role_manager')->getGroupTypeRoleFromId($this->getUser()->getHighRoleId())
		));
	}
}

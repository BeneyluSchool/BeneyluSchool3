<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Utils\Crypt;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LogoutController extends Controller
{
	/**
	 * @Route("/disconnect", name="disconnect_user") 
	 */
	public function disconnectAction()
	{
		$secretKey = $this->container->getParameter('security.logout.username.secret_key');
		$parameters = array(
			'referer'	=> $this->getRequest()->getHost(),
			'username'	=> Crypt::encrypt($this->getUser()->getLogin(), $secretKey)
		);
		
		return $this->redirect($this->container->getParameter('auth_url') . $this->container->getParameter('auth_logout_route') . '?' . http_build_query($parameters));
	}
}
<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MainBundle\Model\HomeNewQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class EmailConfirmationController extends Controller
{
    /**
     * Url de vérification d'adresse email
     * @Route("/email-check/{token}", name="main_emailConfirmation_emailCheck")
     * @Template()
     */
    public function emailCheckAction($token, Request $request)
    {
        if ($token == null || $token == '') {
            throw new NotFoundHttpException('no token');
        }
        $user = UserQuery::create()->findOneByEmailConfirmationToken($token);
        if (!$user) {
            $confirmation = false;
            $alreadyConfirmed = false;
        }else{
            if(!$user->getEmailValidated())
            {
                $user->confirmEmail();
                $confirmation = true;
                $alreadyConfirmed = false;
            }else{
                $confirmation = false;
                $alreadyConfirmed = true;
            }

        }

        return array(
            'confirmation' => $confirmation,
            'alreadyConfirmed' => $alreadyConfirmed
        );
    }

    /**
     * Url de renvoi de message de validation email
     * @Route("/email-check-send", name="main_emailConfirmation_emailCheckSend")
     * @Template()
     */
    public function emailCheckSendAction(Request $request)
    {
        $user = $this->get('bns.right_manager')->getUserSession();
        $base['first_name'] = $user->getFirstName();
        $base['confirm_link'] = $this->container->get('router')->generate('main_emailConfirmation_emailCheck',array('token' => $user->getEmailConfirmationToken()),true);
        $this->get('bns.mailer')->send(
            'CHECK_EMAIL',
            $base,
            $user->getEmail(),
            $user->getLang()
        );
        //TODO TOKEN
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_NEW_VALIDATE_MESSAGE_SENT', array(), 'MAIN'));
        return $this->redirect($this->generateUrl('home'));
    }
}

<?php

namespace BNS\App\RegistrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\CoreBundle\Model\GroupQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontConfirmationController extends Controller
{
    /**
     * @Route("/confirmation-classe/{token}", name="registration_confirm_classroom")
     */
    public function classRoomConfirmAction($token)
    {
		$classRoom = GroupQuery::create('g')
			->where('g.ConfirmationToken = ?', $token)
		->findOne();
		
		if (null == $classRoom) {
			return $this->render('BNSAppRegistrationBundle:Free:classroom_confirmation_failed.html.twig');
		}
		
		// Confirmation
		$this->get('bns.classroom_manager')->setClassroom($classRoom)->confirmClassRoom();
		
        return $this->render('BNSAppRegistrationBundle:Free:classroom_confirmation.html.twig', array(
			'classRoom' => $classRoom
		));
    }
}
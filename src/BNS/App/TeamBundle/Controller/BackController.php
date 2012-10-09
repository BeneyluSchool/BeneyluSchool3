<?php

namespace BNS\App\TeamBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;

use BNS\App\CoreBundle\Form\Type\AttributeHomeMessageType;
use BNS\App\CoreBundle\Form\Model\AttributeHomeMessageFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

class BackController extends Controller
{
	/**
	 * @Route("/", name="BNSAppTeamBundle_back")
	 * @Rights("TEAM_ACCESS_BACK")
	 */
	public function indexAction()
	{
	    $rightManager = $this->get('bns.right_manager');
		// Recherche de la classe qui possède l'équipe courante
		$classroomManager = $this->get('bns.classroom_manager');
		  
		$teamManager = $this->get('bns.team_manager');
		$teamManager->setTeam($rightManager->getCurrentGroup());
		$classroomOwner = $teamManager->getParent();
		if (null == $classroomOwner)
		{
			throw new HttpException(500, 'This point must be never reach!');
		}
		
		// Contruction du tableau des modules actifs/inactifs selon le rôle
		/*$activableModules = $rightManager->getActivableModules();
		$memberActivatedModules = $teamManager->getMemberActivatedModules();
		$otherActivatedModules = $teamManager->getOtherActivatedModules();
		foreach ($activableModules as $activableModule)
		{
			$activableModuleUniqueName = $activableModule->getUniqueName();
			foreach ($memberActivatedModules as $memberActivatedModule)
			{
				if ($activableModuleUniqueName == $memberActivatedModule->getUniqueName())
				{
					$activableModule->activateForMember();
					break;
				}
			}
			
			foreach ($otherActivatedModules as $pupilActivatedModule)
			{
				if ($activableModuleUniqueName == $pupilActivatedModule->getUniqueName())
				{
					$activableModule->activateForOther();
					break;
				}
			}
		}*/
		
		$activationRole = GroupTypeQuery::create()->filterBySimulateRole(true)->findByType('PUPIL');

		$form = $this->createForm(new AttributeHomeMessageType(), new AttributeHomeMessageFormModel($rightManager->getCurrentGroup()));

		if ('POST' == $this->getRequest()->getMethod())
		{
			$form->bindRequest($this->getRequest());
			if ($form->isValid())
			{
				$form->getData()->save();
			}
		}

		return $this->render('BNSAppTeamBundle:Back:index.html.twig', array(
			'activation_role'  => $activationRole,
			'form'              => $form->createView(),
			'team'              => $rightManager->getCurrentGroup(),
			'classroom'         => $classroomOwner,
		));
	}
}
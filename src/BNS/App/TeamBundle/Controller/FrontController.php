<?php

namespace BNS\App\TeamBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;

class FrontController extends Controller
{
	/**
	 * @Route("/", name="BNSAppTeamBundle_front")
	 * @Rights("TEAM_ACCESS")
	 */
	public function indexAction()
	{
		$team = $this->get('bns.right_manager')->getCurrentGroup();
		$teamManager = $this->get('bns.team_manager');
		$teamManager->setTeam($team);
		$team->setUsers($teamManager->getUsers(true));

		return $this->render('BNSAppTeamBundle:Front:index.html.twig', array(
			'team'              => $team,
			'homepageMessage'   => $team->getAttribute('HOME_MESSAGE'),
		));
}
}
<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

class ModuleController extends Controller
{
      
	/**
	 * @Route("/activation-tableau", name="BNSAppMainBundle_modules_activation", options={"expose"=true})
	 */
	public function moduleActivationAction($roles)
	{ 
		$rightManager = $this->get('bns.right_manager');
		$groupManager = $this->get('bns.group_manager');
		$groupManager->setGroup($rightManager->getCurrentGroup());
		$activableModules = $rightManager->getActivableModules();
		
		$moduleStates = array();
		foreach($roles as $role) {
			$moduleStates[$role->getId()] = $groupManager->getActivatedModules($role);
		}
		
		return $this->render('BNSAppMainBundle:Module:module_activation.html.twig', array(
			'activableModules' => $activableModules,
			'moduleStates' => $moduleStates,
			'roles' => $roles,
			'groupId'=> $groupManager->getGroup()->getId())
		);
	}
	
	/**
	 * @Route("/activer-desactiver-module", name="BNSAppMainBundle_module_activation_toggle", options={"expose"=true})
	 */
	public function moduleActivationToggleAction()
	{
		// AJAX
		if (!$this->getRequest()->isXmlHttpRequest())
		{
			//throw new NotFoundHttpException();
		}
		$request = $this->getRequest();
		$rightManager = $this->get('bns.right_manager');
		if (
			null == $request->get('groupId') ||
			null == $request->get('moduleUniqueName') ||
			null == $request->get('roleId') ||
			null == $request->get('currentState')
		)
		{
			throw new HttpException(500, 'You must provide 4 parameters: groupId, moduleUniqueName, roleId, currentState !');
		}
		
		$module = ModuleQuery::create()->findOneByUniqueName($request->get('moduleUniqueName'));
		$gm = $this->get('bns.group_manager');
		$gm->setGroupById($request->get('groupId'));
		$groupTypeRole = GroupTypeQuery::create()->findOneById($request->get('roleId'));
		$requestedValue = !$request->get('currentState');		
		
		$rightManager->getCurrentGroupManager()->activationModuleRequest($module, $groupTypeRole, $requestedValue);
		
		$rightManager->getCurrentGroupManager()->clearGroupCache();
		
		$moduleStates[$groupTypeRole->getId()] = $gm->getActivatedModules($groupTypeRole);
		
		return $this->render('BNSAppMainBundle:Module:module_activation_block.html.twig',
			array(
				'module'		=> $module,
				'role'			=> $groupTypeRole,
				'groupId'		=> $gm->getGroup()->getId(),
				'moduleStates'	=> $moduleStates
			)
		);
	}
}
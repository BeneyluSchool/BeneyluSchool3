<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Module;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModuleController extends Controller
{

    /**
     * @Route("/activation-tableau", name="BNSAppMainBundle_modules_activation", options={"expose"=true})
     */
    public function moduleActivationAction($roles, $group = null, $groupType = null)
    {
        $rightManager = $this->get('bns.right_manager');
        $groupManager = $this->get('bns.group_manager');
        switch ($groupType) {
            case 'PARTNERSHIP' :
                $activableModules = $rightManager->getActivablePartnershipModules();
                // only those 3 modules are available in high school partnerships
                break;
            case 'TEAM' :
                $activableModules = $rightManager->getActivableModules($group->getId(), 'TEAM');
                break;
            default :
                $activableModules = $rightManager->getActivableModules();
                break;
        }

        if ($group == null) {
            $groupManager->setGroup($rightManager->getCurrentGroup());
        } else {
            $groupManager->setGroup($group);
        }
        $group = $groupManager->getGroup();

        $moduleStates = array();
        foreach ($roles as $role) {
            $moduleStates[$role->getId()] = $groupManager->getActivatedModules($role);
        }

        $activatedModules = $groupManager->getActivatedModuleUniqueNames($group);
        $groupId = $group->getId();
        $rights = $this->get('bns.user_manager')->getRights();
        $applicationManager = $this->get('bns_core.application_manager');

        if ($applicationManager->isEnabled()) {
            foreach ($activableModules as $module) {
                if (isset($rights[$groupId])) {
                    $applicationManager->decorate($module, $rights[$groupId], $activatedModules, $group);
                }
            }
        }

        return $this->render('BNSAppMainBundle:Module:module_activation.html.twig',
            array(
                'activableModules' => $activableModules,
                'moduleStates' => $moduleStates,
                'roles' => $roles,
                'groupId' => $group->getId(),
                'groupType' => $groupType,
                'groupIsSchoolOrClassroom' => in_array($group->getType(), array('SCHOOL', 'CLASSROOM'))
            )
        );
    }

    /**
     * @Route("/activer-desactiver-module", name="BNSAppMainBundle_module_activation_toggle", options={"expose"=true})
     */
    public function moduleActivationToggleAction(Request $request)
    {
        // AJAX
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }
        $rightManager = $this->get('bns.right_manager');
        if (
            null == $request->get('groupId') || null == $request->get('moduleUniqueName') || null == $request->get('roleId') ||
            null == $request->get('currentState')
        ) {
            throw new HttpException(500,
            'You must provide 4 parameters: groupId, moduleUniqueName, roleId, currentState !');
        }

        $groupId = $request->get('groupId');
        $moduleUniqueName = $request->get('moduleUniqueName');
        $requestedValue = !$request->get('currentState');
        $groupTypeRole = GroupTypeQuery::create()->findOneById($request->get('roleId'));

        if ("DIRECTOR" === $groupTypeRole->getType()) {
            $entReferentRole = GroupTypeQuery::create()->filterByType("ENT_REFERENT")->findOne();
            $rightManager->toggleModule($groupId, $moduleUniqueName, $entReferentRole, $requestedValue);
        }

        $module = $rightManager->toggleModule($groupId, $moduleUniqueName, $groupTypeRole, $requestedValue);

        $moduleStates[$groupTypeRole->getId()] = $rightManager->getCurrentGroupManager()->getActivatedModules($groupTypeRole);

        return $this->render('BNSAppMainBundle:Module:module_activation_block.html.twig',
            array(
                'module' => $module,
                'role' => $groupTypeRole,
                'groupId' => $request->get('groupId'),
                'moduleStates' => $moduleStates
            )
        );
    }

}

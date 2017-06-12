<?php
namespace BNS\App\GroupBundle\ApiController;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Exception\InvalidApplication;
use BNS\App\CoreBundle\Exception\InvalidUninstallApplication;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class GroupApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get user's groups",
     * )
     *
     * @Rest\Get("")
     * @Rest\QueryParam(name="right", requirements="\w+", description="Name of a right to filter with")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return Response
     */
    public function getGroupsCurrentAction(ParamFetcherInterface $paramFetcher)
    {
        $right = $paramFetcher->get('right');
        if ($right) {
            $groups = $this->get('bns.right_manager')->getGroupsWherePermission($right);
        } else {
            $groups = $this->get('bns.right_manager')->getGroups(true);
        }

        $rightManager = $this->get('bns.right_manager');

        if ($favoriteGroupId = $this->getUser()->getFavoriteGroupId()) {
            foreach ($groups as $group) {
                if ($favoriteGroupId === $group->getId()) {
                    $group->isFavorite = true;
                    break;
                }
            }
        }

        if (!$right &&
            $rightManager->hasRightSomeWhere('CLASSROOM_ACCESS_BACK') &&
            $rightManager->getCurrentGroupManager()->isOnPublicVersion()) {

            $school = null;
            $hasPremium = false;
            foreach ($groups as $group) {
                if ('SCHOOL' === $group->getType()) {
                    if ($group->isPremium()) {
                        $hasPremium = true;
                        break;
                    } else {
                        $school = $group;
                    }
                }
            }

            if (!$hasPremium) {
                if (!$school) {
                    $classroom = $this->get('bns.right_manager')->getUserManager()->getGroupsUserBelong('CLASSROOM')->getFirst();
                    $parent = $this->get('bns.group_manager')->setGroup($classroom)->getParent();
                    if ($parent && $parent->getType() === 'SCHOOL') {
                        $school = $parent;
                    }
                }
                $schools = $this->get('bns.right_manager')->getUserManager()->getGroupsUserBelong('CLASSROOM');
                foreach($schools as $g) {
                    $parent = $this->get('bns.group_manager')->setGroup($g)->getParent();
                    if($parent && $parent->isPremium()) {
                        $groups->append($parent);
                        $school = null;
                    }
                }
            }

            // user has mgmt rights and no premium school: add a fake school group for push
            if ($school) {
                $paasManager = $this->get('bns.paas_manager');

                // TODO: remove this temporary stuff
                $prices = $this->getParameter('premium_subscription_prices');
                $price = isset($prices[$school->getCountry()]) ? $prices[$school->getCountry()] : $prices['default'];

                $fakeSchool = [
                    'type' => 'SCHOOL',
                    'label' => $school->getLabel(),
                    'page' => [
                        'name' => 'school',
                        'vars' => [
                            'price' => $price,
                            'brand' => $this->getParameter('beneylu_brand_name'),
                            'link' => $this->generateUrl('BNSAppSpotBundle_front', [
                                'code' => $paasManager::PREMIUM_SUBSCRIPTION,
                                'origin' => 'push school'
                            ]),
                        ],
                    ],
                ];

                $groups->append($fakeSchool);
            }
        }

        return $groups;
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get current group",
     * )
     *
     * @Rest\Get("/current")
     * @Rest\View(serializerGroups={"Default", "basic", "details", "app", "groupOnde", "groupAAF"})
     */
    public function getGroupCurrentAction()
    {
        $applicationManager = $this->get('bns_core.application_manager');

        $group = $this->get('bns.right_manager')->getCurrentGroup();
        if (!$group) {
            throw $this->createNotFoundException();
        }
        $groupModule = $applicationManager->getGroupSpecialModule($group);
        if ($groupModule) {
            $rights = $this->get('bns.user_manager')->getRights();
            $activatedModules = $this->get('bns.group_manager')->getActivatedModuleUniqueNames($group);
            $applicationManager->decorate($groupModule, $rights[$group->getId()], $activatedModules, $group);
        }

        $group->setApp($groupModule);

        return $group;
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get current group's school",
     * )
     *
     * @Rest\Get("/current/school")
     * @Rest\View(serializerGroups={"Default", "basic", "details", "groupUAI", "groupAAF"})
     */
    public function getGroupCurrentSchoolAction()
    {

        $group = $this->get('bns.right_manager')->getCurrentGroup();
        if (!$group) {
            throw $this->createNotFoundException();
        }
        $ancestors = $this->get('bns.group_manager')->getAncestors($group);
        foreach ($ancestors as $ancestor) {
            if ('SCHOOL' === $ancestor->getType()) {
                return $ancestor;
            }
        }

        return $this->createNotFoundException();
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Patch current group's school",
     * )
     *
     * @Rest\Patch("/current/school")
     * @Rest\View(serializerGroups={"Default", "basic", "details", "groupUAI", "groupAAF"})
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function patchtGroupCurrentSchoolAction(Request $request)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        if (!$group) {
            throw $this->createNotFoundException();
        }
        $ancestors = $this->get('bns.group_manager')->getAncestors($group);
        foreach ($ancestors as $ancestor) {
            if ('SCHOOL' === $ancestor->getType()) {
                $uai = $request->get('uai');
                if ($uai && !$ancestor->getAafId()) {
                    $ancestor->setAttribute('UAI', $uai);
                }

                return $ancestor;
            }
        }

        return $this->createNotFoundException();
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get group beta information",
     * )
     *
     * @Rest\Get("/{id}/beta")
     * @Rest\View()
     */
    public function getGroupBetaAction($id, Request $request)
    {
        $group = GroupQuery::create()->findPk($id);
        $betaManager = $this->get('bns_app_core.beta_manager');

        if (
            !$group
            || !$betaManager->isBetaModeAllowed()
            || !$this->get('bns.user_manager')->hasRight(null, $group->getId())
            || 'fr' !== $request->getLocale()
        ) {
            // no beta mode
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupManager = $this->get('bns.group_manager')->setGroup($group);
        $userIds = [];
        $roles = [
            'TEACHER',
            'PUPIL',
            'PARENT'
        ];
        foreach ($roles as $role) {
            $userIds = array_merge($userIds, $groupManager->getUsersByRoleUniqueNameIds($role));
        }
        $userIds = array_diff($userIds, [$this->getUser()->getId()]);

        $modes = UserQuery::create()
            ->filterById($userIds)
            ->groupByBeta()
            ->select('Beta')
            ->find()
        ;

        $mode = false;

        if ($modes->count() > 1) {
            $mode = 'partial';
        } else if (1 == $modes->getFirst()) {
            $mode = true;
        }

        return [
            'id' => $group->getId(),
            'beta_mode' => $betaManager->isBetaModeEnabled(),
            'beta_group_mode' => $mode,
        ];
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get group beta information",
     * )
     *
     * @Rest\Patch("/{id}/beta/{mode}", requirements={"mode"="0|1"})
     * @Rest\View()
     */
    public function patchGroupBetaAction($id, $mode, Request $request)
    {
        $group = GroupQuery::create()->findPk($id);

        $betaManager = $this->get('bns_app_core.beta_manager');
        if (!$group || !$betaManager->isBetaModeAllowed() || 'fr' !== $request->getLocale()) {
            // no beta mode
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$this->get('bns.user_manager')->hasRight('MAIN_BETA_SWITCH_GROUP', $group->getId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $groupManager = $this->get('bns.group_manager')->setGroup($group);
        $userIds = [];
        $roles = [
            'TEACHER',
            'PUPIL',
            'PARENT'
        ];
        foreach ($roles as $role) {
            $userIds = array_merge($userIds, $groupManager->getUsersByRoleUniqueNameIds($role));
        }
        $userIds = array_diff($userIds, [$this->getUser()->getId()]);

        $count = UserQuery::create()
            ->filterById($userIds)
            ->update(['Beta' => (boolean) $mode])
        ;

        if ($count) {
            return View::create('', Codes::HTTP_NO_CONTENT);
        }

        return View::create('', Codes::HTTP_NOT_MODIFIED);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Change current group",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "The new group id"
     *      }
     *  },
     * )
     *
     * @Rest\Patch("/current")
     * @Rest\View(serializerGroups={"Default"})
     */
    public function patchGroupCurrentAction(Request $request)
    {
        if ($groupId = $request->get('id')) {
            $group = $this->get('bns.group_manager')->findGroupById($groupId);
            if (!$group) {
                throw new NotFoundHttpException();
            }

            $rightManager = $this->get('bns.right_manager');
            $rightManager->switchContext($group);

            $this->get('bns.analytics.manager')->identifyUser($this->getUser(), $group);

            return $group;
        }

        return $this->view('', Codes::HTTP_BAD_REQUEST);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get applications of the group",
     * )
     *
     * @Rest\Get("/{groupId}/applications")
     * @Rest\View(serializerGroups={"Default", "basic", "details"})
     */
    public function getGroupApplicationsAction($groupId)
    {
        $rights = $this->get('bns.user_manager')->getRights();

        /** @var Group $group */
        $group = GroupQuery::create()
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$rights || !$group || !isset($rights[$groupId])) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $applicationManager = $this->get('bns_core.application_manager');

        // get all apps in the group, including those of the base stack, sorted by label
        $modules = $applicationManager->getInstalledApplications($group, $rights[$groupId], ModulePeer::TYPE_APP, $this->getUser()->getLang());
        // add group app
        $groupModule = $applicationManager->getGroupSpecialModule($group);
        $modules->prepend($groupModule);

        // add spot module
        if (in_array('SPOT_ACCESS', $rights[$groupId]['permissions'])) {
            $spot = $applicationManager->getApplication('SPOT');
            if ($spot) {
                $modules->append($spot);
            }
        }

        // add custom ARGOS module
        if ($this->container->hasParameter('argos_academy') && $this->container->getParameter('argos_academy') === $this->getUser()->getAafAcademy()) {
            $argosModule = new Module();
            $argosModule->setCustomLabel('ARGOS<sup>2.0</sup>');
            $argosModule->setUniqueName('ARGOS');
            $argosModule->setRouteFront('disconnect_user');
            $argosModule->hasAccessFront = true;
            $modules->append($argosModule);
        }

        $groupManager = $this->get('bns.group_manager');
        $groupManager->setGroup($group);
        $activatedModules = $groupManager->getActivatedModuleUniqueNames($group);

        if ('PARTNERSHIP' === $group->getType() && $group->getAttribute('IS_HIGH_SCHOOL')) {
            $forcePrivateApplications = [
                'USER_DIRECTORY',
                'MESSAGING',
                'MEDIA_LIBRARY',
                'PROFILE',
            ];
        } else {
            $forcePrivateApplications = $groupManager->getProjectInfoCurrentFirst('private_applications');
            if (!$forcePrivateApplications || !is_array($forcePrivateApplications)) {
                $forcePrivateApplications = [];
            }
        }

        $removed = [];
        foreach ($modules as $key => $module) {
            $applicationManager->decorate($module, $rights[$groupId], $activatedModules, $group);
            if (!($module->hasAccessFront || $module->hasAccessBack)
                && ($module->isPrivate || in_array($module->getUniqueName(), $forcePrivateApplications))
            ) {
                $removed[] = $key;
                continue;
            }

            if ('SCHOOL' === $module->getUniqueName()) {
                $classroom = $this->get('bns.right_manager')->getUserManager()->getGroupsUserBelong('CLASSROOM')->getFirst();
                if ($classroom) {
                    $school = $groupManager->setGroup($classroom)->getParent();
                    if ($school) {
                        $module->hasAccessFront = true;
                    }
                }
            } elseif ('PROFILE' === $module->getUniqueName()) {
                $user = $this->getUser();
                $module->setCustomLabel($user->getFullname());
            } elseif ('NOTIFICATION' === $module->getUniqueName()) {
                $rmu = $this->get('bns.right_manager')->getModulesReachableUniqueNames();

                $counter = NotificationQuery::create('n')
                    ->where('n.TargetUserId = ?', $this->getUser()->getId())
                    ->where('n.IsNew = ?', true)
                    ->joinWith('NotificationType')
                    ->where('NotificationType.ModuleUniqueName IN ?', $rmu)
                    ->count();
                $counter += $this->get('bns.announcement_manager')->countUnreadAnnouncements($this->getUser());
                $module->counter = $counter;
                $module->hasAccessFront = true;
                $module->hasAccessBack = true;
            } elseif ('INFO' === $module->getUniqueName()) {
                $module->counter = $this->get('bns.right_manager')->getNbNotifInfo() ?: null;
            }
        }
        foreach ($removed as $key) {
            // php7 bug remove key outside foreach
            unset($modules[$key]);
        }

        $hasNoRank = false;
        $modules->uasort(function (Module $appA, Module $appB) use ($groupModule, &$hasNoRank) {
            if (('SPOT' === $appA->getUniqueName() && $appA->rank === null) || ($groupModule->equals($appB) && $appB->rank === null)) {
                $hasNoRank = true;
                return 1;
            }
            if (('SPOT' === $appB->getUniqueName() && $appB->rank === null) || ($groupModule->equals($appA) && $appA->rank === null)) {
                $hasNoRank = true;
                return -1;
            }

            if (null !== $appA->rank && null !== $appB->rank) {
                if ($appA->rank === $appB->rank) {
                    return 0;
                }

                return $appA->rank < $appB->rank ? -1 : 1;
            } elseif (null !== $appA->rank) {
                $hasNoRank = true;
                return -1;
            } elseif (null !== $appB->rank) {
                $hasNoRank = true;
                return 1;
            }

            return strcmp($appA->getLabel(), $appB->getLabel());
        });

        if ($hasNoRank) {
            $rank = 0;
            foreach ($modules as $module) {
                $module->rank = $rank;
                $rank++;
            }
        }

        // fix serializer bug with array wrongly indexed (it should be 0 to count()-1)
        return array_values($modules->getArrayCopy());
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  resource = true,
     *  description="Get details of an application of the group",
     * )
     *
     * @Rest\Get("/{groupId}/applications/{applicationName}")
     * @Rest\View(serializerGroups={"Default", "basic", "details"})
     */
    public function getGroupApplicationAction($groupId, $applicationName)
    {
        $rights = $this->get('bns.user_manager')->getRights();

        $group = GroupQuery::create()
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$rights || !isset($rights[$groupId]) || !$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $applicationManager = $this->get('bns_core.application_manager');
        $modules = $applicationManager->getInstalledApplications($group, $rights[$groupId], null, $this->getUser()->getLang()); // base stack + group-only apps
        $groupModule = $applicationManager->getGroupSpecialModule($group);

        $modules->prepend($groupModule);

        // add pssst module
        if (in_array('PSSST_ACCESS', $rights[$groupId]['permissions'])) {
            $pssst = $applicationManager->getApplication('PSSST');
            if ($pssst) {
                $modules->append($pssst);
            }
        }

        $activatedModules = $this->get('bns.group_manager')->getActivatedModuleUniqueNames($group);

        foreach ($modules as $module) {
            if ($module->getUniqueName() !== $applicationName) {
                continue;
            }
            $applicationManager->decorate($module, $rights[$groupId], $activatedModules, $group);

            return $module;
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }


    /**
     * <pre>
     * {
     *  applications: [BLOG, WORKSHOP, CALENDAR]
     * }
     * </pre>
     *
     *
     * @ApiDoc(
     *  section="Groups",
     *  resource = false,
     *  description="Sort applications of the group",
     * )
     *
     * @Rest\Patch("/{groupId}/applications/sort")
     */
    public function patchGroupApplicationSortAction(Request $request, $groupId)
    {
        $rights = $this->get('bns.user_manager')->getRights();

        /** @var Group $group */
        $group = GroupQuery::create()
            ->useGroupTypeQuery()
                ->filterBySimulateRole(false)
            ->endUse()
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$rights || !isset($rights[$groupId]) || !$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $applicationManager = $this->get('bns_core.application_manager');
        $modules = $applicationManager->getInstalledApplications($group, $rights[$groupId], null, $this->getUser()->getLang()); // base stack + group-only apps

        // add group app
        $groupModule = $applicationManager->getGroupSpecialModule($group);
        $modules->prepend($groupModule);

        // add spot module
        if (in_array('SPOT_ACCESS', $rights[$groupId]['permissions'])) {
            $spot = $applicationManager->getApplication('SPOT');
            if ($spot) {
                $modules->append($spot);
            }
        }

        if ($this->canManage($group)) {
            $applications = $request->request->get('applications', []);
            if (!is_array($applications)) {
                return View::create('', Codes::HTTP_BAD_REQUEST);
            }

            $sortedModules = [];

            foreach ($applications as $application) {
                foreach ($modules as $module) {
                    if ($module->getUniqueName() === $application) {
                        $sortedModules[] = $module->getUniqueName();
                        continue 2;
                    }
                }

                // try to sort invalid module
                return View::create('', Codes::HTTP_BAD_REQUEST);
            }

            // Extra Modules
            foreach ($modules as $module) {
                if (!in_array($module->getUniqueName(), $sortedModules)) {
                    $sortedModules[] = $module->getUniqueName();
                }
            }

            $group->setSortedModules($sortedModules);
            $group->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_FORBIDDEN);
    }


    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Open or close an application for a group",
     * )
     *
     * @Rest\Patch("/{groupId}/applications/{applicationName}/{status}", requirements={"status"="open|close"})
     */
    public function patchGroupApplicationOpenAction($groupId, $applicationName, $status)
    {
        $application = $this->get('bns_core.application_manager')->getApplication($applicationName);
        $group = GroupQuery::create()
            ->useGroupTypeQuery('GroupType')
                ->filterBySimulateRole(false)
            ->endUse()
            ->with('GroupType')
            ->findPk($groupId)
        ;
        if (!$group || !$application) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupType = $group->getType();
        $roles = array();
        switch ($groupType) {
            default:
            case 'SCHOOL':
                $roles[] = 'TEACHER';
            case 'CLASSROOM':
                $roles[] = 'PUPIL';
                $roles[] = 'PARENT';
                $groupType = null;
                break;
            case 'PARTNERSHIP':
            case 'TEAM':
                $roles = array(
                    'TEACHER',
                    'PUPIL',
                    'PARENT',
                );
        }

        $groupManager = $this->get('bns.group_manager')->setGroup($group);
        $roles = GroupTypeQuery::create()->filterBySimulateRole(true)->filterByType($roles)->find();
        foreach ($roles as $role) {
            $state = ('open' === $status);
            if (!$groupManager->activationModuleRequest($application, $role, $state, $groupType, $group->getId())) {
                return View::create('', Codes::HTTP_FORBIDDEN);
            }
        }

        return View::create('', Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Uninstall an application for a group",
     * )
     *
     * @Rest\Patch("/{groupId}/applications/{applicationName}/uninstall")
     *
     * @param $groupId
     * @param $applicationName
     * @return View
     */
    public function patchGroupApplicationUninstallAction($groupId, $applicationName)
    {
        $applicationManager = $this->get('bns_core.application_manager');
        $application = $applicationManager->getApplication($applicationName);
        $group = GroupQuery::create()
            ->useGroupTypeQuery('GroupType')
                ->filterBySimulateRole(false)
            ->endUse()
            ->with('GroupType')
            ->findPk($groupId)
        ;
        if (!$group || !$application) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$this->get('bns.right_manager')->hasRight($application->getUniqueName() . '_ACTIVATION', $group->getId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        try {
            $applicationManager->uninstallApplication($applicationName, $group);
        } catch (InvalidApplication $e) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        } catch (InvalidUninstallApplication $e) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Set an application as favorite or not for a group",
     * )
     *
     * @Rest\Patch("/{groupId}/applications/{applicationName}/favorite/{state}", requirements={"state"="true|false"})
     *
     * @param $groupId
     * @param $applicationName
     * @return View
     */
    public function patchGroupApplicationFavoriteAction($groupId, $applicationName, $state)
    {
        $applicationManager = $this->get('bns_core.application_manager');
        $application = $applicationManager->getApplication($applicationName);
        /** @var Group $group */
        $group = GroupQuery::create()
            ->useGroupTypeQuery('GroupType')
                ->filterBySimulateRole(false)
            ->endUse()
            ->with('GroupType')
            ->findPk($groupId)
        ;

        if (!$group || !$application) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if ($this->canManage($group)) {
            if ('true' === $state) {
                if (!$group->hasFavoriteModule($application->getUniqueName())) {
                    $group->addFavoriteModule($application->getUniqueName());
                }
            } else {
                $group->removeFavoriteModule($application->getUniqueName());
            }

            $group->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_FORBIDDEN);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Set a group as favorite",
     * )
     *
     * @Rest\Patch("/{groupId}/favorite")
     *
     * @param int $groupId
     * @param $applicationName
     * @return View
     */
    public function patchGroupFavoriteAction($groupId)
    {
        $user = $this->getUser();
        $group = GroupQuery::create()->findPk($groupId);
        if (!$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $rights = $this->get('bns.right_manager')->getRightsInGroup($group->getId());

        if ($rights && isset($rights['permissions']) && count($rights['permissions']) > 0 ) {
            $user->setFavoriteGroupId($groupId);
            $user->save();

            return View::create('', Codes::HTTP_NO_CONTENT);
        }

        return View::create('', Codes::HTTP_FORBIDDEN);
    }

    /**
     * @ApiDoc(
     *  section="Groups",
     *  description="Edit a group",
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "groupOnde", "groupAAF"})
     *
     * @param Group $group
     * @param Request $request
     * @return View
     */
    public function patchGroupAction(Group $group, Request $request)
    {
        $permission = $group->getType() . '_ACCESS_BACK';
        if (!$this->get('bns.right_manager')->hasRight($permission, $group->getId())) {
            throw new AccessDeniedHttpException();
        }

        // TODO: actual form
        $ondeId = $request->get('onde_id');
        if ($ondeId && $group->hasAttribute('ONDE_ID') && !($group->getAafId() && $group->getAttribute('ONDE_ID'))) {
            $group->setAttribute('ONDE_ID', $ondeId);
        }

        return $group;
    }

    protected function canManage(Group $group)
    {
        return $this->get('bns.user_directory.right_manager')->isGroupManageable($group);
    }

}

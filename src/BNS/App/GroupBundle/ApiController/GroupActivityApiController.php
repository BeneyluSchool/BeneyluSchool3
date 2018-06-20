<?php
namespace BNS\App\GroupBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Exception\InvalidApplication;
use BNS\App\CoreBundle\Exception\InvalidUninstallApplication;
use BNS\App\CoreBundle\Model\ActivityQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModulePeer;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class GroupActivityApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Group Activities",
     *  resource = true,
     *  description="Get activities of the group",
     * )
     *
     * @Rest\Get("/{groupId}/activities")
     * @Rest\View(serializerGroups={"groupActivity", "Default", "basic", "details"})
     */
    public function getGroupActivitiesAction($groupId)
    {
        $rights = $this->get('bns.user_manager')->getRights();

        $group = GroupQuery::create()
            ->filterByArchived(false)
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$group || !$rights || !isset($rights[$groupId])) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $activityManager = $this->get('bns_paas.activity_manager');
        $groupActivities = $activityManager->getActivities($group);

        $activities = [];

        foreach ($groupActivities as $groupActivity) {
            $activityManager->decorate($groupActivity, $rights[$groupId]);
            $activities[] = $groupActivity;
        }

        // find applications
        $applicationManager = $this->get('bns_core.application_manager');

        // get all apps in the group, including those of the base stack, sorted by label
        $modules = $applicationManager->getInstalledApplications($group, $rights[$groupId], [ModulePeer::TYPE_EVENT], $this->getUser()->getLang());

        // add spot module
        if (in_array('SPOT_ACCESS', $rights[$groupId]['permissions'])) {
            $spot = $applicationManager->getApplication('SPOT');
            if ($spot) {
                $modules->append($spot);
            }
        }

        // add pssst module
        if (in_array('PSSST_ACCESS', $rights[$groupId]['permissions'])) {
            $pssst = $applicationManager->getApplication('PSSST');
            if ($pssst) {
                $modules->append($pssst);
            }
        }

        $activatedModules = $this->get('bns.group_manager')->getActivatedModuleUniqueNames($group);

        foreach ($modules as $module) {
            $applicationManager->decorate($module, $rights[$groupId], $activatedModules);
            $activities[] = $module;
        }

        return $activities;
    }

    /**
     * @ApiDoc(
     *  section="Group Activities",
     *  resource = true,
     *  description="Get details of an application of the group",
     * )
     *
     * @Rest\Get("/{groupId}/activities/{activityName}")
     * @Rest\View(serializerGroups={"groupActivity", "Default", "basic", "details"})
     */
    public function getGroupActivityAction($groupId, $activityName)
    {
        $rights = $this->get('bns.user_manager')->getRights();

        $group = GroupQuery::create()
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$rights || !isset($rights[$groupId]) || !$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $activityManager = $this->get('bns_paas.activity_manager');
        $groupActivity = $activityManager->getActivity($activityName, $group);

        if (!$groupActivity) {
            return $this->forward('BNS\App\GroupBundle\ApiController\GroupApiController::getGroupApplicationAction', array(
                'groupId' => $groupId,
                'applicationName' => $activityName,
            ));
        }

        $activityManager->decorate($groupActivity, $rights[$groupId]);

        return $groupActivity;
    }

    /**
     * @ApiDoc(
     *  section="Group Activities",
     *  description="Open or close an activity for a group",
     * )
     *
     * @Rest\Patch("/{groupId}/activities/{activityName}/{status}", requirements={"status"="open|close"})
     *
     * @param $groupId
     * @param $activityName
     * @param $status
     * @return View|Response
     * @throws \PropelException
     */
    public function patchGroupActivityOpenAction($groupId, $activityName, $status)
    {
        $activity = ActivityQuery::create()
            ->filterByUniqueName($activityName)
            ->findOne()
        ;
        $group = GroupQuery::create()
            ->useGroupTypeQuery('GroupType')
                ->filterBySimulateRole(false)
            ->endUse()
            ->with('GroupType')
            ->findPk($groupId)
        ;
        if (!$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$activity) {
            // it's not an activity maybe it's an application (polymorphic API)
            return $this->forward('BNS\App\GroupBundle\ApiController\GroupApiController::patchGroupApplicationOpenAction', array(
                'groupId' => $groupId,
                'applicationName' => $activityName,
                'status' => $status
            ));
        }

        $rightManager = $this->get('bns.right_manager');

        if (!$rightManager->hasRight('MAIN_ACTIVITY_ACTIVATION', $group->getId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ($status === 'open') {
            $this->get('bns_paas.activity_manager')->open($activity, $group);
        } else if ($status === 'close') {
            $this->get('bns_paas.activity_manager')->close($activity, $group);
        } else {
            return View::create('wrong status', Codes::HTTP_BAD_REQUEST);
        }

        return View::create('', Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Group Activities",
     *  description="Uninstall an activity for a group",
     * )
     *
     * @Rest\Patch("/{groupId}/activities/{activityName}/uninstall")
     *
     * @param $groupId
     * @param $activityName
     * @return View
     */
    public function patchGroupActivityUninstallAction($groupId, $activityName)
    {
        $activity = ActivityQuery::create()
            ->filterByUniqueName($activityName)
            ->findOne()
        ;

        $group = GroupQuery::create()
            ->useGroupTypeQuery('GroupType')
                ->filterBySimulateRole(false)
            ->endUse()
            ->with('GroupType')
            ->findPk($groupId)
        ;
        if (!$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$activity) {
            // it's not an activity maybe it's an application (polymorphic API)
            return $this->forward('BNS\App\GroupBundle\ApiController\GroupApiController::patchGroupApplicationUninstallAction', array(
                'groupId' => $groupId,
                'applicationName' => $activityName,
            ));
        }


        $this->get('bns_paas.activity_manager')->uninstall($activity, $group);

        return View::create('', Codes::HTTP_OK);
    }

}

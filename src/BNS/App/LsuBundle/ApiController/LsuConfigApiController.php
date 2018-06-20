<?php
namespace BNS\App\LsuBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\LsuBundle\Form\LsuConfigType;
use BNS\App\LsuBundle\Model\LsuConfig;
use BNS\App\LsuBundle\Model\LsuLevel;
use BNS\App\LsuBundle\Model\LsuLevelQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuConfigApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get all LSU configs for a group",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/groups/{id}/lsu/configs")
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_users", "lsu_birthday", "lsu_avatar", "lsu_detail"})
     *
     * @Rest\QueryParam("with_new", requirements="(1|0)", default="0")
     *
     * @return LsuConfig[]|Response
     */
    public function getConfigsAction(Group $group, ParamFetcherInterface $paramFetcher)
    {
        $this->validateGroup($group);
        //TODO: corriger l'import onde id des classes aaf
        if ($group->getAafId() && (!$group->hasAttribute('ONDE_ID') || ($group->getAttribute('ONDE_ID') == ''))) {
            $group->setAttribute('ONDE_ID', $group->getAafId());
        }
        $withNew = (boolean)$paramFetcher->get('with_new', true);

        $lsuManager = $this->get('bns_app_lsu.lsu_config_manager');
        $configs = $lsuManager->getConfigs($group);

        if (!count($configs)) {
            // init config if none exists
            $configs = $lsuManager->initConfigs($group);
        }

        if ($withNew) {
            return [
                'configs' => $configs,
                'new_users' => $lsuManager->getPupilNotInConfigs($group, $configs)
            ];
        }

        return ['configs' => $configs];
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get a LSU config",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/lsu/configs/{id}")
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_users", "lsu_birthday", "user_light"})
     *
     * @return LsuConfig
     */
    public function getConfigAction(LsuConfig $lsuConfig)
    {
        $this->validateGroup($lsuConfig->getGroup());

        $this->get('bns_app_lsu.lsu_config_manager')->filterConfigUsers($lsuConfig);

        return $lsuConfig;
    }

    /**
     *
     * Form example:
     * <pre>
     * {
     *   "lsuLevel": 1,
     *   "user_ids": [1,2,3]
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Create a LSU config for a group",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid form",
     *      404 = "Invalid group"
     *  }
     * )
     * @Rest\Post("/groups/{id}/lsu/configs")
     *
     * @return Response
     */
    public function postConfigsAction(Request $request, Group $group)
    {
        $this->validateGroup($group);

        $lsuConfig = new LsuConfig();
        $lsuConfig->setGroup($group);

        $pupilIds = $this->get('bns.group_manager')->setGroup($group)->getUsersByRoleUniqueNameIds('PUPIL');

        return $this->restForm(new LsuConfigType(), $lsuConfig, [
            'user_ids' => $pupilIds,
            'csrf_protection' => false,
            'create' => true,
        ], null, function ($object) {
            $object->save();

            return View::create($object, Codes::HTTP_CREATED);
        }, '', $request);
    }

    /**
     *
     * Form example:
     * <pre>
     * {
     *   "level_id": 1,
     *   "user_ids": [1,2,3]
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Update a LSU config for a group",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid form",
     *      404 = "Invalid group"
     *  }
     * )
     * @Rest\Patch("/lsu/configs/{id}")
     *
     * @return Response
     */
    public function patchConfigAction(LsuConfig $lsuConfig, Request $request)
    {
        $group = $lsuConfig->getGroup();
        $this->validateGroup($group);

        // ensure current config has only valid user
        $lsuConfigManager = $this->get('bns_app_lsu.lsu_config_manager');
        $lsuConfigManager->filterConfigUsers($lsuConfig);

        $pupilIds = $this->get('bns.group_manager')->setGroup($group)->getUsersByRoleUniqueNameIds('PUPIL');

        return $this->restForm(new LsuConfigType(), $lsuConfig, [
            'user_ids' => $pupilIds,
            'csrf_protection' => false,
        ], null, function($object) use ($lsuConfigManager) {
            $lsuConfigManager->filterConfigUsers($object);
            $object->save();
        }, '', $request);
    }


    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Delete a LSU config",
     *  statusCodes = {
     *     204 = "Ok",
     *     400 = "can't delete this config",
     *     404 = "Invalid group"
     *  }
     * )
     * @Rest\Delete("/lsu/configs/{id}")
     *
     * @return Response
     */
    public function deleteConfigAction(LsuConfig $lsuConfig)
    {
        $group = $lsuConfig->getGroup();
        $this->validateGroup($group);

        if ($lsuConfig->countLsuTemplates()) {
            return new Response('', Codes::HTTP_BAD_REQUEST);
        }

        $lsuConfig->delete();

        return new Response('', Codes::HTTP_NO_CONTENT);
    }


    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get all Level",
     *  statusCodes = {
     *      200 = "Ok",
     *  }
     * )
     * @Rest\Get("/lsu/levels")
     * @Rest\View(serializerGroups={"Default", "lsu"})
     *
     * @return LsuLevel[]|Response
     */
    public function getLevelsAction()
    {
        $lsuLevels = LsuLevelQuery::create()
            ->orderBySortableRank()
            ->find()
        ;

        return $lsuLevels;
    }

    protected function validateGroup(Group $group)
    {
        // Check security
        if (!$this->get('bns.right_manager')->hasRight('LSU_ACCESS_BACK', $group->getId())) {
            throw $this->createAccessDeniedException();
        }

        if ('CLASSROOM' !== $group->getType()) {
            throw $this->createNotFoundException();
        }
    }
}

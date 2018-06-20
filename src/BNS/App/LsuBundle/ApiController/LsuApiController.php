<?php
namespace BNS\App\LsuBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\LsuBundle\Form\LsuConfigType;
use BNS\App\LsuBundle\Form\LsuTemplateDomainDetailType;
use BNS\App\LsuBundle\Form\LsuTemplateType;
use BNS\App\LsuBundle\Form\LsuType;
use BNS\App\LsuBundle\Model\Lsu;
use BNS\App\LsuBundle\Model\LsuConfig;
use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use BNS\App\LsuBundle\Model\LsuLevel;
use BNS\App\LsuBundle\Model\LsuLevelQuery;
use BNS\App\LsuBundle\Model\LsuPeer;
use BNS\App\LsuBundle\Model\LsuQuery;
use BNS\App\LsuBundle\Model\LsuTemplate;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetail;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetailQuery;
use BNS\App\LsuBundle\Model\LsuTemplateQuery;
use BNS\App\TemplateBundle\Model\Template;
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
class LsuApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get accessible LSUs for the current user in the given group",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/groups/{id}/lsu", requirements={"id"="\d+"})
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_users", "lsu_birthday"})
     *
     * @param Group $group
     * @return Lsu[]|Response
     */
    public function getLsusAction(Group $group)
    {
        $this->validateGroup($group, 'LSU_ACCESS');

        $userIds = [];
        /** @var User[] $children */
        $children = $this->get('bns.user_manager')->getUserChildren($this->getUser());
        foreach ($children as $child) {
            $userIds[] = $child->getId();
        }

        return LsuQuery::create()
            ->filterByUserId($userIds)
            ->filterByValidated(true)
            ->useLsuTemplateQuery()
                ->filterByIsOpen(true)
                ->useLsuConfigQuery()
                    ->filterByGroup($group)
                ->endUse()
            ->endUse()
            ->find()
        ;
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get LSUs from ids or template id",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request"
     *  }
     * )
     * @Rest\Get("/lsu/lookup")
     * @Rest\QueryParam(name="template_id", requirements="\d+")
     * @Rest\QueryParam(name="ids", requirements="[0-9,]+")
     * @Rest\QueryParam(name="user_ids", requirements="[0-9,]+")
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_template_domain_detail", "lsu_users" ,"lsu_birthday", "lsu_gender"})
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return Lsu[]|Response
     */
    public function getLsuLookupAction(ParamFetcherInterface $paramFetcher)
    {
        $ids = $paramFetcher->get('ids') ? explode(',', $paramFetcher->get('ids')) : null;
        $templateId = $paramFetcher->get('template_id');
        $userIds = $paramFetcher->get('user_ids') ? explode(',', $paramFetcher->get('user_ids')) : null;
        $lsus = LsuQuery::create()
            ->filterByValidated(true)
            ->_if(count($ids))
                ->filterById($ids)
            ->_endif()
            ->_if($templateId)
                ->filterByTemplateId($templateId)
            ->_endif()
            ->_if(count($userIds))
                ->filterByUserId($userIds)
            ->_endif()
            ->find();
        $validLsus = [];
        foreach ($lsus as $lsu) {
            try {
                $this->validateLsu($lsu);
                $this->decorateLsu($lsu);
                $validLsus[] = $lsu;
            } catch (\Exception $e) {
                // swallow error
            }
        }

        return $validLsus;
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get existing or create a LSU for a user",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/lsu/templates/{templateId}/users/{userId}", requirements={"userId"="\d+", "templateId"="\d+"})
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_users", "lsu_birthday", "lsu_gender"})
     *
     * @return Lsu|Response
     */
    public function getLsuFromTemplateUserAction($templateId, $userId)
    {
        $template = LsuTemplateQuery::create()->findPk($templateId);
        $this->validateTemplate($template);

        $lsuConfig = $template->getLsuConfig();
        $this->get('bns_app_lsu.lsu_config_manager')->filterConfigUsers($lsuConfig);
        if (!in_array($userId, $lsuConfig->getUserIds())) {
            throw $this->createNotFoundException();
        }
        $user = UserQuery::create()->findPk($userId);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $lsu = LsuQuery::create()
            ->filterByLsuTemplate($template)
            ->filterByUser($user)
            ->findOneOrCreate()
        ;
        if ($lsu->isNew()) {
            $lsu->save();
        }

        return $lsu;
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get existing or create a LSU for a user",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/lsu/{id}", requirements={"id"="\d+"})
     * @Rest\View(serializerGroups={"Default", "lsu", "lsu_template_domain_detail", "lsu_users" ,"lsu_birthday", "lsu_gender"})
     *
     * @return Lsu|Response
     */
    public function getLsuAction(Lsu $lsu)
    {
        $this->validateLsu($lsu);
        $this->decorateLsu($lsu);

        return $lsu;
    }

    /**
     * Form example :
     * <pre>
     * {
     *   "validated": false,
     *   "accompanyingCondition": [
     *     "PAP",
     *     "PPS",
     *     "UPE2A",
     *     "PAI",
     *     "RASED",
     *     "ULIS",
     *     "PPRE"
     *   ],
     *   "accompanyingConditionOther": "test",
     *   "projects": [],
     *   "lsuComments": [
     *     {
     *       "comment": "Tu es trop fort en Math",
     *       "lsuDomain": 3
     *     },
     *     {
     *       "comment": "Un peu de travail",
     *       "lsuDomain": 5
     *     }
     *   ],
     *   "lsuPositions": [
     *     {
     *       "achievement": null,
     *       "lsuDomain": 12
     *     },
     *     {
     *       "achievement": "NOT",
     *       "lsuDomain": 13
     *     }
     *   ]
     * }
     * </pre>
     *
     * achievement :
     * <pre>
     * null
     * NOT
     * PARTIAL
     * SUCCESS
     * OVERSTEP
     * </pre>
     *
     * accompanyingCondition:
     * <pre>
     * "PAP"
     * "PPS"
     * "UPE2A"
     * "PAI"
     * "RASED"
     * "ULIS"
     * "PPRE
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Edit a LSU",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Patch("/lsu/{id}", requirements={"id"="\d+"})
     *
     * @return Response
     */
    public function patchLsuAction(Request $request, Lsu $lsu)
    {
        $this->validateLsu($lsu);

        // force collection clean up
        if ($request->request->has('lsuComments')) {
            $lsu->setLsuComments(new \PropelObjectCollection());
        }
        if ($request->request->has('lsuPositions')) {
            $lsu->setLsuPositions(new \PropelObjectCollection());
        }

        return $this->restForm(new LsuType(), $lsu, [
            'csrf_protection' => false
        ], null, null, '', $request);
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="LSU Accompanying Conditions list",
     *  statusCodes = {
     *      200 = "Ok",
     *  }
     * )
     * @Rest\Get("/lsu/accompanying-conditions")
     * @Rest\View()
     *
     * @return []|Response
     */
    public function getLsuAccompanyingConditionsAction()
    {
        return LsuPeer::getAccompanyingConditions();
    }

    protected function validateLsu(Lsu $lsu)
    {
        return $this->get('bns_app_lsu.lsu_access_manager')->validateLsu($lsu, $this->getUser());
    }

    protected function validateTemplate(LsuTemplate $template)
    {
        return $this->get('bns_app_lsu.lsu_access_manager')->validateTemplate($template);
    }

    protected function validateGroup(Group $group, $permission = 'LSU_ACCESS_BACK')
    {
        return $this->get('bns_app_lsu.lsu_access_manager')->validateGroup($group, $permission);
    }

    protected function decorateLsu(Lsu $lsu)
    {
        $this->get('bns_app_lsu.lsu_config_manager')->decorateConfig($lsu->getLsuTemplate()->getLsuConfig());
    }
}

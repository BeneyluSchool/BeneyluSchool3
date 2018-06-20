<?php
namespace BNS\App\LsuBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\LsuBundle\Form\LsuConfigType;
use BNS\App\LsuBundle\Form\LsuTemplateDomainDetailType;
use BNS\App\LsuBundle\Form\LsuTemplateType;
use BNS\App\LsuBundle\Model\LsuConfig;
use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use BNS\App\LsuBundle\Model\LsuLevel;
use BNS\App\LsuBundle\Model\LsuLevelQuery;
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
class LsuTemplateApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get all LSU template for a group",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/groups/{id}/lsu/templates")
     * @Rest\View(serializerGroups={"Default", "lsu",})
     *
     * @return LsuTemplate[]|Response
     */
    public function getTemplatesAction(Group $group)
    {
        $this->validateGroup($group, 'LSU_ACCESS_READ');

        $lsuManager = $this->get('bns_app_lsu.lsu_config_manager');
        $lsuTemplateManager = $this->get('bns_app_lsu.lsu_template_manager');
        $configs = $lsuManager->getConfigs($group);

        if (count($configs)) {
            $templates = LsuTemplateQuery::create()
                ->filterByArchived(false)
                ->filterByConfigId($configs->getPrimaryKeys(false))
                ->useLsuConfigQuery()
                    ->useLsuLevelQuery()
                        ->orderBySortableRank()
                    ->endUse()
                ->endUse()
                ->find()
            ;
            foreach ($templates as $template) {
                foreach ($configs as $config) {
                    if ($template->getConfigId() === $config->getId()) {
                        $lsuTemplateManager->setCompletion($template, $config);
                        break;
                    }
                }
            }

            return $templates;
        }

        throw $this->createNotFoundException();
    }

    /**
     * <pre>
     * {
     *   "lsu_config": 6,
     *   "started_at": "2016-10-13T15:30:17+00:00",
     *   "ended_at": "2016-12-13T15:30:17+00:00",
     *   "period": "ma super périod",
     *   "is_open": true,
     *   "is_cycle_end": true,
     *   "teacher": "M. bob"
     *   "year": 2016
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Create a new LSU template for a config",
     *  statusCodes = {
     *      201 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Post("/groups/{id}/lsu/templates")
     */
    public function postTemplatesAction(Request $request, Group $group)
    {
        $this->validateGroup($group);

        $template = new LsuTemplate();

        return $this->restForm(
            new LsuTemplateType(),
            $template,
            [
                'group_id' => $group->getId(),
                'csrf_protection' => false
            ]
            ,
            null,
            function ($object) {
                $object->save();

                return View::create($object, Codes::HTTP_CREATED);
            },
            '',
            $request
        );
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get a LSU template",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/lsu/templates/{id}")
     * @Rest\View(serializerGroups={"Default", "lsu_template_domain_detail", "lsu"})
     *
     * @return LsuTemplate|Response
     */
    public function getTemplateAction(LsuTemplate $template)
    {
        $this->validateTemplate($template);
        $this->get('bns_app_lsu.lsu_template_manager')->setCompletion($template);

        return $template;
    }

    /**
     * <pre>
     * {
     *   "started_at": "2016-10-13T15:30:17+00:00",
     *   "ended_at": "2016-12-13T15:30:17+00:00",
     *   "period": "ma super périod",
     *   "is_open": true,
     *   "is_cycle_end": true
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Create a new LSU template for a config",
     *  statusCodes = {
     *      201 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Patch("/lsu/templates/{id}")
     */
    public function patchTemplatesAction(Request $request, LsuTemplate $template)
    {
        $this->validateTemplate($template);

        return $this->restForm(
            new LsuTemplateType(),
            $template,
            [
                'csrf_protection' => false
            ]
            ,
            null,
            null,
            '',
            $request
        );
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Create a new LSU template for a config",
     *  statusCodes = {
     *      201 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Delete("/lsu/templates/{id}")
     */
    public function deleteTemplatesAction(LsuTemplate $template)
    {
        $this->validateTemplate($template);

        if (count($template->getLsus())) {
            $template->setArchived(true);
            $template->save();
        } else {
            $template->delete();
        }

        return new Response('', Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Copy a LSU template",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Post("/lsu/templates/{id}/copy")
     * @Rest\View(serializerGroups={"Default", "lsu_template_domain_detail", "lsu"})
     *
     * @param LsuTemplate $template
     * @return LsuTemplate|Response
     */
    public function copyTemplateAction(LsuTemplate $template)
    {
        $this->validateTemplate($template);
        $newTemplate = $this->get('bns_app_lsu.lsu_template_manager')->copy($template);
        $newTemplate->save();
        $this->get('bns_app_lsu.lsu_template_manager')->setCompletion($newTemplate);

        return $newTemplate;
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get a LSU domains",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\QueryParam("v", requirements="(v\d+)", default="v2016")
     * @Rest\QueryParam("cycle", requirements="(cycle[1-3]|end[1-3]|socle)", default="cycle2")
     *
     * @Rest\Get("/lsu/domains")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @return LsuDomain[]|Response
     */
    public function getDomainsAction(ParamFetcherInterface $paramFetcher)
    {
        // use parameter "v" to prevent conflict with api "version"
        $version = $paramFetcher->get('v', true);
        $cycle = $paramFetcher->get('cycle', true);

        $root = LsuDomainQuery::create()->findRoot($version);
        if (!$root) {
            throw $this->createNotFoundException();
        }

        return $root->getDescendants(LsuDomainQuery::create()->filterByCycle($cycle));
    }

    /**
     * <pre>
     *  {
     *    "label": "un élément du programme",
     *    "lsuDomain": "141"
     *  }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Add a domain detail to a LSU template",
     *  statusCodes = {
     *      201 = "Created",
     *      400 = "Invalid group type"
     *  }
     * )
     *
     * @Rest\Post("/lsu/templates/{id}/domain-details")
     *
     * @return Response
     */
    public function postTemplateDomainAction(Request $request, LsuTemplate $template)
    {
        $this->validateTemplate($template);
        $lsuTemplateDomain = new LsuTemplateDomainDetail();
        $lsuTemplateDomain->setLsuTemplate($template);

        return $this->restForm(new LsuTemplateDomainDetailType(), $lsuTemplateDomain, [
                'csrf_protection' => false,
            ],
            null,
            function ($object) {
                $object->save();

                return View::create($object, Codes::HTTP_CREATED);
            }, '', $request
        );
    }

    /**
     * <pre>
     *  {
     *    "label": "un élément du programme",
     *    "lsuDomain": "141"
     *  }
     * </pre>
     *
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Edit a LSU template domain's detail",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     *
     * @Rest\Patch("/lsu/template-domain-details/{id}", requirements={"id"="\d+"})
     *
     * @return Response
     */
    public function patchTemplateDomainDetailAction(Request $request, LsuTemplateDomainDetail $lsuTemplateDomainDetail)
    {
        $template = $lsuTemplateDomainDetail->getLsuTemplate();
        if (!$template) {
            throw $this->createNotFoundException();
        }
        $this->validateTemplate($template);

        return $this->restForm(new LsuTemplateDomainDetailType(), $lsuTemplateDomainDetail, [
            'csrf_protection' => false
        ], null, null, '', $request);
    }

    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Get a LSU template domain's detail",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     *
     * @Rest\Get("/lsu/template-domain-details/{id}", requirements={"id"="\d+"})
     * @Rest\View(serializerGroups={"Default"})
     *
     * @return LsuTemplateDomainDetail|Response
     */
    public function getTemplateDomainDetailAction(LsuTemplateDomainDetail $lsuTemplateDomainDetail)
    {
        $template = $lsuTemplateDomainDetail->getLsuTemplate();
        if (!$template) {
            throw $this->createNotFoundException();
        }
        $this->validateTemplate($template);

        return $lsuTemplateDomainDetail;
    }


    /**
     * @ApiDoc(
     *  section="LSU",
     *  resource=true,
     *  description="Delete a LSU template domain's detail",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     *
     * @Rest\Delete("/lsu/template-domain-details/{id}", requirements={"id"="\d+"})
     *
     * @return Response
     */
    public function deleteTemplateDomainDetailAction(LsuTemplateDomainDetail $lsuTemplateDomainDetail)
    {
        $template = $lsuTemplateDomainDetail->getLsuTemplate();
        if (!$template) {
            throw $this->createNotFoundException();
        }
        $this->validateTemplate($template);

        $lsuTemplateDomainDetail->delete();

        return new Response('', Codes::HTTP_NO_CONTENT);
    }


    protected function validateTemplate(LsuTemplate $template)
    {
        $group = GroupQuery::create()
            ->useLsuConfigQuery()
                ->useLsuTemplateQuery()
                    ->filterById($template->getId())
                ->endUse()
            ->endUse()
            ->findOne()
        ;
        if (!$group) {
            throw $this->createNotFoundException();
        }

        $this->validateGroup($group);

        return $group;
    }

    protected function validateGroup(Group $group, $permission = 'LSU_ACCESS_BACK')
    {
        // Check security
        if (!$this->get('bns.right_manager')->hasRight($permission, $group->getId())) {
            throw $this->createAccessDeniedException();
        }

        if ('CLASSROOM' !== $group->getType()) {
            throw $this->createNotFoundException();
        }
    }
}

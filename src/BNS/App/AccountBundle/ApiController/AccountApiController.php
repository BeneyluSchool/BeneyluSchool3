<?php
namespace BNS\App\AccountBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

class AccountApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Account",
     *  resource=true,
     *  description="Get licences of the current user",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid group type"
     *  }
     * )
     * @Rest\Get("/licences")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @return array
     */
    public function getLicencesAction()
    {
        $storeLinks = $this->getParameter('bns_store_links');

        /** @var Group[] $groups */
        $groups = $this->get('bns.user_manager')->getSimpleGroupsAndRolesUserBelongs(true);
        $data = [];
        foreach ($groups as $group) {
            if (!in_array($group->getType(), ['CLASSROOM', 'SCHOOL'])) {
                continue;
            }
            $store = $this->get('bns.group_manager')->getSpotStore($group);
            $plan = $this->get('bns_app_paas.manager.licence_manager')->getLicenceArray($group);
            if (isset($plan['end']) && $plan['end'] instanceof \DateTime) {
                $plan['end'] = $plan['end']->format('Y-m-d');
            }
            $data[] = [
                'group' => $group,
                'plan' => $plan,
                'pay_url' => $storeLinks['pay_url'][$store] ?? $storeLinks['pay_url']['default'] ?? $storeLinks['pay_url'] ?? null,
            ];
        }

        return $data;
    }

}

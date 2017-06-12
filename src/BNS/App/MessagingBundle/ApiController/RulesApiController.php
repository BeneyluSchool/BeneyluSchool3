<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RulesApiController
 *
 * @package BNS\App\MessagingBundle\ApiController
 */
class RulesApiController extends BaseMessagingApiController
{

    /**
     * @ApiDoc(
     *  section="Messagerie - Règles",
     *  resource=true,
     *  description="Détails d'une règle de la messagerie",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "description" = "ID du groupe cible"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La règle n'a pas été trouvée"
     *  }
     * )
     * @Rest\Get("/{type}/{id}", requirements={ "type": "EXTERNAL|GROUP" })
     *
     * @Rest\View()
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param String $type
     * @param Group $group
     * @return Response
     */
    public function getAction($type, Group $group)
    {
        if ($type === 'EXTERNAL'){
            $permission = 'MESSAGING_NO_EXTERNAL_MODERATION';
        } else {
            $permission = 'MESSAGING_NO_GROUP_MODERATION';
        }

        $rightManager = $this->get('bns.right_manager');

        if (!$rightManager->hasRight('MESSAGING_ACCESS', $group->getId())) {
            return $this->view('', Codes::HTTP_FORBIDDEN);
        }

        $groupManager = $this->get('bns.group_manager');
        $pupilRole  = GroupTypeQuery::create()->findOneByType('PUPIL');

        // If we have the rule for the group then the moderation is disable
        // false = pupils have permission MESSAGING_NO_GROUP_MODERATION = moderation disable
        // true = pupils doesn't have permission MESSAGING_NO_GROUP_MODERATION = moderation enable

        return [
            'status' => !in_array($permission, $groupManager->getPermissionsForRole($group, $pupilRole)),
        ];
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Règles",
     *  resource=false,
     *  description="Modifie une règle de la messagerie",
     *  requirements = {
     *      {
     *          "name" = "status",
     *          "dataType" = "boolean",
     *          "description" = "Si activer ou désactiver la règle"
     *      },
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "description" = "ID du groupe cible"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La règle n'a pas été trouvée"
     *  }
     * )
     * @Rest\Patch("/{type}/{id}", requirements={ "type": "EXTERNAL|GROUP" })
     *
     * @Rest\View()
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     * @param String $type
     * @param Group $group
     * @param Request $request
     * @return Response
     */
    public function patchAction($type, Group $group, Request $request)
    {
        if ($type === 'EXTERNAL'){
            $rank = 'MESSAGING_SEND_EXTERNAL';
        } else {
            $rank = 'MESSAGING_SEND_INTERNAL';
        }
        // send true to enable moderation => $status false to remove the rank
        // send false to disable moderation => $status true to add the rank
        $status = !$request->get('status');

        $this->get('logger')->debug('patchAction' .  $status, ['status' => $status]);

        $rightManager = $this->get('bns.right_manager');
        $groupManager = $this->get('bns.group_manager');

        if (!$rightManager->hasRight('MESSAGING_ACCESS_BACK', $group->getId())) {
            return $this->view('', Codes::HTTP_FORBIDDEN);
        }

        if (!in_array($group->getType(), ['CLASSROOM', 'SCHOOL', 'TEAM'])) {
            return $this->view('Invalid group type', Codes::HTTP_BAD_REQUEST);
        }

        $pupilRole = GroupTypeQuery::create()->findOneByType('PUPIL');
        $groupManager->setGroup($group)->activationRankRequest($rank, $pupilRole, $status);

        return $this->getAction($type, $group);
    }

}

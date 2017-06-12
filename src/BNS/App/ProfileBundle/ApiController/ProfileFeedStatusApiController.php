<?php

namespace BNS\App\ProfileBundle\ApiController;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;
use BNS\App\CoreBundle\Model\ProfileFeedStatus;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ProfileFeedStatusApiController
 *
 * @package BNS\App\ProfileBundle\ApiContoller
 */
class ProfileFeedStatusApiController extends BaseApiController
{

    /**
     * <pre>
     * {
     *   "game": {
     *     "world": 4,
     *     "score": 210401
     *   }
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="Profile - status",
     *  resource=true,
     *  description="Post a new status about winning a Space Ops game",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Invalid data",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Post("/space-ops")
     * @Rest\View()
     * @Rights("PROFILE_ACCESS_BACK")
     *
     * @param Request $request
     * @return null
     */
    public function postNewSpaceOpsGameAction(Request $request)
    {
        $game = $request->get('game', []);
        $userId = $request->get('user_id', null);
        if (!isset($game['world']) || !isset($game['score']) || !$userId) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if ($user->getId() != $userId) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $world = intval($game['world']);
        $score = intval($game['score']);
        if (!($world && $score)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $operations = [
            '+' => 'BUTTON_ADDITION',
            '-' => 'BUTTON_SUBSTRACTION',
            '*' => 'BUTTON_MULTIPLICATION',
            'all' => 'BUTTON_ALL_OPERATIONS',
        ];
        if (!isset($operations[$game['operation']])) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        /** @Ignore */
        $operation = $this->get('translator')->trans($operations[$game['operation']], [], 'JS_SPACE_OPS');

        $status = new ProfileFeedStatus();
        $status->setContent($this->get('translator')->trans('DESCRIPTION_WIN_SPACE_OPS', [
            '%station%' => $world,
            '%operation%' => $operation,
            '%score%' => $score,
        ], 'PROFILE_STATUS'));
        $status->setModuleUniqueName('SPACE_OPS');
        $status->getFeed()
            ->setProfileId($user->getProfileId())
            ->setDate(time())
            ->setStatus(ProfileFeedPeer::STATUS_VALIDATED)
        ;

        $status->save();

        return $this->view(null, Codes::HTTP_CREATED);
    }

}

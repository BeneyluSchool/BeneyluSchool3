<?php

namespace BNS\App\HomeworkBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\HomeworkBundle\Form\Type\HomeworkPreferencesType;
use BNS\App\HomeworkBundle\Model\HomeworkPreferences;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class HomeworkPreferencesApiController
 *
 * @package BNS\App\HomeworkBundle\ApiController
 */
class HomeworkPreferencesApiController extends BaseHomeworkApiController
{

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Récupère les préférences du cahier de texte",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le cahier de textes n'a pas été trouvé."
     *  }
     * )
     *
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     *
     * @return mixed
     */
    public function getAction()
    {
        return HomeworkPreferencesQuery::create()->findOrInit($this->get('bns.right_manager')->getCurrentGroupId());
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  description="Modifie les préférences du cahier de texte",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *  }
     * )
     *
     * @Rest\Patch("")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param Request $request
     * @return mixed
     */
    public function patchAction(Request $request)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();

        if (!$this->canManageGroup($group)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $preferences = HomeworkPreferencesQuery::create()->findOrInit($group->getId());
        $ctrl = $this;

        return $this->restForm(new HomeworkPreferencesType(), $preferences, [
            'csrf_protection' => false,
        ], null, function ($preferences) use ($request, $ctrl) {
            /** @var HomeworkPreferences $preferences */

            // PATCH can't bind on arrays: do it and validate manually
            $preferences->setDays($request->get('days', []));
            if (!count($preferences->getDays())) {
                return $ctrl->view(null, Codes::HTTP_BAD_REQUEST);
            }
            $preferences->save();

            return $preferences;
        });
    }

}

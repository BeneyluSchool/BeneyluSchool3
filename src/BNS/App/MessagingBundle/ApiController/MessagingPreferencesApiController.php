<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 16/03/2018
 * Time: 12:40
 */

namespace BNS\App\MessagingBundle\ApiController;


use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MessagingBundle\Form\Type\PreferencesType;
use BNS\App\MessagingBundle\Model\MessagingPreferences;
use BNS\App\MessagingBundle\Model\MessagingPreferencesQuery;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;


class MessagingPreferencesApiController extends BaseMessagingApiController
{
    /**
     * @ApiDoc(
     *  section="Messagerie - Preferences",
     *  resource=false,
     *  description="Messagerie - Suppression d'une conversation",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     */
    public function getPrefencesAction()
    {
        if (!$this->hasFeature('messaging_sdet_preferences')) {
            throw $this->createAccessDeniedException();
        }
        $rightManager = $this->get('bns.right_manager');
        $messagePreferences = MessagingPreferencesQuery::create()->filterByUserId($rightManager->getUserSession()->getId())
            ->findOne();
        if (!$messagePreferences) {
            $messagePreferences = new MessagingPreferences();
            $messagePreferences->setUserId($rightManager->getUserSession()->getId())
                ->setAlias($rightManager->getUserSession()->getFullName())
                ->save();
        }
        return $messagePreferences;
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Preferences",
     *  resource=false,
     *  description="Messagerie - Modification des préférences",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Patch("")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     */
    public function patchPrefencesAction(Request $request)
    {
        if (!$this->hasFeature('messaging_sdet_preferences')) {
            throw $this->createAccessDeniedException();
        }
        $rightManager = $this->get('bns.right_manager');
        $messagePreferences = MessagingPreferencesQuery::create()->filterByUserId($rightManager->getUserSession()->getId())
            ->findOne();
        if (!$messagePreferences) {
            throw $this->createNotFoundException();
        }
        $this->restForm(new PreferencesType(), $messagePreferences, array(
            'csrf_protection' => false,
        ));
        return $this->view('', Codes::HTTP_OK);
    }

}

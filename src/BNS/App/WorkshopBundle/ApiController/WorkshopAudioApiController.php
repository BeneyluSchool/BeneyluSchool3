<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\WorkshopBundle\Model\WorkshopAudio;
use BNS\App\WorkshopBundle\Model\WorkshopContentInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkshopAudioApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Audio",
     *  resource=true,
     *  description="Création d'un document audio de l'atelier",
     *  statusCodes = {
     *      201 = "Document créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à l'atelier"
     *  }
     * )
     *
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Request $request
     * @return WorkshopAudio
     */
    public function postAction(Request $request)
    {
        $this->checkWorkshopAccess();
        $this->checkFeatureAccess('workshop_audio');

        $contentManager = $this->get('bns.workshop.content.manager');
        $user = $this->getUser();
        $destination = $this->get('bns.media_folder.manager')->getMyWorkshopFolder($user);
        $media = $this->get('bns.media.creator')->createFromRequest($destination, $user->getId(), $request, true, false)[0];
        $workshopAudio = $contentManager->setup(new WorkshopAudio(), $user, $media);

        $ctrl = $this;

        return $this->restForm('workshop_audio', $workshopAudio, array(
            'csrf_protection' => false,
        ), null, function () use ($request, $contentManager, $workshopAudio, $ctrl) {
            // force save of related parent object
            $workshopAudio->getWorkshopContent()->getMedia()->setIsPrivate(true);
            $workshopAudio->save();
            $workshopAudio->getWorkshopContent()->save();

            // update user ids, if given
            $userIds = $request->get('user_ids', false);

            if (is_array($userIds)) {
                $userIds = $ctrl->checkContributorUserIds($workshopAudio, $userIds);
                $contentManager->setContributorUserIds($workshopAudio->getWorkshopContent(), $userIds);
            }

            // update group ids, if given
            $groupIds = $request->get('group_ids', false);
            if (is_array($groupIds)) {
                $groupIds = $ctrl->checkContributorGroupIds($workshopAudio, $groupIds);
                $contentManager->setContributorGroupIds($workshopAudio->getWorkshopContent(), $groupIds);
            }

            // force save of related parent object
            $workshopAudio->save();
            $workshopAudio->getWorkshopContent()->save();

            return $workshopAudio;
        });
    }

}

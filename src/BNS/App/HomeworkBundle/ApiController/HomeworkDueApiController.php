<?php

namespace BNS\App\HomeworkBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\HomeworkBundle\Model\HomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkTask;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class HomeworkDueApiController
 *
 * @package BNS\App\HomeworkBundle\ApiController
 */
class HomeworkDueApiController extends BaseHomeworkApiController
{

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Détails d'une occurrence d'un devoir",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "L'occurrence de devoir n'a pas été trouvée."
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default", "homework_due_detail", "homework_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param HomeworkDue $homeworkDue
     * @return mixed
     */
    public function getAction(HomeworkDue $homeworkDue)
    {
        if (!$this->canManageHomeworkDue($homeworkDue)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        return $homeworkDue;
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  description="Suppression d'une occurrence d'un devoir",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "L'occurrence de devoir n'a pas été trouvée."
     *  }
     * )
     *
     * @Rest\Delete("/{id}")
     * @Rest\View()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param HomeworkDue $homeworkDue
     * @return mixed
     */
    public function deleteAction(HomeworkDue $homeworkDue)
    {
        if (!$this->canManageHomeworkDue($homeworkDue)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $homeworkDue->delete();

        return $this->view(null, Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Marquage comme 'fait' d'une occurrence d'un devoir",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "L'occurrence de devoir n'a pas été trouvée."
     *  }
     * )
     *
     * @Rest\Post("/{id}/done")
     * @Rest\View()
     *
     * @param HomeworkDue $homeworkDue
     * @param Request $request
     * @return mixed
     */
    public function doneAction($id, Request $request)
    {
        if ($step = $request->get('step')) {
            if ($step >= '1-1.2') {
                return $this->view(null, Codes::HTTP_CREATED);
            }

            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRightSomeWhere('HOMEWORK_SIGN')) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $homeworkDue = HomeworkDueQuery::create()->findPk($id);
        if (!$homeworkDue) {
            return $this->view(null, Codes::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if ($homeworkDue->isDoneBy($user)) {
            return $this->view(null, Codes::HTTP_NOT_MODIFIED);
        }

        $groupIds = $homeworkDue->getHomework()->getGroupsIds();
        $hasRight = false;
        foreach ($groupIds as $groupId) {
            if ($rightManager->hasRight('HOMEWORK_SIGN', $groupId)) {
                $hasRight = true;
                break;
            }
        }
        if (!$hasRight) {
            // no rights user can't set task done
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $task = new HomeworkTask();
        $task->setHomeworkDue($homeworkDue);
        $task->setUser($user);
        $task->setDone(1);
        $task->save();

        // mise à jour du nombre de devoirs faits par les élèves pour cette échéance
        $homeworkDue->updateNumberOfTasksDone();

        $this->get('stat.homework')->validateWork();

        return $this->view(null, Codes::HTTP_CREATED);
    }

}

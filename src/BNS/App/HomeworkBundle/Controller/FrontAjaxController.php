<?php

namespace BNS\App\HomeworkBundle\Controller;

use BNS\App\HomeworkBundle\Model\HomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\HomeworkBundle\Model\HomeworkTask;

class FrontAjaxController extends Controller
{

    /**
     * @Route("/taches/{hdId}", name="BNSAppHomeworkBundle_frontajax_task_status", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_SIGN")
     */
    public function taskDoneAction(Request $request, $hdId)
    {
        if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
            /** @var HomeworkDue $homeworkDue */
            $homeworkDue = HomeworkDueQuery::create()
                ->joinWith('Homework')
                ->findPk($hdId);
            if (!$homeworkDue) {
                throw $this->createNotFoundException();
            }

            $right_manager = $this->get('bns.right_manager');

            $groupIds = $homeworkDue->getHomework()->getGroupsIds();
            $hasRight = false;
            foreach ($groupIds as $groupId) {
                if ($right_manager->hasRight('HOMEWORK_SIGN', $groupId)) {
                    $hasRight = true;
                    break;
                }
            }
            if (!$hasRight) {
                // no rights user can't set task done
                throw $this->createAccessDeniedException();
            }

            $task = new HomeworkTask();
            $task->setHomeworkDue($homeworkDue);
            $task->setUser($this->getUser());
            $task->setDone(true);
            $task->save();

            // mise à jour du nombre de devoirs faits par les élèves pour cette échéance
            $task->getHomeworkDue()->updateNumberOfTasksDone();

            $this->get('stat.homework')->validateWork();

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_front'));
    }

}

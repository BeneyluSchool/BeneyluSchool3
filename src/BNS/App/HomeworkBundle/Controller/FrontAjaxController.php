<?php

namespace BNS\App\HomeworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\HomeworkBundle\Model\HomeworkTaskQuery;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\HomeworkBundle\Model\HomeworkTask;

class FrontAjaxController extends Controller
{

    /**
     * 
     * @Route("/taches/{hdId}", name="BNSAppHomeworkBundle_frontajax_task_status", options={"expose"=true})
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     */
    public function taskDoneAction($hdId)
    {
        if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
            
            $right_manager = $this->get('bns.right_manager');
            
            $task = new HomeworkTask();
            $task->setHomeworkDueId($hdId);
            $task->setUser($right_manager->getUserSession());
            $task->setDone(1);
            $task->save();
            
            // mise à jour du nombre de devoirs faits par les élèves pour cette échéance
            $task->getHomeworkDue()->updateNumberOfTasksDone();

            return new Response();
        }

        return $this->redirect($this->generateUrl('BNSAppHomeworkBundle_front'));
    }

}
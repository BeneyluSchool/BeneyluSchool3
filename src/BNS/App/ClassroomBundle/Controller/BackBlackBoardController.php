<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\ClassroomBundle\Form\Type\GroupBlackboardType;
use BNS\App\ClassroomBundle\Model\GroupBlackboardQuery;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Marion
 */
class BackBlackBoardController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_blackboard")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        $groupBoard = $this->get('bns.group_manager')->setGroup($group)->getProjectInfo('has_group_blackboard');
        if (!$groupBoard) {
            return $this->redirect($this->generateUrl("BNSAppClassroomBundle_back"));
        }
        $board = GroupBlackboardQuery::create()
            ->filterByGroupId($group->getId())
            ->findOneOrCreate();

        $image = '';
        if ($board) {
            $image = $board->getImageId();
            $board->setGroupId($group->getId());
        }
        $form = $this->createForm(new GroupBlackboardType(), $board);

        $form->handleRequest($request);
        $this->get('bns.media.manager')->bindAttachments($board, $request);
        if ($form->isValid()) {
            $this->get('bns.media.manager')->saveAttachments($board, $request);

            $board->save();

            return $this->redirect($this->generateUrl("BNSAppClassroomBundle_back_blackboard"));
        }

        return $this->render('BNSAppClassroomBundle:BackBlackBoard:classroom_black_board_index.html.twig', [
            'image' => $image,
            'form' => $form->createView()
        ]);
    }
}

<?php

namespace BNS\App\SchoolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Form\Type\AttributeHomeMessageType;
use BNS\App\CoreBundle\Form\Model\AttributeHomeMessageFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\ClassroomBundle\Form\Type\GroupBlackboardType;
use BNS\App\ClassroomBundle\Model\GroupBlackboardQuery;
use BNS\App\ClassroomBundle\Model\GroupBlackboard;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

/**
 * @author Marion
 */
class BackBlackBoardController extends Controller
{
    /**
     * @Route("/", name="BNSAppSchoolBundle_back_blackboard")
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        $groupBoard = $this->get('bns.group_manager')->setGroup($group)->getProjectInfo('has_group_blackboard');
        if (!$groupBoard) {
            return $this->redirect($this->generateUrl("BNSAppSchoolBundle_back"));
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

            return $this->redirect($this->generateUrl("BNSAppSchoolBundle_back_blackboard"));
        }

        return $this->render('BNSAppSchoolBundle:BackBlackBoard:school_black_board_index.html.twig', [
            'image' => $image,
            'form' => $form->createView()
        ]);
    }
}

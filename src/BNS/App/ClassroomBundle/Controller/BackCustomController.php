<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\ClassroomBundle\Form\Model\EditClassroomFormModel;
use BNS\App\ClassroomBundle\Form\Type\EditClassroomType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Eric
 */
class BackCustomController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_custom")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
        $classroom = $this->get('bns.right_manager')->getCurrentGroup();
        $classroomManager = $this->get('bns.classroom_manager')->setClassroom($classroom);
        $currentTimezone = (!empty($classroomManager->getPupils(false))) ? $classroomManager->getPupils()[0]->getTimezone() : null;
        $currentLang = $classroom->getLang();
        $parameters = array(
            'timezone' => $currentTimezone,
            'lang' => $currentLang
        );

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $withCountry = false;
        if ($classroomManager->isOnPublicVersion()) {
            $school = $classroomManager->getParent();
            if ($school->getType() === 'SCHOOL') {
                $withCountry = !$school->isPremium();
            } else {
                $withCountry = true;
            }
        }

        $form = $this->createForm(
            new EditClassroomType($parameters),
            new EditClassroomFormModel(
                $this->get('bns.right_manager')->getCurrentGroup(),
                $this->get('bns.group_manager'),
                $this->get('translator')
            ), [
                'with_country' => $withCountry
            ]
        );
        $form->handleRequest($request);
        if ($form->isValid()) {
            if ($form->getData()->lang != null) {
                $pupils = $this->get('bns.classroom_manager')->setClassroom($classroom)->getPupils();
                $classroom->setLang($form->getData()->lang);
                foreach ($pupils as $pupil) {
                    $pupil->setLang($form->getData()->lang);
                    $pupil->save();
                }
            }
            if ($form->getData()->timezone != null) {
                $pupils = $this->get('bns.classroom_manager')->setClassroom($classroom)->getPupils();
                foreach ($pupils as $pupil) {
                    $pupil->setTimezone($form->getData()->timezone);
                    $pupil->save();
                }
            }
            $data = $form->getData();
            $data->save($withCountry);

            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_custom'));
        }

        return $this->render(
            'BNSAppClassroomBundle:BackCustom:classroom_custom_index.html.twig',
            array(
                'hasGroupBoard' => $hasGroupBoard,
                'classroom' => $classroom,
                'form' => $form->createView(),
                'level_separator_labels' => array(
                    0 => $this->get('translator')->trans('INFANT_SCHOOL', array(), "CLASSROOM"),
                    3 => $this->get('translator')->trans('ELEMENTARY', array(), "CLASSROOM"),
                    8 => $this->get('translator')->trans('MIDDLE_SCHOOL', array(), "CLASSROOM"),
                    12 => $this->get('translator')->trans('HIGH_SCHOOL', array(), "CLASSROOM"),
                    15 => $this->get('translator')->trans('CLIS', array(), "CLASSROOM")
                )
            )
        );
    }

    /**
     * @Route("/sauvegarder", name="classroom_custom_save")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function saveAction(Request $request)
    {
        return $this->indexAction($request);
    }
}

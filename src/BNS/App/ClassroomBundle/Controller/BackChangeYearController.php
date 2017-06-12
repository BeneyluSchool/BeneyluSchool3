<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\ClassroomBundle\Form\Type\DataResetWithoutOptionType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BNS\App\CoreBundle\Annotation\Rights;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/gestion/changement-annee")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackChangeYearController extends Controller
{
    /**
     * @Route("/", name="classroom_manager_changeyear")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function indexAction()
    {
        if (!$this->canChangeYear()) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back'));
        }

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $form = $this->createForm(new DataResetWithoutOptionType(), $currentGroup);
        $dataResetManager = $this->get('bns.data_reset.manager');

        return $this->render('BNSAppClassroomBundle:BackChangeYear:index.html.twig', array(
            'dataResetWithOptions' => $dataResetManager->getDataResets('change_year', true),
            'form' => $form->createView(),
            'currentGroup' => $currentGroup
        ));
    }

    /**
     * @Route("/etape/validation", name="data_reset_changeyear_validate", defaults={"isValidation": true})
     * @Route("/etape", name="data_reset_changeyear_dostep", options={"expose": true})
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function executeAction($isValidation = false)
    {
        if (!$this->canChangeYear()) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back'));
        }
        
        $request = $this->getRequest();
        if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() || $request->get('name', false) === false || $request->get('group_id', false) === false) {
            return $this->redirect($this->generateUrl('classroom_manager_changeyear'));
        }

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        if ($currentGroup->getId() != $request->get('group_id')) {
            return $this->redirect($this->generateUrl('classroom_manager_changeyear'));
        }

        $form = $this->get('bns.data_reset.manager')->getDataReset('change_year', $request->get('name'))->getForm();
        $form->bind($request);
        $response = new Response();
        
        if (!$form->isValid()) {
            $errors = $form->getErrors();
            $response->setContent(json_encode(array(
                'success' => false,
                'name'    => $request->get('name'),
                'error'   => $errors[0]->getMessage()
            )));

            return $response;
        }

        // Validation only, we checking choices
        if ($isValidation) {
            $response->setContent(json_encode(array(
                'name'    => $request->get('name'),
                'success' => true
            )));

            return $response;
        }

        $dataReset = null;
        try {
            $dataReset = $this->get('bns.data_reset.manager')->getDataReset('change_year', $request->get('name'));
        }
        catch (\RuntimeException $e) {
            return $this->redirect($this->generateUrl('classroom_manager_changeyear'));
        }

        try {
            $dataReset->reset($currentGroup);
        }
        catch (\DataResetException $e) {
            $response->setContent(json_encode(array(
                'success' => false,
                'name'    => $request->get('name'),
                'error'   => $e->getViewMessage()
            )));
        }

        $response->setContent(json_encode(array(
            'success' => true
        )));

        return $response;
    }

    /**
     * @Route("/finaliser", name="data_reset_changeyear_dofinish")
     *
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function finalStepAction(Request $request)
    {
        if (!$this->canChangeYear()) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back'));
        }
        
        if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
            return $this->redirect($this->generateUrl('classroom_manager_changeyear'));
        }
        
        $this->get('stat.classroom')->changeGrade();

        $form = $this->createForm(new DataResetWithoutOptionType(), $this->get('bns.right_manager')->getCurrentGroup());
        $form->bind($request);
        $response = new Response();

        if (!$form->isValid()) {
            $response->setContent(json_encode(array(
                'success' => false
            )));

            return $response;
        }
        
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        if ($currentGroup->getId() != $form->getData()->getId()) {
            return $this->redirect($this->generateUrl('classroom_manager_changeyear'));
        }

        $dataResets = $this->get('bns.data_reset.manager')->getDataResets('change_year', false);
        foreach ($dataResets as $dataReset) {
            $dataReset->reset($currentGroup);
        }

        // Finally, flag the classroom at the current year
        $this->get('bns.group_manager')->setGroup($currentGroup)->setAttribute('CURRENT_YEAR', $this->container->getParameter('registration.current_year'));

        $response->setContent(json_encode(array(
            'success' => true
        )));

        return $response;
    }

    /**
     * @return Response 
     */
    public function sidebarAction()
    {
        if ($this->canChangeYear()) {
            return $this->render('BNSAppClassroomBundle:BackChangeYear:sidebar.html.twig');
        }

        return new Response();
    }

    /**
     * @return boolean 
     */
    private function canChangeYear()
    {
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $currentYear = $this->get('bns.group_manager')->setGroup($currentGroup)->getAttribute('CURRENT_YEAR', null);
        $paramCurrentYear = $this->container->getParameter('registration.current_year');
        
        if($this->container->hasParameter('registration.force_change') && $currentYear != $paramCurrentYear)
        {
            if($this->container->getParameter('registration.force_change') == true)
            {
                $currentGroup->setAttribute('CURRENT_YEAR',$paramCurrentYear);
                return false;
            }
        }
        
        if (null == $currentYear || $this->container->getParameter('registration.current_year') > $currentYear && $this->get('bns.group_manager')->getProjectInfo('has_change_year') == true) {
            return true;
        }

        return false;
    }
}
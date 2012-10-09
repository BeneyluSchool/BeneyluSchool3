<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;

use BNS\App\CoreBundle\Model\GroupTypePeer;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\SchoolBundle\Model\School;

use BNS\App\CoreBundle\Form\Type\GroupType;

use BNS\App\SchoolBundle\Form\Type\SchoolTeacherType;
use BNS\App\SchoolBundle\Form\Type\SchoolPupilType;
use BNS\App\SchoolBundle\Form\Type\SchoolClassroomType;

/**
 * @Route("/admin")
 *
 */
class UserSchoolBackController extends Controller
{
    /**
     * @Route("/{slug}/user", name="BNSAppSchoolBundle_user_index")
     * @Template()
     */
    public function userIndexAction($slug)
    {
        $school = $this->getSchoolBySlug($slug);
        
        $form_teacher = $this->createForm(new SchoolTeacherType());
        
        $form_pupil = $this->createForm(new SchoolPupilType());
        
    	return array(
    		'school' => $school,
    		'teacher_form' => $form_teacher->createView(),
    		'pupil_form' => $form_pupil->createView(),
    	);
    }
    
    /**
     * @Route("/{slug}/users/add-teacher", name="BNSAppSchoolBundle_users_add_teacher")
     * @param unknown_type $slug
     */
    public function addTeacherAction($slug)
    {    	
        $school = $this->getSchoolBySlug($slug);
    	
    	$form = $this->createForm(new SchoolTeacherType());
    	
    	$request = $this->getRequest();
    	
    	if ('POST' === $request->getMethod()) {
			$form->bindRequest($request);
			if ($form->isValid())
			{
				School::addTeacher($school, $form->getData());
				
				return new RedirectResponse($this->generateUrl('BNSAppSchoolBundle_user_index', array('slug' => $school->getSlug())));
			}
    	}
    	
    	throw new NotFoundHttpException();
    }
    
    /**
     * @Route("/{slug}/users/delete-teacher-{id}", name="BNSAppSchoolBundle_users_delete_teacher", options={"expose"=true})
     * @param unknown_type $slug
     */
    public function deleteTeacherAJAXAction($slug, $id)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    
        $school = $this->getSchoolBySlug($slug);
    	
    	$state = School::deleteTeacher($school, $id);
    	
    	return new Response(json_encode($state));
    }
    
    
    /**
     * @Route("/{slug}/users/switch-director-{id}", name="BNSAppSchoolBundle_users_switch_director", options={"expose"=true})
     */
    public function switchDirectorAJAXAction($slug, $id)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    	
        $school = $this->getSchoolBySlug($slug);
    	
    	$state = School::switchDirector($school, $id);
    	
    	return new Response(json_encode($state));
    }
    
    
    /**
     * @Route("/{slug}/users/switch-teacher-{id}", name="BNSAppSchoolBundle_users_switch_teacher", options={"expose"=true})
     */
    public function switchTeacherAJAXAction($slug, $id)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    
        $school = $this->getSchoolBySlug($slug);
    
    	$state = School::switchTeacher($school, $id);
    
    	return new Response(json_encode($state));
    }
    
    
    /**
     * @Route("/{slug}/users/add-pupil", name="BNSAppSchoolBundle_users_add_pupil", options={"expose"=true})
     * @param unknown_type $slug
     */
    public function addPupilAction($slug)
    {    
    	$school = $this->getSchoolBySlug($slug);
    
    	$form = $this->createForm(new SchoolPupilType());
    
    	$request = $this->getRequest();
    
    	if ('POST' === $request->getMethod()) {
    		$form->bindRequest($request);
    		if ($form->isValid())
    		{
    			School::addPupil($school, $form->getData());
    			 
    			return new RedirectResponse($this->generateUrl('BNSAppSchoolBundle_user_index', array('slug' => $school->getSlug())));
    		}
    	}
    	 
    	throw new NotFoundHttpException();
    }
    
    /**
     * @Route("/{slug}/users/delete-pupil-{id}", name="BNSAppSchoolBundle_users_delete_pupil", options={"expose"=true})
     * @param unknown_type $slug
     */
    public function deletePupilAJAXAction($slug, $id)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    
        $school = $this->getSchoolBySlug($slug);
    
    	$state = School::deletePupil($school, $id);
    
    	return new Response(json_encode($state));
    }
        
    /**
     * Récupérer l'objet de type groupe associé au slug $slug fourni en paramètre; le groupe doit être du type School
     *
     * @param String $slug slug à partir duquel on souhaite identifier et récupérer un objet de type Group
     * @throws NotFoundHttpException si le slug fourni n'est pas reconnu, l'exception NotFoundHttpException
     * @throws Exception si le groupe associé au slug fourni n'est pas une école alors une exception est levé
     * @return Group est un objet du type Group qui correspond à l'objet que l'on a voulu récupérer à partir du slug
     */
    private function getSchoolBySlug($slug)
    {
    	$group = GroupQuery::create()
	    	->joinWith('GroupType')
	    	->joinWith('GroupType.GroupTypeI18n')
    	->findOneBySlug($slug);
    	
    	if (null == $group) {
    		throw new NotFoundHttpException('The group with the slug ' . $slug . ' does not exist!');
    	}
    	
    	if ('SCHOOL' != $group->getGroupType()->getType()) {
    		throw new Exception('The group must be a school!');
    	}
    	
    	return $group;
    }
}

<?php

namespace BNS\App\SchoolBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

use BNS\App\CoreBundle\Form\Type\CustomGroupType;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\SchoolBundle\Form\Type\EmbeddedTeacherForm;
use BNS\App\SchoolBundle\Model\School;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;

/**
 * @Route("/admin")
 *
 */
class ClassroomSchoolBackController extends Controller 
{
	/**
	 * 
	 * @Route("/{slug}/classroom", name="BNSAppSchoolBundle_classroom_index")
	 * @Template()
	 * @param String $slug
	 */
	public function classroomIndexAction($slug)
	{
		$school = $this->getSchoolBySlug($slug);
		
		$embeddedForms = array(
			'teacher'	=> new EmbeddedTeacherForm($school),
		);
		$usedAttributes = array(
			'0'	=> 'NAME',
			'1'	=> 'LEVEL',
			'2'	=> 'LEVEL_MORE',
		);
		$customGroupType = new CustomGroupType('CLASSROOM', $usedAttributes, $embeddedForms);
		$form = $this->createForm($customGroupType);
		
		$request = $this->getRequest();
		if ('POST' === $request->getMethod()) {
			$form->bindRequest($request);
			if ($form->isValid())
			{
				$customGroupType->save($form->getData());
				
				return new RedirectResponse($this->generateUrl('BNSAppSchoolBundle_classroom_index', array('slug' => $school->getSlug())));
			}
		}
		
		return array(
			'school' => $school,
			'form' => $form->createView(),
			'classrooms' => $school->getGroupChilds('CLASSROOM'),
		);
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
	
		if (null == $group)
		{
			throw new NotFoundHttpException('The group with the slug ' . $slug . ' does not exist!');
		}
	
		if ('SCHOOL' != $group->getGroupType()->getType())
		{
			throw new Exception('The group must be a school!');
		}
	
		return $group;
	}
}
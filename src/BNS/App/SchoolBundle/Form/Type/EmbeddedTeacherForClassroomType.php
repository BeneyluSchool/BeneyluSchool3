<?php

namespace BNS\App\SchoolBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupTypeDataChoicePeer;

use BNS\App\CoreBundle\Model\GroupTypeDataChoiceI18nPeer;

use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use BNS\App\CoreBundle\Model\Group;

class EmbeddedTeacherForClassroomType extends AbstractType
{
	private $school;
	
	public function __construct(Group $school)
	{
		$this->school = $school;
	}
	
	public function buildForm(FormBuilderInterface $builder, array $options)
	{		
		$builder->add('teachers', 'choice', array(
			'choices' 		=> $this->getTeachers(),
			'required'		=> true,
			'label'			=> 'Enseignant',
		));
	}
	
	public function getName()
	{
		return 'school_teacher';
	}
	
	private function getTeachers()
	{
		$teachers = $this->school->getTeachers();
		$teacherArray = array();
		
		foreach ($teachers as $teacher)
		{
			$teacherArray[$teacher->getId()] = $teacher->getFirstName().' '.$teacher->getLastName();
		}
		
		return $teacherArray;
	}
}
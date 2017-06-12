<?php

namespace BNS\App\RegistrationBundle\Form\Model;

use BNS\App\RegistrationBundle\Model\SchoolInformation;
use BNS\App\CoreBundle\Classroom\BNSClassroomManager;
use BNS\App\CoreBundle\Model\User;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ClassRoomFormModel
{
	/*
	 * Used by form type
	 */
	
	/**
	 * @var int 
	 */
	public $school_information_id;
	
	/**
	 * @var string 
	 */
	public $label;
	
	/**
	 * @var array 
	 */
	public $levels;
	
	/*
	 * Attributes
	 */
	private $classRoom;
	
	/**
	 * NB: parameters are injected by the controller from the container
	 * 
	 * @param \BNS\App\CoreBundle\Group\BNSClassroomManager	$classRoomManager
	 * @param int											$domainId
	 * @param int											$schoolId
	 * @param BNS\App\CoreBundle\Model\User					$user
	 * 
	 * @return BNS\App\CoreBundle\Group 
	 */
	public function save(BNSClassroomManager $classRoomManager, $domainId, $schoolId, User $teacher, SchoolInformation $schoolInfo, $currentYear)
	{
		// Creating classroom
		$this->classRoom = $classRoomManager->createClassroom(array(
			'domain_id'			=> $domainId,
			'label'				=> $this->label,
			'group_parent_id'	=> $schoolId,
			'attributes'		=> array(
				'LEVEL'        => $this->levels,
                'CURRENT_YEAR' => $currentYear
			)
		));
		
		$classRoomManager->setClassroom($this->classRoom);
		
		// Assign the new teacher to his classroom
		$classRoomManager->assignTeacher($teacher);
		
		// Send confirmation e-mail
		$classRoomManager->sendConfirmation($teacher, $schoolInfo);
		
		return $this->classRoom;
	}
}
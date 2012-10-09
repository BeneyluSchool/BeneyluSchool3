<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Model\Group;

class EditClassroomFormModel
{
    public $classroom;
	
	public $avatarId;
	
	public $name;
	
	public $level;
	
	public $description;
	
	public function __construct(Group $classroom)
	{
		$this->classroom = $classroom;
		$this->avatarId = $this->classroom->getAttribute('AVATAR_ID');
		$this->name = $this->classroom->getLabel();
		$this->level = $this->classroom->getAttribute('LEVEL');
		$this->description = $this->classroom->getAttribute('DESCRIPTION');
		$this->home_message = $this->classroom->getAttribute('HOME_MESSAGE');
	}
	
    public function save()
    {
		$this->classroom->setLabel($this->name);
		if ($this->avatarId != null) {
			$this->classroom->setAttribute('AVATAR_ID', $this->avatarId);
		}
		$this->classroom->setAttribute('LEVEL', $this->level);
		$this->classroom->setAttribute('DESCRIPTION', $this->description);
		$this->classroom->setAttribute('HOME_MESSAGE', $this->home_message);
		// Finally
		$this->classroom->save();
	}
}
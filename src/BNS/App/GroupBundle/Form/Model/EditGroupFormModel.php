<?php

namespace BNS\App\GroupBundle\Form\Model;

use BNS\App\CoreBundle\Model\Group;

class EditGroupFormModel
{
    public $classroom;	
	public function __construct(Group $classroom)
	{
		$this->classroom = $classroom;
		$this->home_message = $this->classroom->getAttribute('HOME_MESSAGE');
	}
	
    public function save()
    {
		$this->classroom->setAttribute('HOME_MESSAGE', $this->home_message);
	}
}
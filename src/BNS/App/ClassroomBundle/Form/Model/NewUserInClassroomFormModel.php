<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;

class NewUserInClassroomFormModel
{
    public $first_name;

    public $last_name;

    public $gender;
	
    public $birthday;
	
    public $email;
	
	public $user = null;

    public function save()
    {
		$values = array(
			'first_name'	=> $this->first_name,
			'last_name'		=> $this->last_name,
			'birthday'		=> $this->birthday,
			'gender'		=> $this->gender,
			'lang'			=> BNSAccess::getLocale()
		);
		
		if (null != $this->email)
		{
			$values['email'] = $this->email;
		}
		
		$this->user = BNSAccess::getContainer()->get('bns.user_manager')->createUser($values,false);
	}
	
	public function getObjectUser()
	{
		return $this->user;
	}
}
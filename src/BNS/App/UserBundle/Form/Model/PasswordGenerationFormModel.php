<?php

namespace BNS\App\UserBundle\Form\Model;

use BNS\App\CoreBundle\User\BNSUserManager;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class PasswordGenerationFormModel
{
	/*
	 * Used by form type
	 */
	
	/**
	 * @var string 
	 */
	public $password;
	
	/**
	 * NB: parameters are injected by the controller from the container
	 * 
	 * @param \BNS\App\CoreBundle\User\BNSUserManager $userManager
	 */
	public function save(BNSUserManager $userManager)
	{
		$userManager->setPassword($this->password);
	}
}
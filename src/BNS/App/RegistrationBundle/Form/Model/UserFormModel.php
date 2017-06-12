<?php

namespace BNS\App\RegistrationBundle\Form\Model;

use Symfony\Component\Validator\ExecutionContext;

use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class UserFormModel
{
	/*
	 * Used by form type
	 */
	
	/**
	 * @var string 
	 */
	public $first_name;
	
	/**
	 * @var string 
	 */
	public $last_name;
	
	/**
	 * @var string 
	 */
	public $email;
	
	/*
	 * Attributes
	 */
	private $user;
	
	
	/**
	 * NB: parameters are injected by the controller from the container
	 * 
	 * @param \BNS\App\CoreBundle\User\BNSUserManager $userManager
	 * 
	 * @return \BNS\App\CoreBundle\Model\User
	 */
	public function save(BNSUserManager $userManager)
	{
		$this->user = $userManager->createUser(array(
			'first_name'    => $this->first_name,
			'last_name'		=> $this->last_name,
			'email'			=> $this->email,
			'lang'			=> 'fr'
		),true);
		
		$userManager->flagChangePassword($this->user);
		
		return $this->user;
	}
	
	/**
	 * Constraint validation
	 */
	public function isEmailUnique($context)
	{
		if (null != $this->email && '' != $this->email && null != BNSAccess::getContainer()->get('bns.user_manager')->getUserByEmail($this->email)) {
			$context->addViolationAt('email', "L'adresse e-mail renseignée est déjà utilisée. Veuillez en saisir une autre", array(), null);
		}
	}
}
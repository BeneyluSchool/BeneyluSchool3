<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;

use Symfony\Component\Validator\ExecutionContext;

class NewUserInClassroomFormModel
{
    public $first_name;

    public $last_name;

    public $gender;

    public $birthday;

    public $email;

	public $user = null;

	private $isTeacher;

	public function __construct($isTeacher = false, $withBirthdate = true)
	{
		$this->isTeacher = $isTeacher;
        $this->withBirthdate = $withBirthdate;
	}

    public function save()
    {
		$values = array(
			'first_name'	=> $this->first_name,
			'last_name'		=> $this->last_name,
			'gender'		=> $this->gender,
			'lang'			=> BNSAccess::getLocale(),
			'email'			=> $this->email
		);
        if($this->withBirthdate)
        {
            $values['birthday'] = $this->birthday;
        }

		$this->user = BNSAccess::getContainer()->get('bns.user_manager')->createUser($values, isset($this->email) && $this->email != "" ? true : false);
	}

	public function getObjectUser()
	{
		return $this->user;
	}

	/**
	 * Validation constraint
	 *
	 * @return array<String>
	 */
	public static function getGenders()
	{
		return array('M', 'F');
	}

	/**
	 * Constraint validation
	 */
	public function isEmailAlreadyInUse($context)
	{
		if ($this->isTeacher) {
			if (null == $this->email || '' == $this->email) {
				return $context->buildviolation('EMAIL_EMPTY')
                    ->atPath('email')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
			}

			$emailUser = BNSAccess::getContainer()->get('bns.user_manager')->getUserByEmail($this->email);
			if (null != $emailUser) {
				return $context->buildviolation('EMAIL_ALREADY_USED_CHOOSE_ANOTHER')
                    ->atPath('email')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
			}
		}
	}
}

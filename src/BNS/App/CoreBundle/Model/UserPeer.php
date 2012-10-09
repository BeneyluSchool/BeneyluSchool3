<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseUserPeer;

/**
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class UserPeer extends BaseUserPeer
{
	public static function createUser($values)
	{
		$user = new User();
		$user->setId($values['user_id']);
		$user->setLogin($values['username']);
		$user->setFirstName($values['first_name']);
		$user->setLastName($values['last_name']);
		$user->setGender(isset($values['gender'])? $values['gender'] : (0 === rand(0, 1)? self::GENDER_M : self::GENDER_F));
		
		if ($values['email'] != '') {
			$user->setEmail($values['email']);
		}
		
		$user->setLang($values['lang']);
		$user->setIsExpert(false);
		
		if (isset($values['birthday'])) {
			$user->setBirthday($values['birthday']);
		}
		else {
			$user->setBirthday(null);
		}
		
		$user->createProfile();
		$user->save();
		$user->createResourceLabelRoot();
		
		return $user;
	}
}
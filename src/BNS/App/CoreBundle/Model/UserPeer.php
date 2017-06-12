<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseUserPeer;

/**
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class UserPeer extends BaseUserPeer
{
	public static function createUser($values,$withExtraDatas = true)
	{
		$user = new User();
		$user->setId($values['user_id']);
		$user->setLogin($values['username']);
		$user->setFirstName($values['first_name']);
		$user->setLastName($values['last_name']);
		$user->setGender(isset($values['gender'])? $values['gender'] : self::GENDER_M);
		
		if(isset($values['email'])){
			if ($values['email'] != '') {
				$user->setEmail($values['email']);
			}
		}
		
		$user->setLang($values['lang']);
		$user->setIsExpert(false);
		
		if (isset($values['birthday'])) {
			$user->setBirthday($values['birthday']);
		}
		else {
			$user->setBirthday(null);
		}

        if(isset($values['email_validated']))
        {
            $user->setEmailValidated($values['email_validated']);
        }

        $user->createProfile();

        $user->setSlug('utilisateur-' . $values['user_id']);

        if(isset($values['import_id']))
        {
            $user->setImportId($values['import_id']);
        }

        if(isset($values['aaf_id']))
        {
            $user->setAafId($values['aaf_id']);
        }

        if(isset($values['aaf_academy']))
        {
            $user->setAafAcademy($values['aaf_academy']);
        }

        if(isset($values['aaf_level']))
        {
            $user->setAafLevel($values['aaf_level']);
        }

        if(isset($values['aaf_cycle']))
        {
            $user->setAafCycle($values['aaf_cycle']);
        }

        if(isset($values['high_role_id']))
        {
            $user->setHighRoleId($values['high_role_id']);
        }

        if(isset($values['country']))
        {
            $user->setCountry($values['country']);
        }

		$user->save();
		
		if($withExtraDatas){
			$user->createResourceLabelRoot();
		}
		return $user;
	}
}
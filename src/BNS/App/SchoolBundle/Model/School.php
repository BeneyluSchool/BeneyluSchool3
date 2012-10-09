<?php

namespace BNS\App\SchoolBundle\Model;

use BNS\App\CoreBundle\Model\GroupTypePeer;

use BNS\App\CoreBundle\Model\User;


use BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\GroupData;
use BNS\App\CoreBundle\Model\GroupDataChoice;

class School
{	
	public static function addTeacher(Group $group, $userData)
	{		
		$teacher = $group->createUser($userData);
		
		// TODO : ajouter les droits d'enseignant
	}
	
	
	public static function deleteTeacher(Group $group, $teacherId)
	{
		// TODO : vérifie les droits pour savoir si le professeur enseigne bien dans l'école $this->group, etc.
		// Renvoi un booléen pour indiquer si la suppression s'est bien effectué ou pas
		
		return true;
	}
	
	
	
	
	public static function switchDirector(Group $group, $userId)
	{
		// TODO : changer les droits de l'utilisateur
	
		return true;
	}
	
	
	public static function switchTeacher(Group $group, $userId)
	{
		// TODO : changer les droits de l'utilisateur
	
		return true;
	}
	
	
	public static function addPupil(Group $group, $userData)
	{
		$teacher = $group->createUser($userData);
		
		// TODO : ajouter les droits d'élève
	}
	
	
	public static function deletePupil(Group $group, $pupilId)
	{
		// TODO : vérifie les droits pour savoir si l'élève étudie bien dans l'école $this->group, etc.
		// Renvoi un booléen pour indiquer si la suppression s'est bien effectué ou pas
	
		return true;
	}
}
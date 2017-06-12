<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseAgenda;


/**
 * Skeleton subclass for representing a row from the 'agenda' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class Agenda extends BaseAgenda {

	public static $colorsClass = array(
            'cal-green'         => 'A7C736',
            'cal-red'           => 'E8452E',
            'cal-orange'        => 'F8B93C',
            'cal-pink'          => 'C97378',
            'cal-blue'          => '63B4BB',
            'cal-light-blue'    => '9BD3D5',
            'cal-brown'         => 'FAC53E',
	);
	//Initialise le nom de l'agenda
	/*
	 * @param $name : Nom du groupe pour l'instant
	 */
	public function initAgendaName($name){
		//TODO prendre en compte la langue par d�faut de la classe
		$this->setTitle($name);
	}

	public function saveColorClassFromColorHex($colorHex)
	{
		$colorClass = null;
		foreach (self::$colorsClass as $key => $value)
		{
			if ($value == ($colorHex))
			{
				$colorClass = $key;
				break;
			}
		}

		$this->setColorClass($colorClass);

		$this->save();
	}

	public function getColor()
	{
		return isset(self::$colorsClass[$this->getColorClass()])
			? '#'.self::$colorsClass[$this->getColorClass()]
			: null
		;
	}

} // Agenda

<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseAgendaPeer;


/**
 * Skeleton subclass for performing query and update operations on the 'agenda' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class AgendaPeer extends BaseAgendaPeer
{
	/*
	 * Création de l'agenda, généralement à la création du groupe
	 * @param $params Tableau des valeurs nécessaires
	 * @return Agenda
	 */
	public static function createAgenda($params)
	{
		$agenda = new Agenda();
               
                $agenda->setTitle($params['label']);
		if (isset($params['group_id'])) 
		{
                    $agenda->setGroupId($params['group_id']);
		}
		
		$arrayKeys = array_keys(Agenda::$colorsClass);
		$random_class = $arrayKeys[rand(0, count($arrayKeys) - 1)];
		$agenda->setColorClass($random_class);
		$agenda->initAgendaName($params['label']);
		
		return $agenda->save();
	}
}

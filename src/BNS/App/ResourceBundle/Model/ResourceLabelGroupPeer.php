<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroupPeer;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use \Exception;

/**
 * Skeleton subclass for performing query and update operations on the 'resource_label_group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLabelGroupPeer extends BaseResourceLabelGroupPeer {

	//Initialisation à la création du groupe
	public static function createResourceLabelGroup($params)
	{
		if(!isset($params['group_id']) || !isset($params['label'])){
			throw new Exception('Please provide a group_id and a label');
		}
		$label_group = new ResourceLabelGroup();
		$label_group->setLabel($params['label']);
		$label_group->setGroupId($params['group_id']);
		$label_group->makeRoot();
		$label_group->save();	
		
		//Création du dossier utilisateurs
		$label_group_users = new ResourceLabelGroup();
		$label_group_users->setLabel("Espace utilisateurs");
		$label_group_users->setGroupId($params['group_id']);
		$label_group_users->insertAsLastChildOf($label_group);
		$label_group_users->setIsUserFolder(true);
		$label_group_users->save();	
		
		return $label_group;
	}
	
} // ResourceLabelGroupPeer

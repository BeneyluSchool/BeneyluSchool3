<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceQuery;
use BNS\App\ResourceBundle\BNSResourceManager;
use BNS\App\CoreBundle\Access\BNSAccess;
use \Exception;


/**
 * Skeleton subclass for performing query and update operations on the 'resource' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceQuery extends BaseResourceQuery {

	/*
	 * Fonction principale de récupération de ressources
	 */
	public static function getResources($params = null){
		
		if($params == null)
			throw new Exception("Can't Find resources, please provide params");
		
		//Limite d'affichage
		if(!isset($params['limit']))
			$limit = 10;
		else
			$limit = $params['limit'];
		//Page affichée
		if(!isset($params['page']))
			$page = 0;
		else
			$page = $params['page'];
		
		$query = self::create();
		
		if(isset($params['query']))
			$query->filterByLabel('%' . $params['query'] . '%');
		
		if(isset($params['current_label'])){
			
			if($params['current_label'] != null){

				$current_label = $params['current_label'];

				if(get_class($current_label) == "BNS\App\ResourceBundle\Model\ResourceLabelUser"){
					$query->useResourceLinkUserQuery()->filterByResourceLabelUserId($current_label->getId())->endUse();
				}elseif(get_class($current_label) == "BNS\App\ResourceBundle\Model\ResourceLabelGroup"){
					$query->useResourceLinkGroupQuery()->filterByResourceLabelGroupId($current_label->getId())->endUse();
				}
			}
		}
		
		if(isset($params['filters']))
			if(count($params['filters']) > 0)
				$query->filterByTypeUniqueName($params['filters']);
		
		if(isset($params['favorite_filter']) && isset($params['user_id'])){
			if($params['favorite_filter'] == true)
				$query->useResourceFavoritesQuery()->filterByUserId($params['user_id'])->endUse();
		}
		
		if($params['type'] == 'garbage'){
			//Si corbeille filtre sur l'utilisateur en cours
			$query->filterByUserId(BNSAccess::getUser()->getId());
			$query->filterByStatusDeletion(BNSResourceManager::STATUS_GARBAGED);
		}else{
			$query->filterByStatusDeletion(BNSResourceManager::STATUS_ACTIVE);
		}
		
		if(isset($params['sort'])){
			switch($params['sort']){
				case "chrono":
					$query->orderByCreatedAt("desc");
				break;
				case "alpha":
					$query->orderByLabel("asc");
				break;
			}
		}else{
			//Tri par défaut
			$query->orderByCreatedAt('desc');
		}
		
				
		return $query->paginate($page,$limit);
	}
} // ResourceQuery

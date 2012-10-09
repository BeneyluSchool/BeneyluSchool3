<?php

namespace BNS\App\CoreBundle\Module;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\PermissionPeer;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Model\RankPeer;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des modules / permissions / rangs
 */
class BNSModuleManager
{	

	protected $api;
	protected $rank;
	
	public function __construct($api)
	{
		$this->api = $api;
	}
	
	//////////////    FONCTIONS LIEES AUX MODULES    \\\\\\\\\\\\\\\\\\
	
	/**
	 * Vérification de l'éxistence du module
	 * @param type $unique_name
	 * @return type
	 */
	public function moduleExists($unique_name)
	{
		return $this->findModuleByUniqueName($unique_name) != NULL;
	}
	
	public function findModuleByUniqueName($unique_name)
	{
		return ModuleQuery::create()->findOneByUniqueName($unique_name);
	}
	
	public function findModuleById($moduleId)
	{
		return ModuleQuery::create()->findOneById($moduleId);
	}
	
	/**
	 * Création d'un module avec unique name + label FR
	 * @param type $params
	 * @return type
	 * @throws HttpException
	 */
	public function createModule($params = array())
	{
	    //vérification que nous avons assez d'infos : unique_name , Description au moins FR
		if(
			isset($params['unique_name']) &&
			isset($params['i18n']['fr']['label'])
		){
			
			//on vérifie la non existence en local sur App
			if(!$this->moduleExists($params['unique_name'])){
				$unique_name = $params['unique_name'];
				$is_contextable = $params['is_contextable'];
				$bundle_name = $params['bundle_name'];				
				$values = array(
					'unique_name' => $unique_name,
					'is_contextable' => $is_contextable,
					'bundle_name' => $bundle_name,
				);
				//Vérification de l'éxistence du module coté centrale
				$response = $this->api->send('module_read',array('check' => true,'route' => array('unique_name' =>$params['unique_name'])));
				
				if($response == "404"){
					//Le module n'existe pas sur la centrale on le créé donc
					$response = $this->api->send('module_create',array('values' => $values));
				}else{
					//On fait un appel sans check pour avoir l'id
					$response = $this->api->send('module_read',array('route' => array('unique_name' =>$params['unique_name'])));
				}	
				$values['id'] = $response['id'];
				$values['i18n'] = $params['i18n'];
				if(isset($params['default_parent_rank']))
					$values['default_parent_rank'] = $params['default_parent_rank'];
				if(isset($params['default_pupil_rank']))
					$values['default_pupil_rank'] = $params['default_pupil_rank'];
				if(isset($params['default_other_rank']))
					$values['default_other_rank'] = $params['default_other_rank'];
				//Création en local
				$new_module = ModulePeer::createModule($values);
				return $new_module;
			}else{
				throw new HttpException(500,"Module already exists, please provide an other unique_name");
			}
		}else{
			throw new HttpException(500,"Not enough datas to create module : please provide unique_name and french label");
		}
	}
	
	//////////////    FONCTIONS LIEES AUX PERMISSIONS   \\\\\\\\\\\\\\\\\\
	
	/**
	 * Vérifiction de l'existence d'une permission
	 * @param type $unique_name
	 * @return type
	 */
	public function permissionExists($unique_name)
	{
		return PermissionQuery::create()->findOneByUniqueName($unique_name) != NULL;
	}
	
	/**
	 * Création d'une permission
	 * @param type $params
	 * @return type
	 * @throws HttpException
	 */
	public function createPermission($params)
	{
		//vérification que nous avons assez d'infos : unique_name , Description au moins FR, module_id
		if(
			isset($params['unique_name']) &&
			isset($params['i18n']['fr']['label']) 
			// && isset($params['module_id'])
		){
			
			//on vérifie la non existence en local
			if(!$this->permissionExists($params['unique_name'])){

				$unique_name = $params['unique_name'];
				
				$module_id = isset($params['module_id']) ? $params['module_id'] != "" ? $params['module_id'] : null : null;
				
				$values = array(
					'unique_name' => $unique_name,
					'module_id' => $module_id
				);
				
				$route = array();
				
				//Vérification de l'éxistence de la permission coté centrale
				$response = $this->api->send('permission_read',array('check' => true,'route' => array('unique_name' =>$params['unique_name'])));
				
				if($response == "404"){
					//Le rang n'existe pas sur la centrale on le créé donc
					$response = $this->api->send('module_create_permission',array('values' => $values,'route' => $route));
				}
				
				$values['i18n'] = $params['i18n'];
				
				$new_permission = PermissionPeer::createPermission($values);

				return $new_permission;
			}else{
				throw new HttpException(500,"Permission already exists, please provide an other unique_name");
			}
		}else{
			throw new HttpException(500,"Not enough datas to create permission : please provide unique_name and french label");
		}
	}
	
	//////////////    FONCTIONS LIEES AUX RANGS   \\\\\\\\\\\\\\\\\\
	
	public function getRankByUniqueName($unique_name)
	{
		return  RankQuery::create()->findOneByUniqueName($unique_name);
	}
	
	/**
	 * Vérification de l'existence d'un rang
	 * @param type $unique_name
	 * @return type
	 */
	public function checkRank($unique_name)
	{
		$rank = RankQuery::create()->findOneByUniqueName($unique_name);
		if($rank){
			$this->setRank($rank);
			return true;
		}else{
			throw new HttpException(500,"The rank " . $unique_name ." does not exist");
		}
	}
	
	/**
	 * Settage du rank
	 * @param type $rank
	 */
	public function setRank($rank)
	{
		$this->rank = $rank;
	}
	
	/**
	 * Get du rank en cours
	 * @return type
	 * @throws HttpException
	 */
	public function getRank()
	{
		if(isset($this->rank)){
			return $this->rank;
		}else{
			throw new HttpException(500,"The rank is not set");
		}
	}
	
	/**
	 * Création d'un rang
	 * @param type $params
	 * @return type
	 * @throws HttpException
	 */
	public function createRank($params)
	{
		//vérification que nous avons assez d'infos : unique_name , Description au moins FR, module_id
		if(
			isset($params['unique_name']) &&
			isset($params['i18n']['fr']['label']) &&
			isset($params['module_id'])	
		){
			$unique_name = $params['unique_name'];
			$module_id = $params['module_id'];

			$values = array(
				'unique_name' => $unique_name,
				'module_id' => $module_id
			);

			$route = array(
				'id' => $module_id
			);
			
			//Vérification de l'éxistence du rang coté centrale
			$response = $this->api->send('rank_read',array('check' => true,'route' => array('unique_name' =>$params['unique_name'])));
				
			if($response == "404"){
				//Le rang n'existe pas sur la centrale on le créé donc
				$response = $this->api->send('module_create_rank',array('values' => $values,'route' => array('id' => $module_id )));
			}
			$values['i18n'] = $params['i18n'];
			//CREATION EN LOCAL
			$new_rank = RankPeer::createRank($values);

			if(isset($params['permissions'])){
				//ASSIGNATION DES PERMISSIONS
				if(is_array($params['permissions'])){
					foreach($params['permissions'] as $permission_unique_name){
						$this->addRankPermission($unique_name,$permission_unique_name);
					}
				}
			}
			return $new_rank;
		}else{
			throw new HttpException(500,"Not enough datas to create rank : please provide unique_name and french label and module_id");
		}
	}
	
	/**
	 * Récupération depuis la centrale des permissions d'un rang
	 * @param type $rankUniqueName
	 */
	public function getRankPermissions($rankUniqueName)
	{
		$this->checkRank($rankUniqueName);
		$rank = $this->getRank();
		$permissions = $this->api->send('module_rank_get_permissions',
			array('route' => array('module_id' => $rank->getModuleId(),'rank_unique_name' => $rank->getUniqueName()))
		);
		$localPermissions = array();
		foreach($permissions as $permission){
			$localPermissions[] = $permission['unique_name'];
		}
		return PermissionQuery::create()->findByUniqueName($localPermissions);
	}
	
	public function deleteRankPermission($rankUniqueName,$permissionUniqueName)
	{
		$this->checkRank($rankUniqueName);
		$this->api->send('module_rank_delete_permission',
			array('route' => array('permission_unique_name' => $permissionUniqueName,'rank_unique_name' => $rankUniqueName))
		);	
	}
	
	public function addRankPermission($rankUniqueName,$permissionUniqueName)
	{
		$this->checkRank($rankUniqueName);
		$this->api->send('module_rank_add_permission',
			array(
				'route' => array('module_id' => $this->getRank()->getModuleId(),'rank_unique_name' => $rankUniqueName),
				'values' => array('unique_name' => $permissionUniqueName)
			)
		);	
	}
}
<?php

namespace BNS\App\CoreBundle\Module;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\Permission;
use BNS\App\CoreBundle\Model\PermissionPeer;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\Rank;
use BNS\App\CoreBundle\Model\RankDefaultQuery;
use BNS\App\CoreBundle\Model\RankPeer;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\InstallBundle\Exception\AuthModuleAlreadyInstalled;
use BNS\App\InstallBundle\Exception\AuthPermissionAlreadyInstalled;
use BNS\App\InstallBundle\Exception\AuthRankAlreadyInstalled;
use BNS\App\InstallBundle\Module\ModuleManagerInterface;
use BNS\App\InstallBundle\Process\InstallProcessInterface;
use BNS\App\NotificationBundle\Model\NotificationType;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\StatisticsBundle\Model\Marker;

/**
 * @author Eymeric Taelman
 *
 * Classe permettant la gestion des modules / permissions / rangs
 */
class BNSModuleManager implements ModuleManagerInterface
{

	/**
	 * @var BNSApi
	 */
	protected $api;

	protected $rank;

	public function __construct(BNSApi $api)
	{
		$this->api = $api;
	}

	//////////////    FONCTIONS LIEES AUX MODULES    \\\\\\\\\\\\\\\\\\

	/**
	 * Vérification de l'éxistence du module
	 * @param string $unique_name
	 * @return bool
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
	 * @param InstallProcessInterface $process
	 *
	 * @return boolean
	 */
	public function isInstalled(InstallProcessInterface $process)
	{
		// Côté AUTH
		try {
			$response = $this->api->send('module_read', array(
				'route' => array(
					'unique_name' => $process->getUniqueName()
				)
			));
		}
		catch (NotFoundHttpException $e) {
			return false;
		}

		throw new AuthModuleAlreadyInstalled('The bundle "' . $process->getUniqueName() . '" is ALREADY installed in AUTH but NOT in APP !', $response);
	}

    /**
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param bool $isEnabled
     *
     * @return \BNS\App\CoreBundle\Model\Module
     */
    public function createFromProcess(InstallProcessInterface $process, $isEnabled)
    {
        // Création côté AUTH seulement si le module n'existe pas déjà
        if (null == $process->getId()) {
            $response = $this->api->send('module_create', array(
                'values' => $process->getModuleInstallData()
            ));

            $process->setId($response['id']);
        }

        // Création côté APP
        $module = new Module();
        $module->setId($process->getId());
        $module->setUniqueName($process->getUniqueName());
        $module->setIsContextable($process->isContextable());
        $module->setBundleName($process->getBundleName());
        $module->setType($process->getType());

        if ($isEnabled) {
            $module->setIsEnabled(true);
        }

        $this->setDefaultRanks($process, $module);

        $module->save();

       $this->setDefaultBetaRanks($process, $module);

        return $module;
    }

	//////////////    FONCTIONS LIEES AUX PERMISSIONS   \\\\\\\\\\\\\\\\\\

	/**
	 * @param string $permissionName
	 *
	 * @return boolean
	 */
	public function isInstalledPermission($permissionName)
	{
		try {
			$response = $this->api->send('permission_read', array(
				'route' => array(
					'unique_name' => $permissionName
				)
			));
		}
		catch (NotFoundHttpException $e) {
			return false;
		}

		throw new AuthPermissionAlreadyInstalled('The permission "' . $permissionName . '" is ALREADY installed in AUTH but NOT in APP !', $response);
	}

	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 * @param string  $permissionName
	 * @param array   $permissionData
	 * @param boolean $alreadyInstalled
	 *
	 * @return \BNS\App\CoreBundle\Model\Permission
	 */
	public function createPermissionFromProcess(InstallProcessInterface $process, $permissionName, array $permissionData, $alreadyInstalled = false)
	{
		// Création côté AUTH seulement si la permission n'existe pas déjà
		if (!$alreadyInstalled) {
			$this->api->send('module_create_permission', array(
				'values' => array(
					'unique_name' => $permissionName,
					'module_id'	  => $process->getId()
				)
			));
		}

		// Création côté APP
		$permission = new Permission();
		$permission->setUniqueName($permissionName);
		$permission->setModuleId($process->getId());

		// Finally
		$permission->save();

		return $permission;
	}

	//////////////    FONCTIONS LIEES AUX RANGS   \\\\\\\\\\\\\\\\\\

	/**
	 * @param string $rankName
	 *
	 * @return boolean
	 */
	public function isInstalledRank($rankName)
	{
		try {
			$response = $this->api->send('rank_read', array(
				'route' => array(
					'unique_name' => $rankName
				)
			));
		}
		catch (NotFoundHttpException $e) {
			return false;
		}

		throw new AuthRankAlreadyInstalled('The rank "' . $rankName . '" is ALREADY installed in AUTH but NOT in APP !', $response);
	}

	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 * @param string  $rankName
	 * @param array   $rankData
	 * @param boolean $alreadyInstalled
	 *
	 * @return \BNS\App\CoreBundle\Model\Rank
	 */
	public function createRankFromProcess(InstallProcessInterface $process, $rankName, array $rankData, $alreadyInstalled = false)
	{
		// Création côté AUTH seulement si le rank n'existe pas déjà
		if (!$alreadyInstalled) {
			$this->api->send('module_create_rank', array(
				'values' => array(
					'unique_name' => $rankName,
					'module_id'	  => $process->getId()
				),
				'route' => array(
					'id' => $process->getId()
				)
			));
		}

		// Création côté APP
		$rank = new Rank();
		$rank->setUniqueName($rankName);
		$rank->setModuleId($process->getId());
		$rank->save();

		// Finally
		$rank->save();

		// Add permissions to the rank
		$permissions = array();
		if ($alreadyInstalled) {
			$response = $this->api->send('module_rank_get_permissions', array(
				'route' => array(
					'module_id' => $process->getId(),
					'rank_unique_name' => $rankName
				)
			));

			foreach ($response as $permission) {
				$permissions[] = $permission['unique_name'];
			}
		}

		foreach ($rankData['permissions'] as $permissionName) {
			if (in_array($permissionName, $permissions)) {
				continue;
			}

			$this->api->send('module_rank_add_permission', array(
				'route' => array(
					'module_id' => $process->getId(),
					'rank_unique_name' => $rankName
				),
				'values' => array(
					'unique_name' => $permissionName
				)
			));
		}

		return $rank;
	}

    /**
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param string $notificationTypeName
     * @param array $notificationTypeData
     *
     * @return \BNS\App\NotificationBundle\Model\NotificationType
     */
    public function createNotificationTypeFromProcess(InstallProcessInterface $process, $notificationTypeName, array $notificationTypeData)
    {
        $notificationType = new NotificationType();
        $notificationType->setModuleUniqueName($process->getUniqueName());
        $notificationType->setUniqueName($notificationTypeName);

        if (isset($notificationTypeData['disabled_engine']) && null != $notificationTypeData['disabled_engine']) {
            $notificationType->setDisabledEngine($notificationTypeData['disabled_engine']);
        }

        if (isset($notificationTypeData['is_correction'])) {
            $notificationType->setIsCorrection($notificationTypeData['is_correction']);
        }

        $notificationType->save();

        return $notificationType;
    }

    /**
     * @param string $unique_name
     *
     * @return Rank
     */
	public function getRankByUniqueName($unique_name)
	{
		return  RankQuery::create()->findOneByUniqueName($unique_name);
	}

	/**
	 * Vérification de l'existence d'un rang
	 * @param string $unique_name
	 * @return bool
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
	 * @param Rank $rank
	 */
	public function setRank($rank)
	{
		$this->rank = $rank;
	}

	/**
	 * Get du rank en cours
	 * @return Rank
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
	 * @param array $params
	 * @return Rank
	 * @throws HttpException
	 */
	public function createRank($params)
	{
		//vérification que nous avons assez d'infos : unique_name , module_id
		if(
			isset($params['unique_name']) &&
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
	 * @param string $rankUniqueName
	 * @return array|\PropelObjectCollection|Permission[]
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
		try {
			$this->api->send('module_rank_get_permission', [
				'route' => ['permission_unique_name' => $permissionUniqueName, 'rank_unique_name' => $rankUniqueName],
			]);

			return; // rankPermission already exist, abort.
		} catch (NotFoundHttpException $e) {
			// does not exist, proceed to create it.
		}
		$this->api->send('module_rank_add_permission',
			array(
				'route' => array('module_id' => $this->getRank()->getModuleId(),'rank_unique_name' => $rankUniqueName),
				'values' => array('unique_name' => $permissionUniqueName)
			)
		);
	}

	/**
	 * Vérifiction de l'existence d'une permission
	 * @param string $unique_name
	 * @return bool
	 */
	public function permissionExists($unique_name)
	{
		return PermissionQuery::create()->findOneByUniqueName($unique_name) != NULL;
	}

	/**
	 * Création d'une permission
	 * @param array $params
	 * @return Permission
	 * @throws HttpException
	 */
	public function createPermission($params)
	{
		//vérification que nous avons assez d'infos : unique_name , Description au moins FR, module_id
		if(
			isset($params['unique_name'])
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

				$new_permission = PermissionPeer::createPermission($values);

				return $new_permission;
			}else{
				throw new HttpException(500,"Permission already exists, please provide an other unique_name");
			}
		}else{
			throw new HttpException(500,"Not enough datas to create permission : please provide unique_name and french label");
		}
	}

	/**
	 * Création d'un module avec unique name + label FR
	 * @param array $params
	 * @return Module
	 * @throws HttpException
	 */
	public function createModule($params = array())
	{
	    //vérification que nous avons assez d'infos : unique_name , Description au moins FR
		if(
			isset($params['unique_name'])
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
				if(isset($params['default_parent_rank']))
					$values['default_parent_rank'] = $params['default_parent_rank'];
				if(isset($params['default_pupil_rank']))
					$values['default_pupil_rank'] = $params['default_pupil_rank'];
				if(isset($params['default_teacher_rank']))
					$values['default_teacher_rank'] = $params['default_teacher_rank'];
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


    public function createMarkerFromProcess(InstallProcessInterface $process, $markerName, array $markerData)
    {
        $marker = new Marker();
        $marker->setModuleUniqueName($process->getUniqueName());
        $marker->setUniqueName($markerName);

        if (isset($markerData['description']) && null != $markerData['description']) {
            $marker->setDescription($markerData['description']);
        }

        $marker->save();

        return $marker;
    }

    /**
     * @param InstallProcessInterface $process
     * @return bool|void*
     */
    public function getInvalidDefaultRank(InstallProcessInterface $process)
    {
        $moduleUniqueName = $process->getUniqueName();
        $module = ModuleQuery::create()->filterByUniqueName($moduleUniqueName)->findOne();
        if (!$module) {
            return [
                'parent',
                'pupil',
                'teacher',
                'other'
            ];
        }

        $invalidDefaultRanks = [];
        $defaultRanks = $process->getDefaultRanks();

        if ((!isset($defaultRanks['parent']) && null != $module->getDefaultParentRank()) ||
            isset($defaultRanks['parent']) && $defaultRanks['parent'] !== $module->getDefaultParentRank()
        ) {
            $invalidDefaultRanks[] = 'parent';
        }
        if ((!isset($defaultRanks['pupil']) && null != $module->getDefaultPupilRank()) ||
            isset($defaultRanks['pupil']) && $defaultRanks['pupil'] !== $module->getDefaultPupilRank()
        ) {
            $invalidDefaultRanks[] = 'pupil';
        }
        if ((!isset($defaultRanks['teacher']) && null != $module->getDefaultTeacherRank()) ||
            isset($defaultRanks['teacher']) && $defaultRanks['teacher'] !== $module->getDefaultTeacherRank()
        ) {
            $invalidDefaultRanks[] = 'teacher';
        }
        if ((!isset($defaultRanks['other']) && null != $module->getDefaultOtherRank()) ||
            isset($defaultRanks['other']) && $defaultRanks['other'] !== $module->getDefaultotherRank()
        ) {
            $invalidDefaultRanks[] = 'other';
        }

        return $invalidDefaultRanks;
    }

    /**
     * @param InstallProcessInterface $process
     * @param Module $module
     * @return Module
     */
    public function setDefaultRanks(InstallProcessInterface $process, Module $module)
    {
        $defaultRanks = $process->getDefaultRanks();
        if (isset($defaultRanks['parent'])) {
            $module->setDefaultParentRank($defaultRanks['parent']);
        }
        if (isset($defaultRanks['pupil'])) {
            $module->setDefaultPupilRank($defaultRanks['pupil']);
        }
        if (isset($defaultRanks['teacher'])) {
            $module->setDefaultTeacherRank($defaultRanks['teacher']);
        }
        if (isset($defaultRanks['other'])) {
            $module->setDefaultOtherRank($defaultRanks['other']);
        }
    }

    public function setDefaultBetaRanks(InstallProcessInterface $process, Module $module)
    {
        $defaultRanks = $process->getDefaultRanks();

        if (isset($defaultRanks['beta'])) {
            foreach ($defaultRanks['beta'] as $groupType => $roles) {
                foreach ($roles as $role => $rank) {
                    $defaultRank = RankDefaultQuery::create()
                        ->filterByModule($module)
                        ->filterByBeta(true)
                        ->filterByGroupType($groupType)
                        ->filterByRole($role)
                        ->findOneOrCreate()
                    ;
                    $defaultRank->setRankDefault($rank);
                    $defaultRank->save();
                }
            }
        }
    }

}

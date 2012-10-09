<?php

namespace BNS\App\CoreBundle\Model;

use Symfony\Component\Security\Acl\Exception\Exception;
use \Criteria;

use BNS\App\CoreBundle\Model\om\BaseGroup;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupData;
use BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class Group extends BaseGroup 
{
	private $users;
	private $subgroups;
	private $subgroupsRole;
	private $subGroupsArray;
	
	/**
	 * @var Group groupe parent du groupe courant
	 */
	private $parent = null;
	
    /**
	 * @var array<Module> type group's modules
	 */
    private $activatedModules;
	
	/*
	 *  FONCTIONS ESSENTIELLES
	 */
	
	
	/**
	 * 
	 * Affiche le label pour affichage
	 * @return string Label à afficher
	 * 
	 */
	public function __toString()
	{
		return $this->getLabel();
	}
	
	/**
	 * Renvoie le type d'objet
	 */
	public function getClassName()
	{
		return 'Group';
	}
	
	/*
	 *  FONCTIONS SPECIFIQUES
	 */
	
	/**
	 * On récupère tous les GroupData du groupe $this;
	 * le label (getLabel()) et le(s) valeur(s) (getValue()) sont directements accessibles
	 * sur les objets GroupData
	 *
	 * @return    $data_value tableau qui contient tous les objets de type GroupData associés
	 * 			  au group $this;
	 * 					      
	 */
	public function getFullGroupDatas() 
	{
		if (!isset($this->collGroupDatas)) {		
			$groupDatas = GroupDataQuery::create()
				->joinWith('GroupTypeData')
				->joinWith('GroupTypeData.GroupTypeDataTemplate')
				->joinWith('GroupTypeDataTemplate.GroupTypeDataTemplateI18n')
				->add(GroupTypeDataTemplateI18nPeer::LANG, BNSAccess::getLocale())
			->findByGroupId($this->getId());
			
			$groupDataIds = array();
			foreach ($groupDatas as $groupData) {
				if ($groupData->getValue() == null) {
					$groupDataIds[] = $groupData->getId();
				}
			}
			
			$groupDataChoices = GroupDataChoiceQuery::create()
				->joinWith('GroupTypeDataChoice')
				->joinWith('GroupTypeDataChoice.GroupTypeDataChoiceI18n')
				->add(GroupTypeDataChoiceI18nPeer::LANG, BNSAccess::getLocale())
				->add(GroupDataChoicePeer::GROUP_DATA_ID, $groupDataIds, \Criteria::IN)
			->find();
			
			foreach ($groupDataChoices as $groupDataChoice) {
				foreach ($groupDatas as $groupData) {
					if ($groupData->getId() == $groupDataChoice->getGroupDataId()) {
						$groupData->addGroupDataChoice($groupDataChoice);
						break;
					}
				}
			}
			
			$this->collGroupDatas = $groupDatas;
		}
		
		return $this->collGroupDatas;
	}
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function getGroupTypeDataTemplateByUniqueName($uniqueName)
	{
		return $groupe_type_data_template = GroupTypeDataTemplateQuery::create()
			->filterByUniqueName($uniqueName)
			->joinGroupTypeData()
			->where('GroupTypeData.GroupTypeId = ?',$this->getGroupTypeId()) 
		->findOne();
	}
	
	/**
	 * 
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function getGroupDataByUniqueName($uniqueName)
	{
		return GroupDataQuery::create()
			->filterByGroupId($this->getId())
			->useGroupTypeDataQuery()
				->filterByGroupTypeDataTemplateUniqueName($uniqueName)
			->endUse()
		->findOne();
	}
			
	
	/////////    METHODES LIEES AUX ATTRIBUTS    \\\\\\\\\\\\
	
	/**
	 * @param type $uniqueName
	 * @param type $value
	 * 
	 * @throws Exception
	 */
	public function createAttribute($uniqueName,$value)
	{
		if ($this->hasAttribute($uniqueName)) {
			$groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
			$group_data = new GroupData();
			$group_data->setGroupTypeDataId($this->getGroupType()->getGroupTypeDataByUniqueName($uniqueName)->getId());
			$group_data->setGroupId($this->getId());
			
			switch ($groupe_type_data_template->getType()) {
				case "SINGLE":
				case "TEXT":
					$group_data->setValue($value);
					$group_data->save();
				break;
				case "ONE_CHOICE":
					$group_data->save();
					$group_data->clearChoices();
					$group_data->addChoice($value);
				break;
				case "MULTIPLE_CHOICE":
					$group_data->save();
					$group_data->clearChoices();
					$group_data->addChoices($value);
				break;
			}
		}
		else {
			throw new Exception("You cant create this attribute [" . $uniqueName . "] to this group");
		}
	}
	
	/**
	 * @param type $uniqueName
	 * @param type $value
	 */
	public function setAttribute($uniqueName,$value)
	{
		if ($this->hasAttributeAndValue($uniqueName)) {
			$this->updateAttribute($uniqueName,$value);
		}
		else {
			$this->createAttribute($uniqueName,$value);
		}
	}
	
	/**
	 * @param type $uniqueName
	 * @param mixed $value
	 *  - array if multiple value
	 */
	public function updateAttribute($uniqueName, $value)
	{
		$groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
		$group_data = $this->getGroupDataByUniqueName($uniqueName);
		
		switch ($groupe_type_data_template->getType()) {
			case "SINGLE":
			case "TEXT":
				$group_data->setValue($value);
				$group_data->save();
			break;
			case "ONE_CHOICE":
				$group_data->clearChoices();
				$group_data->addChoice($value);
			break;
			case "MULTIPLE_CHOICE":
				$group_data->clearChoices();
				$group_data->addChoices($value);
			break;
		}
	}
	
	/**
	 * @return type
	 */
	public function getAttributes()
	{
		$query = GroupTypeDataQuery::create();
		$query
			->filterByGroupTypeId($this->getGroupTypeId())
			->leftJoin('GroupTypeDataTemplate')
			->with('GroupTypeDataTemplate')
			->useGroupTypeDataTemplateQuery()
				->joinWithI18n(BNSAccess::getLocale())
			->endUse()
		;
			
		return $query->find();
	}
	
	/**
	 * Méthode qui permet de vérifier si le group $this possède un attribut $uniqueName
	 * 
	 * @param String $uniqueName est le nom unique de l'attribut dont on souhaite vérifier la présence
	 * 
	 * @return 	un objet du type GroupTypeData associé à $uniqueName, si le groupe $this ne possède pas l'attribut alors 
	 * 			retourne null
	 */
	public function hasAttribute($uniqueName)
	{
		return $this->getGroupType()->hasAttribute($uniqueName);
	}
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function hasAttributeAndValue($uniqueName)
	{
		return $this->getGroupDataByUniqueName($uniqueName) != null;
	}
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function getAttribute($uniqueName)
	{
		if (!isset($this->$uniqueName)) {
			if ($this->hasAttribute($uniqueName)) {
				$groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
				$defaultValue = $groupe_type_data_template->getDefaultValue();

				switch ($groupe_type_data_template->getType()) {
					case "SINGLE": 
					case "TEXT":
						$groupData = GroupDataQuery::create()
							->filterByGroupId($this->getId())
							->useGroupTypeDataQuery()
								->filterByGroupTypeDataTemplateUniqueName($uniqueName)
							->endUse()
							->findOne
							();
						$value = $groupData ? $groupData->getValue() : null;
						$this->$uniqueName = ($value != "" && $value != null) ? $value : $defaultValue;
					break;
					case "ONE_CHOICE":
						$c = new Criteria();
						$c->add(GroupDataPeer::GROUP_ID,$this->getId());
						$c->addJoin(GroupDataPeer::ID,GroupDataChoicePeer::GROUP_DATA_ID);
						$c->addJoin(GroupDataChoicePeer::GROUP_TYPE_DATA_CHOICE_ID,GroupTypeDataChoicePeer::ID);
						$c->addJoin(GroupDataPeer::GROUP_TYPE_DATA_ID,GroupTypeDataPeer::ID);
						$c->addJoin(GroupTypeDataChoicePeer::ID, GroupTypeDataChoiceI18nPeer::ID);
						$c->add(GroupTypeDataPeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME,$uniqueName);
						$value = GroupTypeDataChoicePeer::doSelectOne($c);
						$this->$uniqueName = ($value != "" && $value != null) ? $value->getValue() : GroupTypeDataChoiceQuery::create()->findOneById($defaultValue)->getValue();
					break;
					case "MULTIPLE_CHOICE":
						$c = new Criteria();
						$c->add(GroupTypeDataPeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME,$uniqueName);
						$c->addJoin(GroupTypeDataPeer::ID,GroupDataPeer::GROUP_TYPE_DATA_ID);
						$c->add(GroupDataPeer::GROUP_ID,$this->getId());
						$c->addJoin(GroupDataPeer::ID,GroupDataChoicePeer::GROUP_DATA_ID);
						$c->addJoin(GroupDataChoicePeer::GROUP_TYPE_DATA_CHOICE_ID,GroupTypeDataChoicePeer::ID);
						$c->addJoin(GroupTypeDataChoicePeer::ID, GroupTypeDataChoiceI18nPeer::ID);
						//Pour éviter doublonc, à checker
						$c->setDistinct();
						$choices = GroupTypeDataChoicePeer::doSelect($c);	

						$value = array();
						foreach ($choices as $choice) {
							$value[] = $choice->getValue();
						}
						
						if (count($value) > 0) {
							$this->$uniqueName = $value;
						}
						else {
							$def = GroupTypeDataChoiceQuery::create()->findOneById($defaultValue);
							if ($def) {
								$this->$uniqueName = $def->getValue();
							}
							else {
								$this->$uniqueName = null;
							}
						}
						
						$this->$uniqueName = $value;
					break;
				}	
			}
			else {
				$this->$uniqueName =  null;
			}
		}
		
		return $this->$uniqueName;
	}
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function printAttribute($uniqueName)
	{
		$value = $this->getAttribute($uniqueName);
		if (is_array($value)) {
			$return = "";
			foreach ($value as $v) {
				$return .= $v . ', ';
			}
			
			return substr($return,0,-2);
		}
		
		return $value;
	}
		
	
	///////////////////         METHODES LIEES AUX RESSOURCES       \\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @param type $with_root
	 * 
	 * @return type
	 */
	public function getRessourceLabels($with_root = true)
	{
		if ($with_root) {
			return ResourceLabelGroupQuery::create()->orderByBranch()->filterByGroupId($this->getId())->find();
		}
		
		return ResourceLabelGroupQuery::create()->filterByTreeLevel(array('min' => 1))->findTree($this->getId());
	}

	/**
	 * @return type
	 */
	public function getResourceLabelRoot()
	{
		return ResourceLabelGroupQuery::create()->findRoot($this->getId());
	}
	
	/**
	 * @param type $value
	 */
	public function addResourceSize($value)
	{
		$this->setAttribute("RESOURCE_USED_SIZE",$this->getAttribute("RESOURCE_USED_SIZE") + $value);
	}
	
	/**
	 * @param type $value
	 */
	public function deleteResourceSize($value)
	{
		$this->setAttribute("RESOURCE_USED_SIZE",$this->getAttribute("RESOURCE_USED_SIZE") - $value);
	}
	
	/**
	 * @return type
	 */
	public function getUsers()
	{
		if (!isset($this->users)) {
			throw new \Exception('You didn\'t set users attribute, you can not do get!');
		}
		
		return $this->users;
	}
	
	/**
	 * @param type $users
	 */
	public function setUsers($users)
	{
		$this->users = $users;
	}
	
	public function setSubgroupsRoleWithUsers(array $subgroupsRole)
	{
		$this->subgroupsRole = $subgroupsRole;
	}
	
	public function getSubgroupsRoleWithUsers()
	{
		if (!isset($this->subgroupsRole)) {
			throw new \Exception('You didn\'t set subgroups role attribute, you can not do get!');
		}
		
		return $this->subgroupsRole;
	}
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return mixed
	 */
	public function getGroupDataByGroupTypeDataTemplateUniqueName($uniqueName)
	{
		$groupTypeData = $this->hasAttribute($uniqueName);
		if ($groupTypeData == null) {
			return null;
		}
		
		if (!isset($this->collGroupDatas)) {
			$this->getFullGroupDatas();
		}
		
		foreach($this->collGroupDatas as $groupData) {
			if ($groupData->getGroupTypeDataId() == $groupTypeData->getId()) {
				return $groupData;
			}
		}
	}
		
	/**
	 * @param type $typeGroup
	 * 
	 * @return type
	 */
	public function getGroupChilds($typeGroup = null)
	{
		$query = GroupQuery::create()->joinWith('GroupType');
		if (null != $typeGroup) {
			$query->add(GroupTypePeer::TYPE, $typeGroup);
		}

		$childGroups = $query->find();

		return $childGroups;
	}
	
	/**
	 * @param array $subgroups
	 */
	public function setSubgroups(array $subgroups)
	{
		$this->subgroups = $subgroups;
	}

	/**
	 * @return type
	 */
	public function getSubgroups()
	{
		if (true === !isset($this->subgroups)) {
			throw new \Exception('You did not set subgroups!');
		}
		
		return $this->subgroups;
	}
	
	public function hasSubgroup()
	{
		return isset($this->subgroups);
	}
	
	public function getParent()
	{
		if (!isset($this->parent)) {
			throw new \Exception('You did not set a parent group!');
		}
		
		return $this->parent;
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\om\BaseGroup $parent
	 */
	public function setParent(Group $parent)
	{
		$this->parent = $parent;
	}
	
	/**
	 * @param array<Integer> $subGroupIds
	 */
	public function setSubGroupsArray(array $subGroupIds)
	{
		$this->subGroupsArray = $subGroupIds;
	}
	
	/**
	 * @param int $groupTypeId
	 * 
	 * @return array
	 */
	public function getSubGroupArrayByGroupTypeId($groupTypeId)
	{
		foreach ($this->subGroupsArray as $subGroup) {
			if ($groupTypeId == $subGroup['group_type_id']) {
				return $subGroup;
			}
		}
		
		return null;
	}
	
	/**
	 * @return boolean 
	 */
	public function hasSubGroupArray($groupTypeId = null)
	{
		if (null == $groupTypeId) {
			return isset($this->subGroupsArray) && count($this->subGroupsArray) > 0;
		}
		
		if (isset($this->subGroupsArray)) {
			return null != $this->getSubGroupArrayByGroupTypeId($groupTypeId);
		}
		
		return false;
	}
	
	/**
	 * @return array<Module> 
	 */
	public function getActivatedModules($role)
	{
		if (!isset($this->activatedModules)) {
			BNSAccess::getContainer()->get('bns.group_manager')->setGroup($this);
			$this->modules = BNSAccess::getContainer()->get('bns.group_manager')->getActivatedModules($role);
		}
		
		return $this->activatedModules;
	}

	/**
	 * Remove the pending validation date
	 */
	public function removePendingValidationDate()
	{
		if (null != $this->pending_validation_date) {
			$this->modifiedColumns[] = GroupPeer::PENDING_VALIDATION_DATE;
		}
		
		$this->pending_validation_date = null;
	}
}
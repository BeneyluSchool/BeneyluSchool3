<?php
namespace BNS\App\CoreBundle\Model;

use \Criteria;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseGroupType;

/**
 * Skeleton subclass for representing a row from the 'group_type' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupType extends BaseGroupType
{
	private $attributes;
	
    /**
	 * @var array<Module> type group's modules
	 */
    private $modules;
	
	/**
	 * @return string 
	 */
    public function __toString()
	{
		return $this->getLabel();
	}
    
    /**
	 * Permet d'obtenir la liste des attributs du type de groupe $this
	 *
	 * @return    $label_type tableau qui contient les attributs triés par type (SINGLE, ONE_CHOICE, MULTIPLE_CHOICE)
	 */
    public function getGroupTypeDataArray() 
    {
		$groupTypeDatas = $this->getGroupTypeDatas();

		$label_type = array();
		foreach ($groupTypeDatas as $groupTypeData) 
		{
			$data = $groupTypeData->getGroupTypeDataTemplate();
			$label_type[$data->getType()][] = $data->getLabel(); 
		}

		return $label_type;
    }
	
	/**
	 * @param type $uniqueName
	 * 
	 * @return type
	 */
	public function getGroupTypeDataByUniqueName($uniqueName)
	{
		$c = new Criteria();
		$c->add(GroupTypeDataPeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME,$uniqueName);
		
		return $this->getGroupTypeDatas($c)->getFirst();
	}
	
	/**
	 * @param type $query
	 * @param PropelPDO $con
	 * 
	 * @return array<GroupTypeData> Tous les GroupTypeData avec leur template et i18n.
	 */
    public function getFullGroupTypeDatas($query = null, PropelPDO $con = null)
    {
		if (!isset($this->collGroupTypeDatas)) {
			if (null == $query) {
				$query = GroupTypeDataQuery::create();
			}

			$query->joinWith('GroupTypeDataTemplate')
				->joinWith('GroupTypeDataTemplate.GroupTypeDataTemplateI18n')
				->add(GroupTypeDataTemplateI18nPeer::LANG, BNSAccess::getLocale())
			;

			$this->collGroupTypeDatas = $this->getGroupTypeDatas($query, $con);
		}

		return $this->collGroupTypeDatas;
    }
    
    /**
     * Vérifie si le type de groupe $this possède l'attribut $uniqueName
     * 
     * @param String $uniqueName
     * 
     * @return 	soit null si l'attribut n'existe pas pour le type de groupe $this;
     * 			soit l'objet GroupTypeDataTemplate associé à l'attribut $uniqueName
     */ 
    public function hasAttribute($uniqueName)
    {
    	return in_array($uniqueName, $this->getAttributes());
    }
	
	/**
	 * @return array<String> 
	 */
	public function getAttributes()
	{
		if (!isset($this->attributes)) {
			$attrs = GroupTypeDataQuery::create()->filterByGroupTypeId($this->getId())->find();
			$attrsArray = array();
			
			foreach ($attrs as $attr) {
				$attrsArray[] = $attr->getGroupTypeDataTemplateUniqueName();
			}
			
			$this->attributes = $attrsArray;
		}
		
		return $this->attributes;
	}
	
	/**
	 * Simple shortcut 
	 * 
	 * @return boolean 
	 */
	public function isSimultateRole()
	{
		return $this->getSimulateRole();
	}
	
	/**
	 * @param array<Module> $modules
	 */
	public function setModules($modules)
	{
		$this->modules = $modules;
	}
	
	/**
	 * @return array<Module> 
	 */
	public function getModules($isContextable = null)
	{
		if (!isset($this->modules)) {
			$this->modules = BNSAccess::getContainer()->get('bns.right_manager')->getActivableModules($this->getId());
		}
		
		if (null !== $isContextable) {
			$modules = array();
			foreach ($this->modules as $module) {
				if ($module->isContextable() === $isContextable) {
					$modules[$module->getUniqueName()] = $module;
				}
			}
			
			return $modules;
		}
		
		return $this->modules;
	}
}
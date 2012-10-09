<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;

use BNS\App\CoreBundle\Model\om\BaseGroupTypeDataTemplate;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;


/**
 * Skeleton subclass for representing a row from the 'group_type_data_template' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupTypeDataTemplate extends BaseGroupTypeDataTemplate {
	/**
	 * @param PropelPDO $con
	 * 
	 * @return array<GroupTypeDataChoice> 
	 */
	public function getGroupTypeDataChoicesJoinWithI18n(PropelPDO $con = null)
	{
		if (!isset($this->collGroupTypeDataChoices))
		{
			$query = GroupTypeDataChoiceQuery::create()
				->joinWithI18n(BNSAccess::getLocale());
			
			$this->collGroupTypeDataChoices = $this->getGroupTypeDataChoices($query, $con);
		}
		
		return $this->collGroupTypeDataChoices;
	}
	
	public function printDefaultValue(){
		$value = parent::getDefaultValue();
		if(!$this->isChoiceable())
			return $value;
		else{
			$choice = GroupTypeDataChoiceQuery::create()->joinWithI18n()->findOneById($value);
			return $choice->getLabel() . ' - (' . $choice->getValue() . ')';
		}
			
	}
	
	/**
	 * @return string Converti le type d'un GroupTypeDataTemplate de telle manière qu'un humain peut le lire
	 */
	public function getTypeToString()
	{
		return self::typeToString($this->getType());
	}
	
	/**
	 * @param string $type 
	 */
	public static function typeToString($type)
	{
		switch ($type)
		{
			case GroupTypeDataTemplatePeer::TYPE_SINGLE:
				return 'phrase libre';
			case GroupTypeDataTemplatePeer::TYPE_TEXT:
				return 'texte libre';
			case GroupTypeDataTemplatePeer::TYPE_ONE_CHOICE:
				return 'choix unique';
			case GroupTypeDataTemplatePeer::TYPE_MULTIPLE_CHOICE:
				return 'choix multiples';
		}
	}
	
	public function getGroupTypeDataByGroupTypeId($group_type_id){
		$criteria = new Criteria();
		$criteria->add(GroupTypeDataPeer::GROUP_TYPE_ID,$group_type_id);
		return $this->getGroupTypeDatas($criteria)->getFirst();
	}
	
	public function isChoiceable(){
		if(in_array($this->getType(),array('ONE_CHOICE','MULTIPLE_CHOICE'))){
			return true;
		}
		return false;
	}
} // GroupTypeDataTemplate

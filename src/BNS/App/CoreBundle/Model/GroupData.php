<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupData;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use Symfony\Component\Config\Definition\Exception;
use \Criteria;

/**
 * Skeleton subclass for representing a row from the 'group_data' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupData extends BaseGroupData 
{	
	
	
	private $choices;
	private $available_choices;
	
	
	/**
	 * Permet de récupérer le label du GroupData $this
	 *
	 * @return    chaîne de caractère qui correspond au label du GroupData $this
	 */
	public function getLabel() 
	{		
		$DataTemplateI18ns = $this->getGroupTypeData()->getGroupTypeDataTemplate()->getGroupTypeDataTemplateI18ns();
		
		return $DataTemplateI18ns[0]->getLabel();
	}
	
	/**
	 * Permet de récupérer le type du GroupData (Si c'est SINGLE, ONE_CHOICE, MULTIPLE_CHOICE)
	 * 
	 * 
	 * @return    chaîne de caractère qui correspond au type du GroupData $this
	 */
	public function getType() {
		
		return $this->getGroupTypeData()->getGroupTypeDataTemplate()->getType();
	}
	
	
	/**
	 * Permet de récupérer la (ou les) valeur(s) du GroupData $this;
	 *
	 * @return    la méthode renvoie une chaîne de caractère si le GroupData est du type SINGLE ou ONE_CHOICE;
	 * 			  sinon renvoi un tableau de chaîne de caractère si le GroupData est du type MULTIPLE_CHOICE
	 */
	public function getValue() 
	{
		if ($this->value != null) 
		{
			return $this->value;
		}
		if ($this->getType() == GroupTypeDataTemplatePeer::TYPE_ONE_CHOICE) 
		{
			return $this->getUniqueChoice();
		}
		return $this->getMultipleChoices();
	}
	
	/**
	 * Méthode private qui permet de récupérer la valeur d'un GroupData de type ONE_CHOICE
	 *
	 * @return    string chaîne de caractère qui contient la valeur du GroupData $this
	 */
	private function getUniqueChoice() 
	{
		if ($this->collGroupDataChoices == null)
		{
			return null;
		}
		
		if (count($this->collGroupDataChoices) <= 0)
		{
			return null;
		}
		
		$groupDataChoice = $this->collGroupDataChoices[0];
		$DataChoiceI18ns = $groupDataChoice->getGroupTypeDataChoice()->getGroupTypeDataChoiceI18ns();
		
		return $DataChoiceI18ns[0]->getValue();
	}

	/**
	 * Méthode private qui permet de récupérer les valeurs d'un GroupData de type MULTIPLE_CHOICE
	 *
	 * @return    $values tableau de chaîne de caractère qui contient les valeur du GroupData $this
	 */
	private function getMultipleChoices() 
	{
		$values = array();
		if ($this->collGroupDataChoices == null)
		{
			return null;
		}
		foreach($this->collGroupDataChoices as $groupDataChoice) {
			$DataChoiceI18ns = $groupDataChoice->getGroupTypeDataChoice()->getGroupTypeDataChoiceI18ns();
			$values[$DataChoiceI18ns[0]->getId()] = $DataChoiceI18ns[0]->getValue();
		}
		
		return $values;
	}
	
	public function addChoice($value)
	{
		$choice = $this->findChoice($value);
		if($choice){
			$groupDataChoice = new GroupDataChoice();
			$groupDataChoice->setGroupTypeDataChoiceId($choice->getId());
			$groupDataChoice->setGroupData($this);
			$groupDataChoice->save();
		}
	}
	
	/**
	 * @param array $values
	 */
	public function addChoices(array $values)
	{
		foreach ($values as $value) {
			$this->addChoice($value);
		}
	}
	
	public function getAvailableChoices()
	{
		return $this->getGroupTypeData()->getGroupTypeDataTemplate()->getGroupTypeDataChoices();
	}
	
	public function findChoice($value)
	{
		$c = new Criteria();
		$c->add(GroupTypeDataChoicePeer::VALUE,$value);
		$values = $this->getGroupTypeData()->getGroupTypeDataTemplate()->getGroupTypeDataChoices($c);
		if(isset($values[0]))
			return $values[0];
		return null;
	}
	
	public function clearChoices()
	{
		$this->getChoices()->delete();
	}
	
	public function getChoices(){
		return $this->getGroupDataChoices();
	}
		
} // GroupData

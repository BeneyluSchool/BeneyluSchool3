<?php

namespace BNS\App\WorkshopBundle\Form\Model;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class WorkshopDocumentFormModel
{
	/**
	 * @var \BNS\App\WorkshopBundle\Model\WorkshopDocumentTemplate
	 */
	private $template;
	
	/**
	 * @var array<String>
	 */
	private $inputs;
	
	/**
	 * @param \BNS\App\WorkshopBundle\Model\WorkshopDocumentTemplate $template
	 */
	public function __construct($template)
	{
		$this->template = $template;
		$this->inputs   = array();
	}
	
	public function save()
	{
		
	}
	
	/**
	 * @param string $inputUniqueName
	 * @param mixed  $value
	 */
	public function __set($inputUniqueName, $value)
	{
		if (preg_match('#input_#', $inputUniqueName)) {
			$this->inputs[$inputUniqueName] = $value;
		}
	}
	
	/**
	 * @param string $inputUniqueName
	 * 
	 * @return mixed
	 */
	public function __get($inputUniqueName)
	{
		if (!isset($this->inputs[$inputUniqueName])) {
			return null;
		}
		
		return $this->inputs[$inputUniqueName];
	}
}
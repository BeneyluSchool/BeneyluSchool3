<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseModule;

/**
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class Module extends BaseModule
{
	/**
	 * @var string slug
	 */
	private $slug;
	
	// Needed for manage classroom module statement
	private $isActivatedForParent = false;
	private $isActivatedForPupil = false;
	
	// Needed for manage team module statement
	private $isActivatedForMember = false;
	private $isActivatedForOther = false;
        
	/**
	 *
	 * @var boolean Used for  <> Module reference
	 * True if the module is activated (Module reference exist in the database for this module) for a  object, false otherwise
	 */
	private $isActivated = false;
	
	/**
	 * Permet de récupérer le slug du module
	 *
	 * @return    Renvoi une chaîne de caractère qui correspond au slug du module
	 */
	public function getSlug()
	{
		if (!isset($this->slug))
			$this->slug = $this->getCurrentTranslation()->getSlug();
		
		return $this->slug;
	}
	
	public function activate()
	{
		$this->isActivated = true;
	}
	
	/**
	 * @return boolean module's state
	 */
	public function isActivated()
	{
		return $this->isActivated;
	}
	
	public function getRouteFront()
	{
		return 'BNSApp' . $this->getBundleName() . '_front';
	}
	
	public function getRouteBack()
	{
		return 'BNSApp' . $this->getBundleName() . '_back';
	}
        
	public function activateForParent()
	{
		$this->isActivatedForParent = true;
	}

	public function isActivatedForParent()
	{
		return $this->isActivatedForParent;
	}

	public function activateForPupil()
	{
		$this->isActivatedForPupil = true;
	}

	public function isActivatedForPupil()
	{
		return $this->isActivatedForPupil;
	}
	
	public function activateForMember()
	{
		$this->isActivatedForMember = true;
	}

	public function isActivatedForMember()
	{
		return $this->isActivatedForMember;
	}

	public function activateForOther()
	{
		$this->isActivatedForOther = true;
	}

	public function isActivatedForOther()
	{
		return $this->isActivatedForOther;
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isContextable()
	{
		return $this->getIsContextable();
	}
}
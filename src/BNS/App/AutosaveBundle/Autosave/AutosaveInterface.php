<?php

namespace BNS\App\AutosaveBundle\Autosave;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * Date : 1 juin 2012
 */
interface AutosaveInterface
{
	/**
	 * @params mixed $object  
	 */
	public function autosave(array $objects);
}
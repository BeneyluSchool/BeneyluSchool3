<?php

namespace BNS\App\RegistrationBundle\Model;

use Symfony\Component\Validator\ExecutionContext;

use BNS\App\RegistrationBundle\Model\om\BaseSchoolInformation;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class SchoolInformation extends BaseSchoolInformation
{
	/**
	 * Validation du code UAI selon le pays lors de la soumission du formulaire
	 * de création d'une école
	 * 
	 * @param \BNS\App\RegistrationBundle\Model\ExecutionContext $context
	 */
	public function isUaiValid($context)
    {
		if ('FR' == $this->getCountry() && null == $this->getUai()) {
			$context->addViolationAt('uai', 'Vous devez saisir votre code UAI', array(), null);
		}
    }
}

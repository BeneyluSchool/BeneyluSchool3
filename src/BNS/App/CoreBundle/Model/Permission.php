<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BasePermission;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use JMS\TranslationBundle\Annotation\Ignore;


/**
 * Skeleton subclass for representing a row from the 'permission' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class Permission extends BasePermission
{
    use TranslatorTrait;

    public function getDescription()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getDescriptionToken();
        }

        /** @Ignore */
        return $translator->trans($this->getDescriptionToken(), array(), 'PERMISSION');
    }

    public function getDescriptionToken()
    {
        return 'DESCRIPTION_' . $this->getUniqueName();
    }

    public function getLabel()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getUniqueName();
        }

        /** @Ignore */
        return $translator->trans($this->getUniqueName(), array(), 'PERMISSION');
    }

    public function setLabel($v)
    {
        return $this;
    }

    public function setDescription($v)
    {
        return $this;
    }

} // Permission

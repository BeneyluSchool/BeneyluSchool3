<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseRank;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use JMS\TranslationBundle\Annotation\Ignore;


/**
 * Skeleton subclass for representing a row from the 'rank' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class Rank extends BaseRank
{
    use TranslatorTrait;

    public function getDescription()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getDescriptionToken();
        }

        /** @Ignore */
        return $translator->trans($this->getDescriptionToken(), array(), 'RANK');
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
        if (preg_match('/_BETA$/', $this->getUniqueName())) {
            return '(BETA) ' . $translator->trans(/** @Ignore */ preg_replace('/_BETA$/', '', $this->getUniqueName()), array(), 'RANK');
        }

        /** @Ignore */
        return $translator->trans($this->getUniqueName(), array(), 'RANK');
    }

    public function setLabel($v)
    {
        return $this;
    }

    public function setDescription($v)
    {
        return $this;
    }

} // Rank

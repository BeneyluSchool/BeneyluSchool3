<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\LsuBundle\Model\om\BaseLsuLevel;

class LsuLevel extends BaseLsuLevel
{
    use TranslatorTrait;

    public function getLabel()
    {
        // Use translation from group level attribute
        /** @Ignore */return $this->trans('LABEL_DATA_CHOICE_LEVEL_' . $this->getCode(), [], 'GROUP_TYPE');
    }

    public function getCycleRaw()
    {
        return $this->cycle + 1;
    }
}

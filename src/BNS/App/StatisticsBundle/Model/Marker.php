<?php

namespace BNS\App\StatisticsBundle\Model;

use BNS\App\StatisticsBundle\Model\om\BaseMarker;
use BNS\App\CoreBundle\Translation\TranslatorTrait;


class Marker extends BaseMarker
{
    use TranslatorTrait;

    public function getDescription()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getDescriptionToken();
        }

        /** @Ignore */
        return $translator->trans($this->getDescriptionToken(), array(), 'STATISTICS');
    }

    public function getDescriptionToken()
    {
        return 'DESCRIPTION_' . $this->getUniqueName();
    }

    /**
     * @deprecated do not use this
     * @param $v
     * @return $this
     */
    public function setDescription($v)
    {
        return $this;
    }

}

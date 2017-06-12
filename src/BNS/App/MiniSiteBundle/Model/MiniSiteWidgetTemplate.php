<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\MiniSiteBundle\Model\om\BaseMiniSiteWidgetTemplate;
use JMS\TranslationBundle\Annotation\Ignore;

class MiniSiteWidgetTemplate extends BaseMiniSiteWidgetTemplate
{
    use TranslatorTrait;

    public function getLabel()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getType();
        }

        /** @Ignore */
        return $translator->trans($this->getLabelToken(), [], 'MINISITE');
    }

    public function getLabelToken()
    {
        return 'LABEL_WIDGET_TEMPLATE_' . $this->getType();
    }

    public function getDescription()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getType();
        }

        /** @Ignore */
        return $translator->trans($this->getDescriptionToken(), [], 'MINISITE');
    }

    public function getDescriptionToken()
    {
        return 'DESCRIPTION_WIDGET_TEMPLATE_' . $this->getType();
    }
}

<?php

namespace BNS\App\ScolomBundle\Model;

use BNS\App\ScolomBundle\Model\om\BaseScolomDataTemplate;

class ScolomDataTemplate extends BaseScolomDataTemplate
{
    /**
     * @param string $locale
     * 
     * @return array
     */
    public function getI18n($locale)
    {
        $i18ns = $this->getScolomDataTemplateI18ns();
        $localeI18ns = array();

        foreach ($i18ns as $i18n) {
            if ($i18n->getLang() == $locale) {
                $localeI18ns[] = $i18n;
            }
        }

        return $localeI18ns;
    }
}

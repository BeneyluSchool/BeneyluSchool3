<?php
namespace BNS\App\CoreBundle\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as BaseTranslator;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class Translator extends BaseTranslator
{
    /**
     * Handle custom local for children, do not expose specification outside of the class
     */
    public function getLocale()
    {
        $locale = parent::getLocale();

        return str_replace('_CHILD', '', $locale);
    }

    public function getRealLocale()
    {
        return parent::getLocale();
    }

    public function isChildLocale()
    {
        return false !== strpos(parent::getLocale(), '_CHILD');
    }



    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        if (null === $locale) {
            // Force the use of internal locale (with _CHILD information)
            $locale = $this->getRealLocale();
        }

        return parent::getCatalogue($locale);
    }
}

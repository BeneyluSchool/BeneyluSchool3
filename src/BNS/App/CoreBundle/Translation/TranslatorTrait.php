<?php

namespace BNS\App\CoreBundle\Translation;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
trait TranslatorTrait
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @return TranslatorInterface
     */
    public function getTranslator()
    {
        if (!$this->translator) {
            $container = BNSAccess::getContainer();
            if ($container) {
                $this->translator = $container->get('translator');
            }
        }

        return $this->translator;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * translate a text by calling translator if unavailable fallback to the $id
     * @param string $id translation token / text
     * @param array $parameters
     * @param string|null $domain
     * @param string|null $locale
     * @return string
     */
    public function translate($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if ($translator = $this->getTranslator()) {
            /** @Ignore */return $translator->trans($id, $parameters, $domain, $locale);
        }

        return $id;
    }
}

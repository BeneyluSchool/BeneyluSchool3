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
     * Translates the given message by calling translator if unavailable fallback to the $id.
     *
     * @param string      $id         The message id (may also be an object that can be cast to string)
     * @param array       $parameters An array of parameters for the message
     * @param string|null $domain     The domain for the message or null to use the default
     * @param string|null $locale     The locale or null to use the default
     *
     * @return string The translated string or $id if translator is not available
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if ($translator = $this->getTranslator()) {
            /** @Ignore */return $translator->trans($id, $parameters, $domain, $locale);
        }

        return $id;
    }
}

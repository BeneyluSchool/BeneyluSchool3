<?php

namespace BNS\App\CoreBundle\Form\Type;

use BNS\CommonBundle\Locale\LocaleManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AvailableLocaleType
 *
 * @package BNS\App\CoreBundle\Form\Type
 */
class AvailableLocaleType extends AbstractType
{

    /**
     * @var LocaleManager
     */
    private $localeManager;

    public function __construct(LocaleManager $localeManager)
    {
        $this->localeManager = $localeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => $this->localeManager->getNiceAvailableLanguages(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'available_locale';
    }

}

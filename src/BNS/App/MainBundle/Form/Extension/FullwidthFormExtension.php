<?php

namespace BNS\App\MainBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class FullwidthFormExtension
 *
 * @package BNS\App\MainBundle\Form\Extension
 */
class FullwidthFormExtension extends AbstractTypeExtension
{

    const OPTION_NAME = 'fullwidth';

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return 'form';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setOptional(array(self::OPTION_NAME));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (array_key_exists(self::OPTION_NAME, $options)) {
            $view->vars[self::OPTION_NAME] = $options[self::OPTION_NAME];
        }
    }

}

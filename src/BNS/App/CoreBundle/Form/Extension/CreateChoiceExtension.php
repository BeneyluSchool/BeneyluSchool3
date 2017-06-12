<?php

namespace BNS\App\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class CreateChoiceExtension
 *
 * Enhance the base choice widget with a new 'create' option, that allows to create additional choices directly in the
 * widget.
 * To enable this feature, the option must be set to a string corresponding to an API route, where data will be posted.
 *
 * @package BNS\App\CoreBundle\Form\Extension
 */
class CreateChoiceExtension extends AbstractTypeExtension
{

    const OPTION_NAME = 'create';

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined(self::OPTION_NAME);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (array_key_exists(self::OPTION_NAME, $options)) {
            $view->vars[self::OPTION_NAME] = $options[self::OPTION_NAME];
        }
    }

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return 'choice';
    }

}

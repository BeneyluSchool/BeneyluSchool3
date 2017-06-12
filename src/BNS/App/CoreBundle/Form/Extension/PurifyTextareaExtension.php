<?php

namespace BNS\App\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class PurifyTextareaExtension
 *
 * @package BNS\App\CoreBundle\Form\Extension
 */
class PurifyTextareaExtension extends AbstractTypeExtension
{

    private $purifierTransformer;

    public function __construct(DataTransformerInterface $purifierTransformer)
    {
        $this->purifierTransformer = $purifierTransformer;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'purify' => true
        ));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['purify']) {
            $builder->addViewTransformer($this->purifierTransformer);
        }
    }

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return 'textarea';
    }

}

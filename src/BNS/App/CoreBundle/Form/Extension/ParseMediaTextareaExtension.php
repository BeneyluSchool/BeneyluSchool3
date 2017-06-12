<?php

namespace BNS\App\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ParseMediaTextareaExtension
 *
 * @package BNS\App\CoreBundle\Form\Extension
 */
class ParseMediaTextareaExtension extends AbstractTypeExtension
{

    private $publicMediaTransformer;

    public function __construct(DataTransformerInterface $publicMediaTransformer)
    {
        $this->publicMediaTransformer = $publicMediaTransformer;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'parse_media' => false
        ));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['parse_media']) {
            $builder->addViewTransformer($this->publicMediaTransformer);
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

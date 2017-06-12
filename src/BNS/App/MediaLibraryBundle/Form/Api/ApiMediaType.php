<?php

namespace BNS\App\MediaLibraryBundle\Form\Api;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ApiMediaType
 *
 * @package BNS\App\MediaLibraryBundle\Form\Api
 */
class ApiMediaType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', 'text');
        $builder->add('description', 'textarea');
        $builder->add('is_private', 'checkbox');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\MediaLibraryBundle\Model\Media',
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'api_media';
    }

}

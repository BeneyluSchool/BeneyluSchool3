<?php

namespace BNS\App\ResourceBundle\Form\Api;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ApiResourceType
 *
 * @package BNS\App\ResourceBundle\Form\Api
 */
class ApiResourceType extends AbstractType
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
            'data_class' => 'BNS\App\ResourceBundle\Model\Resource',
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'api_resource';
    }

}

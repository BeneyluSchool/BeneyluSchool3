<?php
namespace BNS\App\GroupBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ActivationStatFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('date_start', 'datetime', array(
            'widget' => 'single_text',
            'required'    => true,
            'date_format' => 'dd-MMMM-yyyy',
            'attr' => array('class' => 'date')
        ));

        $builder->add('date_end', 'datetime', array(
            'widget' => 'single_text',
            'required'    => true,
            'date_format' => 'dd-MMMM-yyyy',
            'attr' => array('class' => 'date')
        ));
    }


    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'activation_stat_filter';
    }

}

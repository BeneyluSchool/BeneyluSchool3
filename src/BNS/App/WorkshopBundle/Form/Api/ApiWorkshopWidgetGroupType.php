<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\ResourceBundle\Right\BNSResourceRightManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * Class ApiWorkshopWidgetGroupType
 *
 * @package BNS\App\WorkshopBundle\Form\Api
 */
class ApiWorkshopWidgetGroupType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id');
        $builder->add('zone');
        $builder->add('position');
        $builder->add('workshop_widgets', 'collection', array(
            'type' => 'workshop_widget',
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup',
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'workshop_widget_group';
    }

}

<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryRightManager;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * Class ApiWorkshopWidgetType
 *
 * @package BNS\App\WorkshopBundle\Form\Api
 */
class ApiWorkshopWidgetExtendedSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('choices');
        $builder->add('correct_answers');
        $builder->add('advanced_settings');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {

        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSetting',
        ));

    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'workshop_widget_extended_setting';
    }

}

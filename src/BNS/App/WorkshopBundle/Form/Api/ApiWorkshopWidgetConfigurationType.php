<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\WorkshopBundle\Manager\WidgetConfigurationManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Class ApiWorkshopWidgetConfigurationType
 *
 * @package BNS\App\WorkshopBundle\Form\Api
 */
class ApiWorkshopWidgetConfigurationType extends AbstractType
{

    /**
     * @var WidgetConfigurationManager
     */
    private $widgetConfigurationManager;

    public function __construct(WidgetConfigurationManager $widgetConfigurationManager)
    {
        $this->widgetConfigurationManager = $widgetConfigurationManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('zone', 'integer');
        $builder->add('position', 'integer');
        $builder->add('code', 'text', array(
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->widgetConfigurationManager->getValidConfigurationCodes(),
                )),
            ),
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'workshop_widget_configuration';
    }

}

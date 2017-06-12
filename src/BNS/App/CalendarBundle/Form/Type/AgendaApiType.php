<?php

namespace BNS\App\CalendarBundle\Form\Type;
use BNS\App\CoreBundle\Model\Agenda;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class AgendaApiType
 *
 * @package BNS\App\CalendarBundle\Form\Type
 */
class AgendaApiType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('color_class', 'choice', [
            'choices' => Agenda::$colorsClass,
        ]);
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'agenda';
    }

}

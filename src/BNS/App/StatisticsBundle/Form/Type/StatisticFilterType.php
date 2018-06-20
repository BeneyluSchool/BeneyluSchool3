<?php

namespace BNS\App\StatisticsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use \BNS\App\StatisticsBundle\Model\MarkerQuery;
use \BNS\App\CoreBundle\Model\ModuleQuery;
use \BNS\App\CoreBundle\Model\ModuleI18nQuery;

/**
 * @author Jérémie Augustin <sylvain.lorinet@pixel-cookers.com>
 */
class StatisticFilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('groupIds', 'choice', array(
            'required'  => false,
            'choices'   => $options['groupIds'],
            'choices_as_values' => true,
            'multiple'  => true,
        ));

        $builder->add('start', 'date', array(
            'widget'      => 'single_text',
            'required'    => true,
        ));

        $builder->add('end', 'date', array(
            'widget' => 'single_text',
            'required'    => true,
        ));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'groupIds' => array()
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'statistic_filter';
    }
}

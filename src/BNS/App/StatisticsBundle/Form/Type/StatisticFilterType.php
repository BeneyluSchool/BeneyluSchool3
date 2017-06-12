<?php

namespace BNS\App\StatisticsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
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
        $builder->add('groups', 'choice', array(
            'required'  => false,
            'choice_list'   => new ObjectChoiceList($options['groups'], 'label', array(), null, 'id'),
            'multiple'  => true,
            'expanded'  => true,
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
            'groups' => array()
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

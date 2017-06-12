<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
    
/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * @author Florian Rotagnon <florian.rotagnon@gmail.com>
 */
class StatsFilterType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
        $markers = \BNS\App\StatisticsBundle\Model\MarkerQuery::create()->find();
        
        foreach ($markers as $marker) {
            //TODO ajouter la description
            $tabMarkers[$marker->getUniqueName()] = $marker->getUniqueName();
        }
                
        $builder->add('aggregation', 'checkbox', array(
            'required' => false)
            );
        
        $builder->add('marker', 'choice', array(
            'required' => true,
            'choices' => $tabMarkers
        ));
        
        $builder->add('sub_groups', 'choice', array(
            'required' => false,
            'multiple' => true,
            'choices' => array()
        ));
        
        $builder->add('period', 'choice', array(
            'required' => true,
            'choices'	=> array(
				'DAY' => 'LABEL_DAY',
				'MONTH'	  => 'LABEL_MONTH',
				'HOURS'  => 'LABEL_HOUR'
			)
        ));
        
        $builder->add('title', 'text', array(
            'required' => false,
            'max_length' => 50
        ));
        
		// Nom
		$builder->add('group_type', 'choice', array(
			'required'	=> false,
			'choices'	=> array(
				'TEACHER' => 'LABEL_TEACHER',
				'PUPIL'	  => 'LABEL_PUPIL',
				'PARENT'  => 'LABEL_PARENTS'
			),
			'empty_value' => 'LABEL_ALL_ROLES'
		));
		
		$builder->add('date_start', 'datetime', array(
			'required'    => false,
			'date_format' => 'dd / MMMM / yyyy'
		));
		
		$builder->add('date_end', 'datetime', array(
			'required'    => false,
			'date_format' => 'dd / MMMM / yyyy'
		));
	}
	
	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ClassroomBundle\Form\Model\StatsFilterFormModel',
            'translation_domain' => 'CLASSROOM',
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'stats_filter';
	}
}
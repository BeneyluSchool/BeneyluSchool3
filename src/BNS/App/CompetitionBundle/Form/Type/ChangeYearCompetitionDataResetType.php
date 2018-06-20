<?php

namespace BNS\App\CompetitionBundle\Form\Type;

use BNS\App\CompetitionBundle\DataReset\ChangeYearCompetitionDataReset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearCompetitionDataResetType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
        $builder->add('choice', 'choice', array(
            'required' => true,
            'choices' => ChangeYearCompetitionDataReset::getChoices(),
            'empty_value' => 'PLEASE_CHOICE',
            'error_bubbling' => true
        ));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CompetitionBundle\DataReset\ChangeYearCompetitionDataReset',
            'translation_domain' => 'COMPETITION'
        ));
    }

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'competition_data_reset_form';
	}
}

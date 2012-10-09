<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\GroupTypeDataChoice;

class GroupTypeDataChoiceType extends AbstractType
{
	private $groupTypeDataChoice;
	
	public function __construct(GroupTypeDataChoice $groupTypeDataChoice = null)
	{
		$this->groupTypeDataChoice = $groupTypeDataChoice;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	
		$builder->add('value','text', array('required' => true));
		
		$builder->add('group_type_data_choice_i18ns', 'collection', array(
            'type'          => new \BNS\App\CoreBundle\Form\Type\GroupTypeDataChoiceI18nType(),
            'allow_add'     => true,
            'allow_delete'  => false,
            'by_reference'  => true
        ));
		
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\GroupTypeDataChoice',
        ));
    }

    public function getName()
    {
        return 'group_type_data_choice';
    }
}
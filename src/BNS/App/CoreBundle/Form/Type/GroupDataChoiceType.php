<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupDataChoiceType extends AbstractType
{
	private $groupDataChoices;
	private static $index = 0;
	
	public function __construct($groupDataChoices = null)
	{
		$this->groupDataChoices = $groupDataChoices;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$builder->add('group_type_data_choice', new \BNS\App\CoreBundle\Form\Type\GroupTypeDataChoiceType($this->groupDataChoices[self::$index]->getGroupTypeDataChoice()));
    	self::$index++;
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\GroupDataChoice',
        ));
    }

    public function getName()
    {
        return 'group_data_choice';
    }
}
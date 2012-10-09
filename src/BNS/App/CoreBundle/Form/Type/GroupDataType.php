<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;

class GroupDataType extends AbstractType
{
	private $groupDatas;
	
	public function __construct($groupDatas = array())
	{
		$this->groupDatas = $groupDatas;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	if (count($this->groupDatas) > 0)
    	{
	    	$index = substr($options['property_path'], 1, -1);
	    	$groupData = $this->groupDatas[$index];
	   		if ($groupData->getType() == GroupTypeDataTemplatePeer::TYPE_SINGLE)
	    	{
	    		$builder->add('value', 'text', array(
		    		'label'			=> $groupData->getLabel(),
		    		'required'		=> true,
	    		));
	    	}
	    	else if ($groupData->getType() == GroupTypeDataTemplatePeer::TYPE_TEXT)
	    	{
	    		$builder->add('value', 'textarea', array(
		    		'label'			=> $groupData->getLabel(),
		    		'required'		=> true,
	    		));
	    	}
	    	else
	    	{
	    		$builder->add('group_data_choices', 'collection', array(
		    		'type'          => new \BNS\App\CoreBundle\Form\Type\GroupDataChoiceType($groupData->getGroupDataChoices()),
		    		'allow_add'     => false,
		    		'allow_delete'  => false,
		    		'by_reference'  => false
	    		));
	    	}
    	}
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\GroupData',
        ));
    }

    public function getName()
    {
        return 'group_data_type';
    }
}
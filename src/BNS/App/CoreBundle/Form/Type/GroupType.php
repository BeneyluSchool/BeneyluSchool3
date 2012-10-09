<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$query = GroupTypeQuery::create()->filterBySimulateRole(false)->joinWithI18n(BNSAccess::getLocale());
		$builder->add('group_type','model',array('class' => 'BNS\App\CoreBundle\Model\GroupType','query' => $query,'label' => "Type de groupe :"));
		
		$builder->add('label','text',array('label' => 'Nom :'));
		
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Group',
        ));
    }

    public function getName()
    {
        return 'group_type';
    }
}
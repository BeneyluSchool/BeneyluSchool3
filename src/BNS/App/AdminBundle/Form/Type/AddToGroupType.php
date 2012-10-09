<?php

namespace BNS\App\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

/**
 * Formulaire d'ajout d'un utilisateur à un groupe
 */

class AddToGroupType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$locale = BNSAccess::getLocale();
		$builder->add('group_id','text',array('label' => "Ajouter au groupe d'ID * :",'required' => true));
		$roles = GroupTypeQuery::create()->filterByRole()->joinWithi18n($locale)->find();
		foreach($roles as $role){
			$formatedRole[$role->getId()] = $role->getLabel($locale);
		}
		$builder->add('group_type_role_id', 'choice', array(
			'empty_value' => 'Choisir un type de groupe',
            'choices' => $formatedRole,
			'label' => "Avec le rôle ",
			"required" => false
        ));
	}
	public function getName()
	{
		return 'addToGroup';
	}
}

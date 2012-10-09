<?php

namespace BNS\App\CoreBundle\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Model\ModuleI18nPeer;

class RuleType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = \BNS\App\CoreBundle\Access\BNSAccess::getLocale();
		
		$ranks = RankQuery::create()
				->joinWithI18n($locale)
				->orderByModuleId()
				->find();
		
		foreach($ranks as $rank){
			$formatedRank[$rank->getUniqueName()] = $rank->getLabel($locale);
		}
		
		$builder->add('rule_where_group_id','hidden', array('required' => true));
		$builder->add('rank_unique_name', 'choice', array(
			'empty_value' => 'Choisir un rang',
             'choices' => $formatedRank,
			'label' => "Rang"
        ));
		
		$roles = GroupTypeQuery::create()->filterByRole()->joinWithi18n($locale)->find();
	
		foreach($roles as $role){
			$formatedRole[$role->getId()] = $role->getLabel($locale);
		}
		
		$notRoles = GroupTypeQuery::create()->filterByNotRole()->joinWithi18n($locale)->find();
	
		foreach($notRoles as $role){
			$formatedNotRole[$role->getId()] = $role->getLabel($locale);
		}
		
		$builder->add('who_group_id', 'choice', array(
			'empty_value' => 'Choisir un type de groupe',
            'choices' => $formatedRole,
			'label' => "Pour qui ?",
			"required" => true
        ));
		
		$builder->add('rule_where_group_type_id', 'choice', array(
			'empty_value' => 'Groupe lui même',
            'choices' => $formatedNotRole,
			'label' => "Groupes cibles ?",
			"required" => false
        ));
		
		$builder->add(
			'rule_where_belongs'
			,'choice',
			array(
				'label' => "Pour les membres des groupes ?",
				'required' => false,
				'choices'   => array(
					true => 'Oui', false => 'Non'
					),
				'multiple'  => false,
				'expanded' => true
			)
		);
		
		$builder->add(
			'state'
			,'choice',
			array(
				'label' => "Règle active",
				'required' => false,
				'choices'   => array(
					true => 'Oui', false => 'Non'
					),
				'multiple'  => false,
				'expanded' => true
			)
		);
    }

    public function getName()
    {
        return 'rule';
    }
}

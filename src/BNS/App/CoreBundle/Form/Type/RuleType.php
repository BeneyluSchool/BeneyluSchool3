<?php

namespace BNS\App\CoreBundle\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class RuleType extends AbstractType
{

	protected $not_authorised_ranks = null;

	public function __construct($options = array()) {
		if(isset($options['not_authorised_ranks'])){
			$this->not_authorised_ranks = $options['not_authorised_ranks'];
		}
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$ranks = RankQuery::create()
				->joinWith('Module')
				->filterByUniqueName($this->not_authorised_ranks,  \Criteria::NOT_IN)
				->orderByModuleId()
				->find();

		foreach($ranks as $rank){
			$formatedRank[$rank->getModule()->getLabel()][$rank->getUniqueName()] = $rank->getLabel();
		}

		$builder->add('rule_where_group_id','hidden', array('required' => true));
		$builder->add('rank_unique_name', 'choice', array(
			'empty_value' => 'CHOOSE_RANK',
            'choices' => $formatedRank,
			'label' => "LABEL_RANK"
        ));

		$roles = GroupTypeQuery::create()->filterByRole()->find();

		foreach($roles as $role){
			$formatedRole[$role->getId()] = $role->getLabel();
		}

		$notRoles = GroupTypeQuery::create()->filterByNotRole()->find();

		foreach($notRoles as $role){
			$formatedNotRole[$role->getId()] = $role->getLabel();
		}

		$builder->add('who_group_id', 'choice', array(
			'empty_value' => 'CHOOSE_GROUP_TYPE',
            'choices' => $formatedRole,
			'label' => "LABEL_FOR_WHO",
			"required" => true
        ));

		$builder->add('rule_where_group_type_id', 'choice', array(
			'empty_value' => 'GROUP_HIMSELF',
            'choices' => $formatedNotRole,
			'label' => "LABEL_TARGET_GROUP",
			"required" => false
        ));

		$builder->add(
			'rule_where_belongs'
			,'hidden',
			array(
				'data' => true
			)
		);

		$builder->add(
			'state'
			,'choice',
			array(
				'label' => "LABEL_ACTIVE_RULE",
				'required' => true,
				'choices'   => array(
					true => 'CHOICE_YES', false => 'CHOICE_NO'
					),
				'multiple'  => false,
				'expanded' => false,
				'empty_value' => false,
				'preferred_choices' => array(1)
			)
		);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'CORE'
        ));
    }

    public function getName()
    {
        return 'rule';
    }
}

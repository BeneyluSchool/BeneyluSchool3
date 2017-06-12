<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Propel\PropelBundle\Form\BaseAbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ClassroomCalendarType extends BaseAbstractType
{
	protected $options = array(
		'data_class' => 'BNS\App\ClassroomBundle\Model\ClassroomNewspaper',
		'name'       => 'classroomnewspaper',
	);

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('date','date', array(
			'widget' => 'choice',
		));
		$builder->add('title', 'text', array('label' => 'Titre') );
		$builder->add('media_title','hidden');
		$builder->add('media_preview_id','hidden');
		$builder->add('media_id','hidden');
		$builder->add('media_preview_id','hidden');
		$builder->add('is_calendar','hidden', (array('data' => '1')));
		$builder->add('joke','textarea',array('label' => 'Texte', 'attr' => array('bns-tinymce' => '')));

	}
}

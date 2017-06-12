<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use BNS\App\CoreBundle\Translation\TranslatorTrait;


class ImportPupilFromCSVType extends AbstractType
{
	use TranslatorTrait;

	const FORM_NAME = 'import_user_from_csv_form';
		
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Sexe
		$translator = $this->getTranslator()->getLocale();
		if($translator == 'fr'){
			$builder->add('format', 'choice', array(
					'label'			=> 'LABEL_FILE_FORMAT',
					'choices'		=> array(
							0 => 'CHOICE_FORMAT_BENEYLU',
							1 => 'CHOICE_FORMAT_PUPIL'
					),
					'required'		=> true,
					'expanded'		=> true,
					'multiple'		=> false,
					'empty_value'	=> false,
			));
		} else {
			$builder->add('format', 'choice', array(
					'label'			=> 'LABEL_FILE_FORMAT',
					'choices'		=> array(
							0 => 'CHOICE_FORMAT_BENEYLU',
					),
					'required'		=> true,
					'expanded'		=> true,
					'multiple'		=> false,
					'empty_value'	=> false,
			));
		}

		// Fichier CSV
		$builder->add('file', 'file', array(
			'label'		=> 'LABEL_FILE_PATH',
			'required' 	=> true,
		));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'CLASSROOM'
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return self::FORM_NAME;
	}
}
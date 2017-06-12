<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Intl\Intl;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditClassroomType extends AbstractType
{
	const FORM_NAME = 'edit_classroom_form';

    /**
     * @var string représente la timezone actuelle des élèves
     */
    private $currentTimezone;

    /**
     * @var string représente la langue actuelle des élèves
     */
    private $currentLanguage;




    public function __construct($parameters = null)
    {
        $this->currentTimezone = $parameters['timezone'];
        $this->currentLanguage = $parameters['lang'];
    }

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Nom
		$builder->add('name', 'text', array(
			'label'		=> 'LABEL_NAME',
			'required'	=> true,
		));

		// Avatar_id
		$builder->add('avatarId', 'hidden', array(
			'required' => true,
		));

		$levelGroupTypeDataTemplate = GroupTypeDataTemplateQuery::create()
			->add(GroupTypeDataTemplatePeer::UNIQUE_NAME,  'LEVEL')
		->findOne();

		$choices = array();
		foreach ($levelGroupTypeDataTemplate->getGroupTypeDataChoices() as $choice) {
			$choices[$choice->getValue()] = $choice->getLabel();
		}

        // Langue
        $currentLang = null !== $this->currentLanguage ? $this->currentLanguage : 'English';
        $builder->add('lang', 'available_locale', array(
            'required' => true,
            'data' => $currentLang
        ));

        $timezone = null !== $this->currentTimezone ? new \DateTimeZone($this->currentTimezone) : new \DateTimeZone('Europe/Paris');

        $builder->add('timezone', 'timezone', array(
            'expanded' => false,
            'multiple' => false,
            'data' => $timezone->getName()
        ));

        // Niveau
		$builder->add('level', 'choice', array(
			'label'		=> 'LABEL_LEVEL',
			'choices' 	=> $choices,
			'required' 	=> true,
			'expanded'	=> true,
			'multiple'	=> true
		));

		$builder->add('description', 'textarea', array(
			'label'	=> 'LABEL_DESCRIPTION'
		));

		$builder->add('home_message', 'textarea', array(
			'label'	=> "LABEL_WELCOME_MESSAGE",
		));

        if ($options['with_country']) {
            $builder->add('country', 'available_country', [
                'label' => 'LABEL_COUNTRY',
                'empty_value' => 'LABEL_COUNTRY',
                'constraints' => [
                    new NotBlank(['message' => 'INVALID_COUNTRY_EMPTY']),
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\ClassroomBundle\Form\Model\EditClassroomFormModel',
            'translation_domain' => 'CLASSROOM',
            'with_country' => false,
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

<?php

namespace BNS\App\RegistrationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ClassRoomRegistrationType extends AbstractType
{
	/**
	 * @var array<String, String> 
	 */
	private $levels;
	
	/**
	 * @param array|PropelObjectCollection $levelObjects
	 */
	public function __construct($levelObjects = null)
	{
		$this->levels = array();
		
		if (null != $levelObjects) {
			foreach ($levelObjects as $level) {
				$this->levels[$level->getValue()] = $level->getLabel();
			}
		}
	}
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('school_information_id', 'hidden', array('required' => true));
        $builder->add('label', 'text', array('required' => true));
        $builder->add('levels', 'choice', array(
			'required'	=> true,
			'multiple'	=> true,
			'expanded'	=> true,
			'choices'	=> $this->levels
		));
    }

	/**
	 * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\RegistrationBundle\Form\Model\ClassRoomFormModel',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'classroom_registration_form';
    }
}
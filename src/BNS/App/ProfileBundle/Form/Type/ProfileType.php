<?php

namespace BNS\App\ProfileBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;

class ProfileType extends AbstractType
{
	const FORM_NAME = 'profile_form';
	
	/**
	 * @var User représente l'user courant à qui l'on veut modifier son profil 
	 */
	private $user;

	public function __construct(User $user = null)
	{
		$this->user = $user;
	}
        
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('avatarId', 'hidden', array(
				'required'	=> false
		));
		
		$isPupil = (0 == strcmp('pupil', BNSAccess::getContainer()->get('bns.user_manager')->setUser($this->user)->getMainRole()));
		$builder->add('birthday', 'birthday', array(
			'days'		=> range(1, 31),
			'months'	=> range(1, 12),
			'years'		=> range(date('Y', time()) - ($isPupil? 30 : 100), date('Y', time())),
			'format'	=> 'dd MMMM yyyy',
			'widget'	=> 'choice',
			'input'		=> 'datetime',
			'required'	=> false
		));

		if (0 == strcmp('teacher', BNSAccess::getContainer()->get('bns.user_manager')->setUser($this->user)->getMainRole())) {
			$builder->add('email', 'email', array(
				'required'	=> true,
			));
		}

		$builder->add('job', 'text');
		$builder->add('description', 'textarea');
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ProfileBundle\Form\Model\ProfileFormModel',
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
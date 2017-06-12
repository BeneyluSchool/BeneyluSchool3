<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Intl\Intl;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;

class ProfileType extends AbstractType
{
	const FORM_NAME = 'profile_form';

	/**
	 * @var User représente l'user courant à qui l'on veut modifier son profil
	 */
	private $user;

    /**
     * @var array représente la liste des langues disponibles dans l'application
     */
    private $availableLanguages;

	public function __construct(User $user = null, $availableLanguages = null)
	{
		$this->user = $user;
        $this->availableLanguages = $availableLanguages;
    }

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
                $builder->add('firstName', 'text', array(
			'required'	=> true
		));
                $builder->add('lastName', 'text', array(
			'required'	=> true
		));
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

		if (BNSAccess::getContainer()->get('bns.user_manager')->setUser($this->user)->isAdult()) {
			$builder->add('email', 'email', array(
				'required'	=> true,
			));
            if (null != $this->availableLanguages) {
                $keys = array();
                foreach ($this->availableLanguages as $lang) {
                    $region = null;
                    // TODO use LocalManager
                    if (strpos($lang, '_')) {
                        $temp = explode('_', $lang);
                        $lang = $temp[0];
                        $region = $temp[1];
                    }
                    array_push($keys, ucfirst(Intl::getLanguageBundle()->getLanguageName($lang, $region , $lang)));
                }
                $builder->add('lang', 'choice', array(
                    'choices' => array_combine($this->availableLanguages, $keys),
                    'required' => true
                ));
            }
		}

        $builder->add('job', 'text', array(
            'required' => false
        ));
        $builder->add('description', 'textarea', array(
            'required' => false
        ));

        $builder->add('parentsIdsToDissociate', 'hidden', array(
            'required' => false
        ));
        $builder->add('siblingsIdsToDissociate', 'hidden', array(
            'required' => false
        ));
        if ($isPupil) {
            $builder->add('assistantsIdsToDissociate', 'hidden', array(
                'required' => false,
                'mapped' => false,
            ));
        }

        $builder->add('gender', 'choice', array(
            'choices'   => array(
                'M' => $isPupil ? 'LABEL_BOY' : 'LABEL_MAN',
                'F' => $isPupil ? 'LABEL_GIRL' : 'LABEL_WOMAN',
            ),
            'choice_translation_domain' => 'CLASSROOM',
            'required'  => true,
            'expanded' => true,
            'multiple' => false
        ));
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ClassroomBundle\Form\Model\ProfileFormModel',
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

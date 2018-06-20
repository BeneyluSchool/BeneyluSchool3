<?php

namespace BNS\App\ProfileBundle\Form\Type;

use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Intl\Intl;
use BNS\App\CoreBundle\Translation\TranslatorTrait;


use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\om\BaseUser;

class ProfileType extends AbstractType
{
    use TranslatorTrait;

    const FORM_NAME = 'profile_form';

    /**
     * @var User représente l'user courant à qui l'on veut modifier son profil
     */
    private $user;

    /**
     * @var string représente la timezone actuelle de l'utilisateur
     */
    private $currentTimezone;

    /**
     * @var string représente la langue actuelle de l'utilisateur
     */
    private $currentLanguage;


    public function __construct(User $user = null, $parameters = null)
    {
        $this->user = $user;
        $this->currentTimezone = $parameters['timezone'];
        $this->currentLanguage = $parameters['lang'];
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'avatarId',
            'hidden',
            array(
                'required' => false
            )
        );

        // TODO remove this BNSAccess, inject service or made the type a service
        $container = BNSAccess::getContainer();
        $rm = $container->get('bns.right_manager');
        $um = $container->get('bns.user_manager')->setUser($this->user);
        $groupManager = $rm->getCurrentGroupManager();
        $mainRole = $um->getMainRole();
        $groupCountry = $rm->getCurrentGroup()->getCountry();
        if (!$groupCountry) {
            $groupCountry = 'FR';
        }

        $translator = $this->getTranslator();
        if (!$um->isChild()) {
            $builder->add('firstName', 'text', array('required' => true, 'label'=>' '));
            $builder->add('lastName', 'text', array('required' => true, 'label'=>' '));
            $builder->add('gender', 'choice' , array(
                'choices' => array(
                    'M' => 'LABEL_MAN',
                    'F' => 'LABEL_WOMAN',
                ),
                'expanded' => true,
                'multiple' => false,
                'choice_translation_domain' => 'PROFILE',
                'label'=>' '
            ));

            $currentLang = null !== $this->currentLanguage ? $this->currentLanguage : 'English';
            $builder->add('lang', 'available_locale', array(
                'required' => true,
                'data' => $currentLang,
                'label' => 'LABEL_CHANGE_LANGUAGE'
            ));

            $timezone = null !== $this->currentTimezone ? new \DateTimeZone($this->currentTimezone) : new \DateTimeZone('Europe/Paris');

            $builder->add('timezone', 'timezone', array(
                'expanded' => false,
                'multiple' => false,
                'data' => $timezone->getName(),
                'label' => 'LABEL_CHANGE_TIMEZONE'
            ));
        }

        $isPupil = 'pupil' === $mainRole;

        if ($rm->hasRight('PROFILE_ADMINISTRATION')) {
            $builder->add(
                'birthday',
                'date',
                array(
                    'input' => 'datetime',
                    'widget' => 'single_text',
                    'required' => false,
                    'attr' => array('class' => 'jq-date', 'placeholder' => 'DATE_PLACEHOLDER'),
                    'label' => ' '
                )
            );
        }

        if ($um->isAdult()) {
            $builder->add(
                'email',
                'email',
                array(
                    'required' => true,
                    'attr' => array('placeholder' => 'EMAIL'),
                    'label' => 'EMAIL'
                )
            );
            $builder->add('phone', 'text',  [
                'constraints' => [
                    new PhoneNumber([
                        'type' => PhoneNumber::MOBILE,
                        'defaultRegion' => $groupCountry,
                        'groups' => 'sms'
                    ])
                ]
            ]);
            $builder->add('address', 'text', array('required' => false));
            $builder->add('organization', 'text', array('required' => false));
            $builder->add('publicData', 'checkbox', array('required' => false));
            if ($mainRole !== 'parent') {
                $builder->add('email_private', 'email',
                    array(
                        'required' => false,
                        'attr' => array('placeholder' => 'EMAIL_USE_TO_SEND_NOTIFICATION'),
                        'label' => 'EMAIL_SECONDARY'
                    )
                );
            }
        }

        if ($mainRole !== 'parent') {
            $builder->add('job', 'text', array('label'=>' '));
            $builder->add('description', 'textarea', array('label' => 'WRITING_INTRODUCTION', 'attr' => array('placeholder' => '', 'rows' => '3')));
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'BNS\App\ProfileBundle\Form\Model\ProfileFormModel',
                'translation_domain' => 'PROFILE'
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}

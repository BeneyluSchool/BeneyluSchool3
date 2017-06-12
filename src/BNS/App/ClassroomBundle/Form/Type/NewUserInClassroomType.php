<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;


class NewUserInClassroomType extends AbstractType
{
    const FORM_NAME = 'new_user_classroom_form';

    private $isNewTeacher;
    private $withBirthdate;

    public function __construct($isNewTeacher = false, $withBirthdate = true)
    {
        $this->isNewTeacher = $isNewTeacher;
        $this->withBirthdate = $withBirthdate;

    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Nom
        $builder->add('last_name', 'text', array(
            'label'	=>  'LABEL_NAME',
            'required'	=> true,
        ));

        // Prénom
        $builder->add('first_name', 'text', array(
            'label'		=> 'LABEL_FIRSTNAME',
            'required' => true,
        ));

        // Sexe
        $builder->add('gender', 'choice', array(
            'multiple' => false,
            'expanded' => true,
            'label'		=> 'LABEL_GENDER',
            'choices' 	=> array(
                'M' => $this->isNewTeacher? 'LABEL_MAN' : 'LABEL_BOY',
                'F' => $this->isNewTeacher? 'LABEL_WOMAN' : 'LABEL_GIRL',
            ),
            'required' 	=> true,
            'data' => 'M'
        ));

        // Date d'anniversaire
        if($this->withBirthdate)
        {
            $builder->add('birthday', 'birthday', array(
                'years'		=> range(date('Y') - ($this->isNewTeacher? 80 : 18), date('Y')),
                'label'		=> 'LABEL_USER_BIRTH',
                'format'	=> 'dd/MMMM/yyyy',
                'required' 	=> false,
            ));
        }

        // Email
        if (true === $this->isNewTeacher) {
            $builder->add('email', 'email', array(
                'label'		=> 'LABEL_EMAIL',
                'required' => true,
            ));
        }
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\ClassroomBundle\Form\Model\NewUserInClassroomFormModel',
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

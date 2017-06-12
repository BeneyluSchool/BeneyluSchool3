<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class UserRegistrationStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', 'text', array(
                'label' => 'LABEL_TYPE_CLASS_NAME',
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_CLASS_NAME')),
                )
        ));

        if ($options['locale']) {
            $builder->add('level', 'classroom_level', [
                'locale' => $options['locale'],
                'expanded' => true,
                'multiple' => true,
                'attr' => ['class' => 'layout-gt-sm-row layout-wrap'],
                'choice_attr' => function (){
                return ['class' => 'flex-gt-sm-25'];
                }
            ]);
        }

        $builder->add('skip', 'submit');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'USER',
            'locale' => null,
        ));
    }

    public function getName()
    {
        return 'form_user_registration_step_1';
    }
}

<?php
namespace BNS\App\UserBundle\Form\Type;

use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class NameEmailType extends AbstractType
{
    protected $userManager;

    public function __construct(BNSUserManager $userManager = null)
    {
        $this->userManager = $userManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first_name', 'text', array(
                'attr' => array(
                        'placeholder' => 'LABEL_FIRST_NAME',
                ),
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_EMPTY_FIRST_NAME')),
                )
        ));

        $builder->add('last_name', 'text', array(
                'attr' => array(
                        'placeholder' => 'LABEL_LAST_NAME',
                ),
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_EMPTY_LAST_NAME')),
                )
        ));

        $builder->add('civility', 'choice', array(
            'choices'   => array('M' => 'LABEL_MISTER', 'F' => 'LABEL_MADAME'),
            'expanded' => true
        ));

        $builder->add('email', 'email', array(
                'attr' => array(
                        'placeholder' => 'LABEL_EMAIL',
                ),
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_EMPTY_EMAIL')),
                        new Email(array('message' => 'INVALID_WRONG_EMAIL')),
                )
        ));

        $choiceList = new ObjectChoiceList( $options['modules'], 'label', array(), null, 'id');

        $builder->add('modules', 'choice', array(
            'choice_list'  =>$choiceList,
            'expanded' => true,
            'multiple' => true,
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'modules' => array(),
                'translation_domain' => 'USER',
            'constraints' =>  new Callback(array(
                'callback' => function($data, ExecutionContextInterface $context) {
                    $email = $data['email'];
                    $user = $this->userManager->getUser();
                    if ($this->userManager->isAdult() && !empty($email)) {
                        $emailUser = $this->userManager->getUserByEmail($email);
                        if ($emailUser && $emailUser->getId() !== $user->getId()) {
                            $context->buildViolation('EMAIL_ALREADY_USED')
                                ->atPath('[email]')
                                ->setTranslationDomain('CLASSROOM')
                                ->addViolation();
                        }
                    }
                }
            ))
        ));
    }

    public function getName()
    {
        return 'form_name_email_type';
    }
}

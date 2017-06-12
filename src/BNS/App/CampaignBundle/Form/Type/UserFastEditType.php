<?php

namespace BNS\App\CampaignBundle\Form\Type;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumberValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserFastEditType extends AbstractType
{
    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var string
     */
    private $groupCountry;

    public function __construct(BNSUserManager $userManager, $groupCountry)
    {
        $this->userManager = $userManager;
        $this->groupCountry = $groupCountry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first_name', 'text', [
            'constraints' => [
                new NotBlank([
                    'groups' => array('Default', 'sms', 'email')
                ])]
        ]);
        $builder->add('last_name', 'text', [
            'constraints' => [
                new NotBlank([
                    'groups' => array('Default', 'sms', 'email')
                ])]
        ]);
        $builder->add('email','text', [
            'constraints' => [
                new Email([
                    'groups' => array('Default', 'sms', 'email')
                ])
            ]
        ]);
        $builder->add('phone', 'text', [
            'constraints' => [
                new PhoneNumber([
                    'type' => PhoneNumber::MOBILE,
                    'defaultRegion' => $this->groupCountry,
                    'groups' => 'sms'
                ])
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\User',
            'constraints' => [
                new Callback(['callback' =>[$this, 'validateMail'], 'groups' =>['Default', 'sms', 'email']])
            ]
        ));
    }

    public function validateMail(User $data, ExecutionContextInterface $context)
    {
        if (!$data->getEmail()) {
            return;
        }

        // email is unique ?
        $exists = $this->userManager->getUserByEmail($data->getEmail());

        if ($exists && $data->getId() !== $exists->getId()) {
            $context->buildViolation('INVALID_EMAIL_ALREADY_EXIST')->atPath('email')
                ->setTranslationDomain('JS_CAMPAIGN')->addViolation();
        }
    }

    public function getName()
    {
        return 'fast_edit_user';
    }
}

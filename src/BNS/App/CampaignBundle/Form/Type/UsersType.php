<?php

namespace BNS\App\CampaignBundle\Form\Type;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UsersType extends AbstractType
{
    /**
     * @var BNSUserManager
     */
    private $userManager;

    protected $groupCountry;

    public function __construct(BNSUserManager $userManager, $groupCountry)
    {
        $this->userManager = $userManager;
        $this->groupCountry = $groupCountry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('users', 'collection', array(
            'type' => new UserFastEditType($this->userManager, $this->groupCountry),
            'allow_add' => true,
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'constraints' => [
                new Callback(['callback' =>[$this, 'validateMassMail'], 'groups' =>['Default', 'sms', 'email']])
            ]
        ));
    }

    public function validateMassMail($data, ExecutionContextInterface $context)
    {
        if (!isset($data['users'])) {
            return;
        }

        $emails = [];

        /**
         * @var User $user
         */
        foreach ($data['users'] as $key => $user) {
            if (!$user->getEmail()) {
                continue;
            }
            if (in_array($user->getEmail(), $emails)) {
               $context->buildViolation('duplicate email')->atPath('users['.$key.']')->addViolation();
            } else {
                $emails[] = strtolower($user->getEmail());
            }
        }
    }

    public function getName()
    {
        return 'fast_edit_users';
    }
}

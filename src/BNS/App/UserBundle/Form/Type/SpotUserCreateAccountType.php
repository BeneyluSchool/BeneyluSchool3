<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class SpotUserCreateAccountType extends UserRegistrationStep1Type
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('email', 'email', [
            'constraints' => [
                new NotBlank([
                    'message' => 'ERROR_INVALID_EMAIL',
                    'groups' => ['Default, SpotUserCreate']
                ]),
                new Email([
                    'message' => 'ERROR_INVALID_EMAIL',
                    'groups' => ['Default, SpotUserCreate']
                ])
            ]
        ]);

        $builder->add('origin', 'text');

        $builder->add('classroom', new UserRegistrationStep2Type());

        $builder->add('school', new UserRegistrationStep3Type(), [
            'with_address' => true
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => 'USER',
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'spot_user_create_account_type';
    }

}

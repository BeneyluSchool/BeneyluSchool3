<?php
namespace BNS\App\LsuBundle\Form;

use BNS\App\LsuBundle\Model\LsuPositionPeer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuCommentType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('lsuDomain', null, [
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Callback('validateDomainWithCode')
            ]
        ]);

        $builder->add('comment');
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\LsuComment',
            'projects' => [],
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_comment_type';
    }

}

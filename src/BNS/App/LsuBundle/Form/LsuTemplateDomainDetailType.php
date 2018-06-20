<?php
namespace BNS\App\LsuBundle\Form;

use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuTemplateDomainDetailType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label');

        $builder->add('lsuDomain', 'model', [
            'class' => 'BNS\App\LsuBundle\Model\LsuDomain',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Callback('validateDomainWithCode')
            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\LsuTemplateDomainDetail',
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_template_domain_detail_type';
    }

}

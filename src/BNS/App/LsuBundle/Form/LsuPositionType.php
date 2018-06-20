<?php
namespace BNS\App\LsuBundle\Form;

use BNS\App\LsuBundle\Model\LsuPositionPeer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuPositionType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('lsuDomain');

        $builder->add('achievement', 'choice', [
            'choices' => LsuPositionPeer::getValueSet(LsuPositionPeer::ACHIEVEMENT),
            'choices_as_values' => true,
            'expanded' => false,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\LsuPosition',
            'projects' => [],
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_position_type';
    }

}

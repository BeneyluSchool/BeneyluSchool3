<?php
namespace BNS\App\LsuBundle\Form;

use BNS\App\LsuBundle\Model\LsuPeer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('validated', 'checkbox');

        $builder->add('accompanyingConditionOther');

        $builder->add('projects', 'choice', [
            'choices' => $options['projects'],
            'multiple' => true,
            'expanded' => false,
        ]);

        $builder->add('accompanyingCondition', 'choice', [
            'choices' => LsuPeer::getAccompanyingConditions(),
            'multiple' => true,
            'expanded' => false,
        ]);

        $builder->add('lsuComments', 'collection', [
            'type' => new LsuCommentType(),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);

        $builder->add('lsuPositions', 'collection', [
            'type' => new LsuPositionType(),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);

        $builder->add('globalEvaluation');

        $builder->add('data');
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\Lsu',
            'projects' => [],
            'validation_groups' => function (FormInterface $form) {
                $data = $form->getData();

                // when attempting to validate a LSU, use additional constraints
                if ($data->getValidated()) {
                    return ['Default', 'validating'];
                }

                return ['Default'];
            },
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_type';
    }

}

<?php

namespace BNS\App\CorrectionBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('comment', 'textarea', [
            'purify' => false,
            'required' => false
        ]);

        $builder->add('correctionAnnotations', 'collection', [
            'type' => 'correction_annotation_type',
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'options' => [
                'label' => false,
            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->setRendered();
    }


    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\CorrectionBundle\Model\Correction'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'correction_type';
    }

}

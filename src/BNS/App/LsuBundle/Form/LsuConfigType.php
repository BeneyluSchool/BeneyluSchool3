<?php
namespace BNS\App\LsuBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuConfigType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (true === $options['create']) {
            $builder->add('lsuLevel');
        }

        $builder->add('user_ids', 'choice', [
            'choices' => $options['user_ids'],
            'choices_as_values' => true,
            'multiple' => true,
            'expanded' => false,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\LsuConfig',
            'user_ids' => [],
            'create' => false,
            'translation_domain' => 'JS_LSU',
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_config';
    }

}

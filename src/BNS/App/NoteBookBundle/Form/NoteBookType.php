<?php
namespace BNS\App\NoteBookBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class NoteBookType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title');
        $builder->add('date', 'date', array(
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => "dd/MM/yyyy",
                'attr' => array('class' => 'jq-date', 'placeholder' => 'jour / mois / année'),
                'required' => true
                ));
        $builder->add('content');
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array('data_class' => 'BNS\App\NoteBookBundle\Model\NoteBook',));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'notebook_form';
    }
}

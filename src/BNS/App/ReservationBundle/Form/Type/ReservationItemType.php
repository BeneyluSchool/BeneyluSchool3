<?php
namespace BNS\App\ReservationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ReservationItemType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title');

        $builder->add('type', 'choice', array(
                'choices' => array('ROOM' => 'Salle', 'ITEM' => 'Matériel'),
                'expanded' => true,
                ));

    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                ));

    }

    public function getName()
    {
        return 'reservation_item_form';
    }

}

<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ApiWorkshopAudioType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('media', 'api_media');

        $builder->add('user_ids', 'text', array(
            'mapped' => false,
        ));
        $builder->add('group_ids', 'text', array(
            'mapped' => false,
        ));

        $builder->add('data', 'file', array(
            'mapped' => false,
        ));

        // enable __call, for delegate behavior
        $builder->setDataMapper(new PropertyPathMapper(
            PropertyAccess::createPropertyAccessorBuilder()
                ->enableMagicCall()
                ->getPropertyAccessor()
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopAudio'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'workshop_audio';
    }

}

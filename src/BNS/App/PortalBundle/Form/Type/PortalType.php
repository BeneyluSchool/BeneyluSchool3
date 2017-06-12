<?php

namespace BNS\App\PortalBundle\Form\Type;

use BNS\App\PortalBundle\Manager\PortalManager;
use Propel\PropelBundle\Form\BaseAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class PortalType extends BaseAbstractType
{
    protected $options = array(
        'data_class' => 'BNS\App\PortalBundle\Model\Portal',
        'name'       => 'portal',
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label');
        $builder->add('backgroundSmallId', 'hidden', array('required' => false));
        $builder->add('backgroundMediumId', 'hidden', array('required' => false));
        $builder->add('backgroundLargeId', 'hidden', array('required' => false));
        $builder->add('logoId', 'hidden', array('required' => false));
        $builder->add('font','choice', array('choices' => PortalManager::$fonts));
        $builder->add('color','choice', array('choices' => PortalManager::$colors));

    }

    /**
 * @param \BNS\App\PortalBundle\Form\Type\OptionsResolverInterface $resolver
 */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'PORTAL'
        ));
    }

    public function getName()
    {
        return "group";
    }
}

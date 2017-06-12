<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\FormBuilderInterface;
use \Symfony\Component\OptionsResolver\OptionsResolverInterface;



class MiniSiteNewsStatusType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
            $statuses = MiniSitePageNewsPeer::getValueSet(MiniSitePageNewsPeer::STATUS);
            $builder->add('status', 'choice', array(
                'choices'	=> array_combine($statuses, $statuses),
                'expanded'	=> true,
                'label' => ' ',
                'multiple' => true,
            ));

    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'MINISITE',
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'mini_site_news_status_form';
    }
}

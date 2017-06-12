<?php

namespace BNS\App\BoardBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class BoardRssType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title');
        $builder->add('url', 'text');
        $builder->add('enable', 'choice', array(
			'choices' => array('0' => 'Oui', '1' => 'Non'),
			'expanded' => true
		));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'board_rss_form';
    }
}

<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use BNS\App\ClassroomBundle\Model\GroupBlackboard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class GroupBlackboardType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text', array(
            'required'	=> true,
        ));
        $builder->add('description');
        $builder->add('notice');
        $builder->add('flux', 'choice', array(
            'required'	=> true,
            "multiple" => false,
            'choices'  => array(
                GroupBlackboard::FLUX_BLOG_ONLY => 'BACKBOARD_FLUX_LABEL_' . GroupBlackboard::FLUX_BLOG_ONLY,
                GroupBlackboard::FLUX_BLOG_MEDIA_LIBRARY => 'BACKBOARD_FLUX_LABEL_' . GroupBlackboard::FLUX_BLOG_MEDIA_LIBRARY,
                GroupBlackboard::FLUX_ALL => 'BACKBOARD_FLUX_LABEL_' . GroupBlackboard::FLUX_ALL,
            ),
            'translation_domain' => 'CLASSROOM'
        ));
        $builder->add('image_id', 'hidden');
    }

    public function getName()
    {
        return 'group_blackboard';
    }
}

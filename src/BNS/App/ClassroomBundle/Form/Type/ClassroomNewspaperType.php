<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Propel\PropelBundle\Form\BaseAbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ClassroomNewspaperType extends BaseAbstractType
{
    protected $options = array(
        'data_class' => 'BNS\App\ClassroomBundle\Model\ClassroomNewspaper',
        'name'       => 'classroomnewspaper',
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('date','date', array(
            'widget' => 'choice',
        ));
        $builder->add('title');
        $builder->add('media_title');
        $builder->add('media_preview_id','hidden');
        $builder->add('media_id','hidden');
        $builder->add('media_preview_id','hidden');
        $builder->add('caption', null, array('required' => false));
        $builder->add('joke','textarea',array('attr' => array('bns-tinymce' => '')));
        $builder->add('riddle','textarea',array('attr' => array('bns-tinymce' => '')));
        $builder->add('riddleAnswer','textarea',array('attr' => array('bns-tinymce' => '')));
        $builder->add('text','textarea',array('attr' => array('bns-tinymce' => '')));
        $builder->add('day_read','textarea',array('attr' => array('bns-tinymce' => ''),'required'=>false));
        $builder->add('lended_by', null, array('required' => false));

    }
}

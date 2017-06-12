<?php
namespace BNS\App\ForumBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupQuery;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class MessageType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['is_subject']) {
            $builder->add('title', 'text', array(
                    'property_path' => 'forumSubject.title'
                    ));
        }

        $builder->add('content');

    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        return $resolver->setDefaults(array(
                'data_class' => 'BNS\App\ForumBundle\Model\ForumMessage',
                'is_subject' => false,
                'is_edit' => false,
                ));

    }

    public function getName()
    {
        return 'forum_form_type';
    }

}

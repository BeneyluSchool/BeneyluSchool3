<?php


namespace BNS\App\BlogBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Julie Boisnard <julie.boisnard@pixel-cookers.com>
 */
class BlogCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text', array('required' => false));
        $builder->add('icon_classname', 'choice', [
            'choices' => $options['icons'],
            'required' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
           'class' => 'BNS\App\CoreBundle\Model\BlogCategory',
            //ToDo load icon list from a manager
            'icons' => ['default' => 'default']
        ]);
    }

    public function getName()
    {
        return 'blog_category';
    }

}

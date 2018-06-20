<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 16/02/2017
 * Time: 17:11
 */

namespace BNS\App\CompetitionBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden');
        $builder->add('title', 'text');
        $builder->add('author', 'text');
        $builder->add('media_id', 'integer', array(
            'required' => false
        ));
        $builder->add('authorize_answers', 'checkbox');
        $builder->add('authorize_questionnaires', 'checkbox');
        $builder->add('authorize_notices', 'checkbox');
        $builder->add('questionnaires', 'hidden');
        $builder->add('notice_id', 'integer', array(
            'required' => false
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CompetitionBundle\Model\Book'
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return "book";
    }
}

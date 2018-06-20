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

class CompetitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text');
        $builder->add('description', 'textarea');
        $builder->add('status', 'choice',
            array("choices" => array(
                'DRAFT' => '0',
                'PUBLISHED' => '1',
                'FINISHED' => '2'))
        );
        $builder->add('media_id', 'integer',
            array('required' => false));
        $builder->add('authorize_answers', 'checkbox');
        $builder->add('authorize_questionnaires', 'checkbox');
        $builder->add('questionnaires', 'hidden');
        $builder->add('books', 'collection', array(
            'type' => new BookType(),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ));
        $builder->add('user_ids', 'hidden', array('mapped' => false));
        $builder->add('group_ids', 'hidden', array('mapped' => false));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CompetitionBundle\Model\Competition'
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return "competition";
    }
}

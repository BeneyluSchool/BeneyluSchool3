<?php
namespace BNS\App\LsuBundle\Form;

use BNS\App\LsuBundle\Model\LsuConfigQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuTemplateType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['group_id']) {
            $builder->add('lsu_config', 'model', [
                'class' => 'BNS\App\LsuBundle\Model\LsuConfig',
                'query' => LsuConfigQuery::create()->filterByGroupId($options['group_id'], \Criteria::EQUAL)
            ]);
        }

        $builder->add('period');

        $builder->add('teacher');

        $builder->add('year', 'number');

        $builder->add('is_open', 'checkbox');

        $builder->add('is_cycle_end', 'checkbox');

        $builder->add('started_at', 'date', [
            'widget' => 'single_text',
        ]);

        $builder->add('ended_at', 'date', [
            'widget' => 'single_text',
        ]);

        $builder->add('data');
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\LsuBundle\Model\LsuTemplate',
            'group_id' => null
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'lsu_template_type';
    }

}

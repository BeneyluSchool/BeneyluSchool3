<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $query = GroupTypeQuery::create()->filterBySimulateRole(false);
        if (!$options['isAdminStrong']) {
            $query->filterByType('ENVIRONMENT', \Criteria::NOT_EQUAL);
        }

        $builder->add('group_type',  'model', array(
            'class' => 'BNS\App\CoreBundle\Model\GroupType',
            'query' => $query,
            'label' => "LABEL_GROUP_TYPE"
        ));

        $builder->add('label', 'text', array('label' => 'LABEL_NAME'));

    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'BNS\App\CoreBundle\Model\Group',
                'translation_domain' => 'CORE',
                'isAdminStrong' => false
            )
        );
    }

    public function getName()
    {
        return 'group_type';
    }
}

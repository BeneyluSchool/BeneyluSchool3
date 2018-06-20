<?php
namespace BNS\App\ForumBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupQuery;

use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ForumType extends AbstractType
{
    /**
     * @var array
     */
    private $userIds;
    /**
     * @var array
     */
    private $groups;

    /**
     * ForumType constructor.
     */
    public function __construct($userIds = array(), $groups = array())
    {
        $this->userIds = $userIds;
        $this->groups = $groups;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title');

        $builder->add('is_public', 'choice', array(
                'label' => 'Visibilité',
                'choices' => array('0' => 'Privé', '1' => 'Public'),
                'expanded' => true
                ));
        $builder->add('subscription_required', 'choice', array(
                'label' => 'Contrôler l\'inscription',
                'choices' => array('0' => 'Non', '1' => 'Oui'),
                'expanded' => true
                ));
        $builder->add('unsubscribing_allowed', 'choice', array(
                'label' => 'Désinscription authorisé',
                'choices' => array('0' => 'Non', '1' => 'Oui'),
                'expanded' => true
                ));
        $builder->add('is_moderated', 'choice', array(
                'label' => 'Modération activé',
                'choices' => array('0' => 'Non', '1' => 'Oui'),
                'expanded' => true
                ));
        $builder->add('validatedUsers', 'model', [
            'class' => 'BNS\\App\\CoreBundle\\Model\\User',
            'multiple' => true,
            'expanded' => true,
            'query' => UserQuery::create()->filterById($this->userIds, \Criteria::IN)
        ]);

        if ($options['is_edit']) {
            $builder->add('closed_at', 'date', array(
                    'widget'    => 'single_text',
                    'required'  => false,
                    'format'    => 'dd/MM/yyyy',
                    'attr' => array('class' => 'jq-date', 'placeholder' => 'jour / mois / année'),
            ));
            $builder->add('closed_until', 'date', array(
                    'widget'    => 'single_text',
                    'required'  => false,
                    'format'    => 'dd/MM/yyyy',
                    'attr' => array('class' => 'jq-date', 'placeholder' => 'jour / mois / année'),
            ));
            $builder->add('archive_after_closed', 'choice', array(
                    'label' => 'Archivage automatique ( nombre de mois après la fermeture)',
                    'choices' => array('1' => '1 mois', '2' => '2 mois', '6' => '6 mois', '12' => '12 mois'),
                    'expanded' => true
            ));
        } else {
            $builder->add('group', 'model', array(
                    'class' => 'BNS\App\CoreBundle\Model\Group',
                    'expanded' => true,
                    'query' => GroupQuery::create()->orderByLabel()->filterById($options['groups']),
            ));
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        return $resolver->setDefaults(array(
                'data_class' => 'BNS\App\ForumBundle\Model\Forum',
                'is_edit' => false,
                'groups' => array(),
                ));

    }

    public function getName()
    {
        return 'forum_form_type';
    }

}

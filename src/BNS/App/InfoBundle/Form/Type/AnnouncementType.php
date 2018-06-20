<?php

namespace BNS\App\InfoBundle\Form\Type;

use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\InfoBundle\Model\AnnouncementPeer;
use BNS\App\InfoBundle\Model\AnnouncementUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AnnouncementType extends AbstractType
{

    /**
     * @var array
     */
    protected $languages;

    public function __construct(array $languages = array())
    {
        $this->languages = $languages;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('announcement_i18ns', 'propel1_translation_collection', array(
            'languages' => $this->languages,
            'options' => array(
                'data_class' => 'BNS\\App\\InfoBundle\\Model\\AnnouncementI18n',
                'columns' => array(
                    'label' => array(
                        'type' => 'text',
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'options' => array(
                            'attr' => array('bns-tinymce' => ''),
                        ),
                    ),
                ),
            ),
        ));
        $enum = AnnouncementPeer::getValueSet(AnnouncementPeer::TYPE);
        $builder->add('type','choice', array(
            'choices' => array_combine($enum, $enum),
            'choice_label' => function ($value) {
                $labels = [
                    'CUSTOM' => 'Notification',
                    'HOME' => 'Push home',
                ];
                return $labels[$value] ?? $value;
            },
            'required'  => true,
            'expanded'  => false
        ));
        $builder->add('participable','choice', array(
            'choices'   => array(true => 'CHOICE_YES',false => 'CHOICE_NO'),
            'required'  => true,
        ));
        $builder->add('participation_label','text',array('required' => false));
        $builder->add('resource_id','hidden',array('attr' => array('id' => 'announcement_resource_id')));
        $builder->add('is_active','choice', array(
            'choices'   => array(true => 'CHOICE_YES',false => 'CHOICE_NO'),
            'required'  => true,
        ));

        $formatedPermissions = [];
        $permissions = PermissionQuery::create()
            ->orderByModuleId()
            ->joinWith('Module')
            ->find()
        ;
        foreach($permissions as $permission){
            $formatedPermissions[$permission->getModule()->getLabel()][$permission->getUniqueName()] = $permission->getLabel();
        }
        $builder->add('permission_unique_name', 'choice', array(
            'choices' => $formatedPermissions,
            'required' => false,
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\InfoBundle\Model\Announcement',
            'translation_domain' => 'INFO'
        ));
    }
	
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'announcement';
    }
}

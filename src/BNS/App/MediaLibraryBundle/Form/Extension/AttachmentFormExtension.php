<?php

namespace BNS\App\MediaLibraryBundle\Form\Extension;

use Symfony\Bridge\Propel1\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AttachmentFormExtension extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CollectionToArrayTransformer());
    }

    /**
     * @inheritDoc
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($parent = $form->getParent()) {
            $object = $parent->getData();
            if ($object && $object instanceof \BaseObject && $object) {
                $view->vars['objectId'] =  $object->getPrimaryKey();
                $view->vars['objectClass'] =  $object->getMediaClassName();
                $view->vars['medias'] = $object->getAttachments();
            }
        }

        if (isset($view->vars['full_name'])) {
            $view->vars['full_name'] .= '[]';
        }
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'type' => 'media_id_type',
            'allow_add' => true,
            'allow_remove' => true,
            'label' => false,
            'by_reference' => false,
            'delete_empty' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getParent()
    {
        return 'collection';
    }


    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'media_attachments';
    }

}

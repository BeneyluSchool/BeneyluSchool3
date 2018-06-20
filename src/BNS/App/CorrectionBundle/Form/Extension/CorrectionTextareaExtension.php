<?php

namespace BNS\App\CorrectionBundle\Form\Extension;

use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CorrectionBundle\Form\Type\CorrectionType;
use BNS\App\CorrectionBundle\Manager\CorrectionManager;
use BNS\App\CorrectionBundle\Model\Correction;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class PurifyTextareaExtension
 *
 * @package BNS\App\CoreBundle\Form\Extension
 */
class CorrectionTextareaExtension extends AbstractTypeExtension
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var CorrectionManager
     */
    protected $correctionManager;

    /**
     * CorrectionTextareaExtension constructor.
     * @param TokenStorageInterface $tokenStorage
     * @param CorrectionManager $correctionManager
     */
    public function __construct(TokenStorageInterface $tokenStorage, CorrectionManager $correctionManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->correctionManager = $correctionManager;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'correction_object_class' => null,
            'correction_edit' => false,
            'correction_group_id' => null,
        ));
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['correction_edit']) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($options) {
                $form = $event->getForm();
                if ($parent = $form->getParent()) {
                    if ($user = $this->getUser()) {
                        $objectClass = $options['correction_object_class'] ?: $parent->getConfig()->getDataClass();
                        if ($this->correctionManager->hasCorrectionEditRight($objectClass, $user, $options['correction_group_id'])) {
                            $parent->add('correction', 'correction_type');
                        }
                    }
                }
            });

            $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
                $user = $this->getUser();
                if (!$user || in_array($user->getHighRoleId(), GroupTypePeer::getRoleIds(['PUPIL', 'PARENT']))) {
                    // prevent pupil or parent from saving last modification
                    return ;
                }

                $form = $event->getForm();
                if ($parent = $form->getParent()) {
                    if (!$parent->has('correction')) {
                        return ;
                    }
                    $correctionForm = $parent->get('correction');
                    /** @var Correction $correction */
                    if ($correction = $correctionForm->getData()) {
                        // set last correction use data from the textearea field
                        $correction->setLastCorrection($event->getData());
                        $correction->setLastCorrectionBy($user->getId());
                    }
                }
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // default to false;
        $view->vars['correction'] = false;
        $view->vars['correction_edit'] =  false;

        if ($options['correction_edit']) {
            if ($parent = $form->getParent()) {
                if ($user = $this->getUser()) {
                    $objectClass = $options['correction_object_class'] ? : $parent->getConfig()->getDataClass();
                    $view->vars['correction_edit'] = $this->correctionManager->hasCorrectionEditRight($objectClass, $user, $options['correction_group_id']);
                    if (!$view->vars['correction_edit']) {
                        $view->vars['correction'] = $this->correctionManager->hasCorrectionRight($objectClass, $user, $options['correction_group_id']);
                    }
                }
            }
        }
    }


    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return 'textarea';
    }

    /**
     * @return User|null
     */
    protected function getUser()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }
        /** @var User $user */
        $user = $token->getUser();
        if ($user && $user instanceof User) {
            return $user;
        }

        return null;
    }
}

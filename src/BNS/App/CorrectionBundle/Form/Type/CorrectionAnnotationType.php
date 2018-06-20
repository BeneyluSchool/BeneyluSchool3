<?php

namespace BNS\App\CorrectionBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CorrectionBundle\Model\CorrectionAnnotation;
use BNS\App\MediaLibraryBundle\Validator\Constraints\Attachments;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionAnnotationType extends AbstractType
{
    protected $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', 'text');
        $builder->add('guid', 'text');
        $builder->add('type');
        $builder->add('comment');
        $builder->add('sortableRank');
        $builder->add('attachments', 'media_attachments', [
            'error_bubbling' => true
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event){
            $token = $this->tokenStorage->getToken();
            if (!$token) {
                return;
            }
            /** @var User $user */
            $user = $token->getUser();
            if (!$user || in_array($user->getHighRoleId(), GroupTypePeer::getRoleIds(['PUPIL', 'PARENT']))) {
                return ;
            }

            /** @var CorrectionAnnotation $correctionAnnotation */
            $correctionAnnotation = $event->getData();
            if ($correctionAnnotation && $correctionAnnotation->isNew()) {
                $correctionAnnotation->setUserId($user->getId());
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\CorrectionBundle\Model\CorrectionAnnotation',
            'constraints' => [
                new Attachments()
            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'correction_annotation_type';
    }

}

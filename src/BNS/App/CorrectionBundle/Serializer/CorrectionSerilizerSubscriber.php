<?php

namespace BNS\App\CorrectionBundle\Serializer;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CorrectionBundle\Manager\CorrectionManager;
use BNS\App\CorrectionBundle\Model\CorrectionInterface;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\Metadata\VirtualPropertyMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionSerilizerSubscriber implements EventSubscriberInterface
{
    /** @var  CorrectionManager */
    protected $correctionManager;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(CorrectionManager $correctionManager, TokenStorageInterface $tokenStorage)
    {
        $this->correctionManager = $correctionManager;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.pre_serialize',
                'method' => 'onPreSerialize',
            ],
        ];
    }

    public function onPreSerialize(PreSerializeEvent $event)
    {
        $object = $event->getObject();
        if (!($object instanceof CorrectionInterface)) {
            return ;
        }
        $user = $this->getUser();
        if (!$user) {
            return ;
        }

        if ($this->correctionManager->hasCorrectionEditRight(get_class($object), $user)
        || $this->correctionManager->hasCorrectionRight(get_class($object), $user)) {
            // User can see corrections data we serialize them
            $metadataObject = $event->getContext()->getMetadataFactory()->getMetadataForClass(get_class($object));
            $metadata = new VirtualPropertyMetadata(get_class($object), 'getCorrection');
            $metadata->serializedName = 'correction';
            $metadataObject->addPropertyMetadata($metadata);
        }
    }

    protected function getUser()
    {
        if ($token = $this->tokenStorage->getToken()) {
            if ($user = $token->getUser()) {
                if ($user instanceof User) {
                    return $user;
                }
            }
        }

        return null;
    }

}

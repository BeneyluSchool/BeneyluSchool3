<?php

namespace BNS\App\HomeworkBundle\Serializer;

use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class HomeworkSerializerSubscriber implements EventSubscriberInterface
{
    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
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

}

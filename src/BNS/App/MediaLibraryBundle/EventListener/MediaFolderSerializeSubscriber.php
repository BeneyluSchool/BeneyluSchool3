<?php

namespace BNS\App\MediaLibraryBundle\EventListener;

use BNS\App\CompetitionBundle\Model\AnswerPeer;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipation;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\PaasBundle\Manager\NathanResourceManager;
use BNS\App\PaasBundle\Manager\PaasManager;
use BNS\App\WorkshopBundle\Manager\ContentManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Class WorkshopDocumentSerializeSubscriber
 */
class MediaFolderSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    /**
     * @var NathanResourceManager
     */
    private $nathanResourceManager;

    public function __construct(TokenStorage $tokenStorage, NathanResourceManager $nathanResourceManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->nathanResourceManager = $nathanResourceManager;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerializeMediaFolderGroup',
                'class' => 'BNS\App\MediaLibraryBundle\Model\MediaFolderGroup',
            )
        );
    }

    public function onPostSerializeMediaFolderGroup(ObjectEvent $event)
    {
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['detail', 'media_detail','competition_participation']))) {
            return;
        }

        $rights = array();

        /** @var MediaFolderGroup $mediaFolder */
        $mediaFolder = $event->getObject();

        if ($mediaFolder->getIsExternalFolder() && $user = $this->getUser()) {
            $visitor = $event->getVisitor();
            $visitor->addData('has_nathan', $this->nathanResourceManager->hasResources($user, $mediaFolder->getGroup()));
        }
    }

    protected function getUser()
    {
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user && $user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}

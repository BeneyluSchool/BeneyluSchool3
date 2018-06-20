<?php

namespace BNS\App\CompetitionBundle\EventListener;

use BNS\App\CompetitionBundle\Manager\CompetitionManager;
use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\BookNoticeQuery;
use BNS\App\CompetitionBundle\Model\BookParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Class BookSerializeSubscriber
 */
class BookSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    private $competitionManager;

    public function __construct(TokenStorage $tokenStorage, CompetitionManager $competitionManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->competitionManager = $competitionManager;
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
                'method' => 'onPostSerialize',
                'class' => 'BNS\App\CompetitionBundle\Model\Book',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var Book $book */
        $book = $event->getObject();

        $visitor = $event->getVisitor();
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['book_detail', 'competition_detail']))) {
            return;
        }

        $user = $this->getUser();

        if (null == $user) {
            throw new AccessDeniedHttpException();
        }

        $score = CompetitionParticipationQuery::create()
            ->filterByUser($user)
            ->useCompetitionQuery()
            ->filterByBook($book)
            ->endUse()
            ->select('score')->findOne();

        if ((int)$score > 0) {
            $visitor->addData('is_contributor', true);
        }

        if (in_array('book_statistics', $groups) && !isset($book->percent)) {
            $this->competitionManager->getPercentCompetition($book->getCompetition(), null, null, $user->getId(), null);
            $visitor->addData('percent', $book->percent);
            $visitor->addData('score', $book->score);
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

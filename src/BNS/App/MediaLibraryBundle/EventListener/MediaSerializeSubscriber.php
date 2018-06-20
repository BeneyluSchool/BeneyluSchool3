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
use BNS\App\WorkshopBundle\Manager\ContentManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Class WorkshopDocumentSerializeSubscriber
 */
class MediaSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    /**
     * @var ContentManager $contentManager
     */
    private $contentManager;

    public function __construct(TokenStorage $tokenStorage, ContentManager $contentManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->contentManager = $contentManager;
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
                'class' => 'BNS\App\MediaLibraryBundle\Model\Media',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['detail', 'media_detail','competition_participation']))) {
            return;
        }

        $rights = array();

        /** @var Media $media */
        $media = $event->getObject();
        if ($media->isWorkshopDocument() || $media->isWorkshopQuestionnaire()) {
            $content = $media->getWorkshopContents()->getFirst();
            if ($content) {
                $user = $this->getUser();
                $rights['workshop_document_edit'] = $this->contentManager->canManage($content, $user);
            }
        }

        $visitor = $event->getVisitor();
        $visitor->addData('rights', $rights);
        if (in_array('competition_participation', $groups)) {
            $participation = QuestionnaireParticipationQuery::create()
                ->filterByUserId($this->getUser()->getId())
                ->filterByQuestionnaireId($media->getId())
                ->findOne();

            if ($participation) {
                $competitionQuestionnaire = CompetitionQuestionnaireQuery::create()
                    ->filterByQuestionnaireId($media->getId())
                    ->findOne();
                $remainAttempts = null;
                if ($competitionQuestionnaire) {
                    if (!$competitionQuestionnaire->getAllowAttempts()) {
                        $remainAttempts = -1;
                    } else {
                        $remainAttempts = $competitionQuestionnaire->getAttemptsNumber() - $participation->getTryNumber();
                    }
                } else {
                    $competitionQuestionnaire = CompetitionBookQuestionnaireQuery::create()
                        ->filterByQuestionnaireId($media->getId())
                        ->findOne();
                }
                $percent = AnswerQuery::create()->filterByParticipationId($participation->getId())
                    ->withColumn('SUM('. AnswerPeer::PERCENT .')', "sumpercent")
                    ->select('sumpercent')
                    ->findOne();
                if ($competitionQuestionnaire && $competitionQuestionnaire->getQuestionnaire() && $competitionQuestionnaire->getQuestionnaire()->questionsCount > 0) {
                    $percent = $percent / $competitionQuestionnaire->getQuestionnaire()->questionsCount;
                } else {
                    $percent = 0;
                }
                $participationArray = [
                    "page" => $participation->getPage(),
                    "like" => $participation->getLike(),
                    "finished" => $participation->getFinished(),
                    "try_number" => $participation->getTryNumber(),
                    "score" => $participation->getScore(),
                    "percent" => $percent,
                    "remain_attempts" => $remainAttempts
                ];
                $visitor->addData('participation', $visitor->visitArray($participationArray, ['BNS\App\CompetitionBundle\QuestionnaireParticipation'], $event->getContext()));
            }
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

<?php

namespace BNS\App\CompetitionBundle\Manager;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Manager\ContentManager;
use BNS\App\WorkshopBundle\Manager\WidgetGroupManager;

/**
 * Class CompetitionMediaManager
 *
 * @package BNS\App\CompetitionBundle\Manager
 */
class CompetitionMediaManager
{

    /**
     * @var WidgetGroupManager
     */
    protected $widgetGroupManager;

    /**
     * @var ContentManager
     */
    protected $contentManager;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    public function __construct(WidgetGroupManager $widgetGroupManager, ContentManager $contentManager, BNSGroupManager $groupManager)
    {
        $this->widgetGroupManager = $widgetGroupManager;
        $this->contentManager = $contentManager;
        $this->groupManager = $groupManager;
    }

    /**
     * Copies the given workshop Media and its WorkshopContent, to be used in a Competition.
     *
     * @param Media $media
     * @return Media the copy
     */
    public function copyWorkshopMedia(Media $media)
    {
        if (!$media->isFromWorkshop()) {
            return $media;
        }

        $copy = $media->copy();
        $copy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION);
        $copy->save();
        $content = $media->getWorkshopContent();
        $contentCopy = $this->widgetGroupManager->duplicateContent($content);
        $copy->addWorkshopContent($contentCopy);
        $contentCopy->setMediaId($copy->getId())->save();

        return $copy;
    }

    /**
     * Increments participations for all contributors of the given questionnaire Media, for the given Competition id.
     *
     * @param Media $questionnaire
     * @param int $competitionId
     */
    public function incrementParticipation(Media $questionnaire, $competitionId)
    {
        $content = $questionnaire->getWorkshopContent();
        $allContributorUserIds = [];
        foreach ($this->contentManager->getContributorUsers($content) as $user) {
            if ($user->isChild()) {
                $allContributorUserIds[] = $user->getId();
            }
        }
        foreach ($this->contentManager->getContributorGroups($content) as $group) {
            $allContributorUserIds = array_merge($allContributorUserIds, $this->groupManager->getUserIdsByRole('PUPIL', $group));
        }
        $allContributorUserIds = array_unique($allContributorUserIds);
        foreach ($allContributorUserIds as $userId) {
            $participation = CompetitionParticipationQuery::create()
                ->filterByCompetitionId($competitionId)
                ->filterByUserId($userId)
                ->findOneOrCreate();
            $score = $participation->getScore() ?: 0;
            $participation->setScore($score + 1)->save();
        }
    }

}

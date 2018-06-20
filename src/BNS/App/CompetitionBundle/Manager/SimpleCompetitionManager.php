<?php

namespace BNS\App\CompetitionBundle\Manager;

use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\SimpleCompetition;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

/**
 * Class SimpleCompetitionManager
 *
 * @package BNS\App\CompetitionBundle\Manager
 */
class SimpleCompetitionManager
{

    /**
     * @var CompetitionMediaManager
     */
    protected $competitionMediaManager;

    /**
     * @var CompetitionNotificationManager
     */
    protected $competitionNotificationManager;

    public function __construct(CompetitionMediaManager $competitionMediaManager, CompetitionNotificationManager $competitionNotificationManager)
    {
        $this->competitionMediaManager = $competitionMediaManager;
        $this->competitionNotificationManager = $competitionNotificationManager;
    }

    public function handleCompetition(SimpleCompetition $competition, User $user)
    {
        $this->handleCompetitionQuestionnaires($competition, $user);
        $competition->save();
        if ($competition->getAuthorizeAnswers() && "PUBLISHED" == $competition->getStatus()) {
            $this->competitionNotificationManager->notificateOpenAnswersCompetition($competition);
        }
        return $competition;
    }

    protected function handleCompetitionQuestionnaires(SimpleCompetition $competition, User $user)
    {
        if (!is_array($competition->questionnaires)) {
            return;
        }

        /** @var CompetitionQuestionnaire[]|\PropelObjectCollection $existingLinks */
        $existingLinks = $competition->getCompetitionQuestionnaires();
        /** @var CompetitionQuestionnaire[] $linksToDelete */
        $linksToDelete = $existingLinks->getArrayCopy('QuestionnaireId');

        foreach ($competition->questionnaires as $questionnaireInfo) {
            $media = MediaQuery::create()->findPk($questionnaireInfo['id']);
            if (!$media) {
                continue;
            }
            $link = null;
            foreach ($existingLinks as $existingLink) {
                if ($existingLink->getQuestionnaireId() === $media->getId()) {
                    $link = $existingLink;
                    unset($linksToDelete[$existingLink->getQuestionnaireId()]);
                    break;
                }
            }
            if (!$link) {
                $media = $this->competitionMediaManager->copyWorkshopMedia($media);
                $this->competitionMediaManager->incrementParticipation($media, $competition->getId());
                $link = new CompetitionQuestionnaire();
                $link
                    ->setUser($user)
                    ->setQuestionnaireId($media->getId())
                    ->setCompetitionId($competition->getId())
                    ->setProposer($user->getFullName())
                ;
            }
            $allowAttempts = isset($questionnaireInfo['allow_attempts']) ? $questionnaireInfo['allow_attempts'] : false;
            $attemptsNumber = (isset($questionnaireInfo['max_attempts_number']) && $allowAttempts) ? $questionnaireInfo['max_attempts_number'] : null;

            $link
                ->setAttemptsNumber($attemptsNumber)
                ->setAllowAttempts($allowAttempts)
                ->setValidate(1)
                ->save()
            ;
        }

        // remove CompetitionBookQuestionnaire no longer related
        foreach ($linksToDelete as $link) {
            $link->delete();
            $competition->removeCompetitionQuestionnaire($link);
        }
    }

}

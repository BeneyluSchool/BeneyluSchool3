<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 26/04/2017
 * Time: 18:08
 */

namespace BNS\App\CompetitionBundle\Manager;

use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaire;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

class BookManager
{

    /**
     * @var CompetitionMediaManager
     */
    private $competitionMediaManager;

    /**
     * @var CompetitionNotificationManager
     */
    protected $competitionNotificationManager;

    public function __construct(CompetitionMediaManager $competitionMediaManager, CompetitionNotificationManager $competitionNotificationManager)
    {
        $this->competitionMediaManager = $competitionMediaManager;
        $this->competitionNotificationManager = $competitionNotificationManager;
    }

    public function handleBook(Book $book, User $user)
    {
        $this->handleMedia($book);
        $this->handleBookQuestionnaires($book, $user);
        $this->handleBookNotice($book);
        $book->save();
        if ($book->getAuthorizeAnswers() && "PUBLISHED" == $book->getCompetition()->getStatus()) {
                $this->competitionNotificationManager->notificateOpenAnswersBook($book);
        }
        return $book;
    }

    protected function handleBookQuestionnaires(Book $book, User $user)
    {
        if (!is_array($book->questionnaires)) {
            return;
        }

        /** @var CompetitionBookQuestionnaire[]|\PropelObjectCollection $existingLinks */
        $existingLinks = $book->getCompetitionBookQuestionnaires();
        /** @var CompetitionBookQuestionnaire[] $linksToDelete */
        $linksToDelete = $existingLinks->getArrayCopy('QuestionnaireId');

        // create new CompetitionBookQuestionnaire links and update existing
        foreach ($book->questionnaires as $key => $questionnaireInfo) {
            $media = MediaQuery::create()->findPk($questionnaireInfo['id']);
            if (!$media) {
                continue;
            }
            $link = null;
            foreach ($existingLinks as $existingLink) {
                if ($existingLink->getQuestionnaireId() === $media->getId()) {
                    $link = $existingLink;
                    unset($linksToDelete[$link->getQuestionnaireId()]);
                    break;
                }
            }
            if (!$link) {
                $media = $this->competitionMediaManager->copyWorkshopMedia($media);
                $this->competitionMediaManager->incrementParticipation($media, $book->getCompetitionId());
                $link = new CompetitionBookQuestionnaire();
                $link
                    ->setUser($user)
                    ->setQuestionnaireId($media->getId())
                    ->setBookId($book->getId())
                    ->setProposer($user->getFullName())
                ;
            }
            $allowAttempts = isset($questionnaireInfo['allow_attempts']) ? $questionnaireInfo['allow_attempts'] : false;
            $attemptsNumber = (isset($questionnaireInfo['max_attempts_number']) && $allowAttempts) ? $questionnaireInfo['max_attempts_number'] : null;
            $isRequired = isset($questionnaireInfo['required']) ? $questionnaireInfo['required'] : false;
            $link
                ->setAttemptsNumber($attemptsNumber)
                ->setAllowAttempts($allowAttempts)
                ->setValidate(1)
                ->setRequired($isRequired)
                ->setRank($key + 1)
                ->save()
            ;
        }

        // remove CompetitionBookQuestionnaire no longer related
        foreach ($linksToDelete as $link) {
            $book->removeCompetitionBookQuestionnaire($link);
        }
    }

    protected function handleBookNotice(Book $book)
    {
        $notice = $book->getNotice();
        if (!$notice) {
            return;
        }
        if ($notice->getStatusDeletion() !== 3) {
            $notice = $this->competitionMediaManager->copyWorkshopMedia($notice);
            $book->setNotice($notice);
            $this->competitionMediaManager->incrementParticipation($notice, $book->getCompetitionId());
        }
    }

    protected function handleMedia(Book $book)
    {
        $media = $book->getMedia();
        if (!$media || MediaManager::STATUS_QUESTIONNAIRE_COMPETITION === $media->getStatusDeletion()) {
            return;
        }
        $mediaCopy = $media->copy();
        $mediaCopy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION)->save();
        $book->setMedia($mediaCopy);
    }

}

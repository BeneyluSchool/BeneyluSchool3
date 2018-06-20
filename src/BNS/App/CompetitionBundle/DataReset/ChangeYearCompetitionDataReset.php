<?php

namespace BNS\App\CompetitionBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CompetitionBundle\Form\Type\ChangeYearCompetitionDataResetType;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\BookParticipationQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionGroupQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionUserQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ChangeYearCompetitionDataReset extends AbstractDataReset
{
    /**
     * @var string
     */
    public $choice;
    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    /**
     * ChangeYearCompetitionDataReset constructor.
     * @param TokenStorage $tokenStorage
     */
    public function __construct(TokenStorage $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'change_year_competition';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        $competitionIds = CompetitionQuery::create()->filterByGroupId($group->getId())->select('id')->find();

        if ('KEEP' === $this->choice) {

            $answerIds = AnswerQuery::create()
                ->useQuestionnaireParticipationQuery()
                    ->useMediaQuery()
                        ->useCompetitionQuery()
                            ->filterById($competitionIds, \Criteria::IN)
                        ->endUse()
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()->toArray();

            AnswerQuery::create()->filterById($answerIds)->delete();

            $bookParticipationIds = BookParticipationQuery::create()
                ->useBookQuery()
                    ->useCompetitionQuery()
                        ->filterById($competitionIds, \Criteria::IN)
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()->toArray();

            BookParticipationQuery::create()->filterById($bookParticipationIds)->delete();

            $questionnaireSimpleParticipationIds = QuestionnaireParticipationQuery::create()
                ->useMediaQuery()
                    ->useCompetitionQuery()
                        ->filterById($competitionIds, \Criteria::IN)
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()->toArray();

            $questionnaireReadingParticipationIds = QuestionnaireParticipationQuery::create()
                ->useMediaQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->useBookQuery()
                            ->useCompetitionQuery()
                                ->filterById($competitionIds, \Criteria::IN)
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()->toArray();

            $questionnaireParticipationIds = array_merge($questionnaireSimpleParticipationIds, $questionnaireReadingParticipationIds);

            QuestionnaireParticipationQuery::create()->filterById($questionnaireParticipationIds)->delete();

            CompetitionGroupQuery::create()
                ->filterByCompetitionId($competitionIds, \Criteria::IN)
                ->filterByGroupId($group->getId(), \Criteria::NOT_EQUAL)
                ->delete();

            CompetitionUserQuery::create()
                ->filterByCompetitionId($competitionIds, \Criteria::IN)
                ->delete();

            CompetitionParticipationQuery::create()
                ->filterByCompetitionId($competitionIds, \Criteria::IN)
                ->delete();


            CompetitionQuestionnaireQuery::create()
                ->filterByCompetitionId($competitionIds, \Criteria::IN)
                ->update(["UserId" => $this->getUser()->getId()]);

            $bookIds = CompetitionBookQuestionnaireQuery::create()
                ->useBookQuery()
                    ->useCompetitionQuery()
                        ->filterById($competitionIds, \Criteria::IN)
                    ->endUse()
                ->endUse()
                ->select('book_id')
                ->find()->toArray();
            CompetitionBookQuestionnaireQuery::create()->filterByBookId($bookIds, \Criteria::IN)
                ->update(["UserId" => $this->getUser()->getId()]);

            CompetitionQuery::create()->filterById($competitionIds, \Criteria::IN)->update(["Status" => 2]);

        } elseif ('DELETE' === $this->choice) {

            $competitionQuestionnaires = MediaQuery::create()
                ->useCompetitionQuestionnaireQuery()
                    ->filterByCompetitionId($competitionIds, \Criteria::IN)
                    ->filterByValidate(CompetitionQuestionnaire::VALIDATE_VALIDATED)
                ->endUse()
                ->select('id')
                ->find()
                ->toArray();

            BookParticipationQuery::create()
                ->useBookQuery()
                    ->useCompetitionQuery()
                        ->filterById($competitionIds, \Criteria::IN)
                    ->endUse()
                ->endUse()
                ->delete();

            $competitionBookQuestionnaires = MediaQuery::create()
                ->useCompetitionBookQuestionnaireQuery()
                    ->useBookQuery()
                        ->useCompetitionQuery()
                           ->filterById($competitionIds, \Criteria::IN)
                        ->endUse()
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()
                ->toArray();

            $mediaIds = array_merge($competitionQuestionnaires, $competitionBookQuestionnaires);

            MediaQuery::create()->filterById($mediaIds, \Criteria::IN)->update(["StatusDeletion" => -1]);

            CompetitionQuery::create()->filterById($competitionIds, \Criteria::IN)->delete();
        }
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppCompetitionBundle:DataReset:change_year_competition.html.twig';
    }

    /**
     * @return ChangeYearCompetitionDataResetType
     */
    public function getFormType()
    {
        return new ChangeYearCompetitionDataResetType();
    }

    /**
     * @return array<String, String>
     */
    public static function getChoices()
    {
        return array(
            'KEEP' => 'KEEP_ALL_COMPETITION',
            'DELETE' => 'DELETE_ALL_COMPETITION'
        );
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

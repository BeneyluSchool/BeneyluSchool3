<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 27/04/2017
 * Time: 18:35
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\BookNotice;
use BNS\App\CompetitionBundle\Model\BookNoticeQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;

class BookNoticeApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *     section ="Competition - BookNotice",
     *     resource=true,
     *     description="Propose un questionnaire au concours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Post("/{id}/notice")
     * @Rest\View(serializerGroups={"Default","book_detail","media_basic","user_avatar"})
     * @param $id
     * @param $request
     */
    public function postNoticePropositionAction(Request $request, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        if (!is_integer($request->get('mediaId'))) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        $media = MediaQuery::create()->findPk($request->get('mediaId'));
        if (!$media) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $notice = BookNoticeQuery::create()->filterByBook($book)->filterByNoticeId($media->getId())->findOne();
        if ($notice && $notice->getValidate() === BookNotice::VALIDATE_PENDING) {
            return View::create('Cette notice est déja soumise à validation', Codes::HTTP_BAD_REQUEST);
        }
        if ($notice && $notice->getValidate() === BookNotice::VALIDATE_REFUSED) {
            $notice->setValidate(BookNotice::VALIDATE_PENDING)->save();
            return $notice;
        }
        $notice = new BookNotice();
        $notice->setBookId($id)->setNoticeId($media->getId())->setUserId($this->getUser()->getId())->setValidate(BookNotice::VALIDATE_PENDING)->save();

        $this->get('bns.competition.notification.manager')->notificateNoticePropositionBook($notice);
        return $notice;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - BookNotice",
     *     resource=true,
     *     description="Liste les notices en attente de validation",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{bookId}/pending-notices")
     * @Rest\View(serializerGroups={"Default","book_detail","media_basic","user_avatar"})
     * @param $bookId
     *
     * @return array
     */
    public function listPendingNoticesAction($bookId)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($bookId);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $query = BookNoticeQuery::create()
            ->filterByBook($book)
            ->filterByValidate(0)
            ->lastUpdatedFirst()
            ->find();

        return $query;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - BookNotice",
     *     resource=true,
     *     description="Liste les notices en attente de validation",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/my-pending-notices/{id}")
     * @Rest\View(serializerGroups={"Default","book_detail","media_basic","user_avatar"})
     * @param $id
     *
     * @return array
     */
    public function listMyPendingNoticesAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $query = BookNoticeQuery::create()
            ->filterByBook($book)
            ->filterByUserId($this->getUser()->getId())
            ->filterByValidate(0)
            ->lastUpdatedFirst()
            ->find();

        return $query;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - BookNotice",
     *     resource=true,
     *     description="Refuse une notice",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{bookId}/refuse-notice/{id}")
     * @Rest\View(serializerGroups={"Default","book_detail","media_basic","user_avatar"})
     * @param $bookId
     * @param $id
     *
     */
    public function refuseNoticeAction($bookId, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $notice = BookNoticeQuery::create()->filterByNoticeId($id)->filterByBookId($bookId)->filterByValidate(0)->findOne();
        if (!$notice) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($notice->getBook()->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $notice->setValidate(-1)->save();

        return $notice;
    }


    /**
     * @ApiDoc(
     *     section ="Competition - BookNotice",
     *     resource=true,
     *     description="Refuse une notice",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{bookId}/accept-notice/{id}")
     * @Rest\View(serializerGroups={"Default","book_detail","media_basic","user_avatar"})
     * @param $bookId
     * @param $id
     *
     */
    public function acceptNoticeAction($bookId, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $notice = BookNoticeQuery::create()->filterByNoticeId($id)->filterByValidate(0)->filterByBookId($bookId)->findOne();
        if (!$notice) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($notice->getBook()->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $media = MediaQuery::create()->findPk($id);
        $copy = $media->copy();
        $copy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION);
        $copy->save();
        $content = WorkshopContentQuery::create()
            ->filterByMediaId($id)
            ->findOne();
        $manager = $this->get('bns.workshop.widget_group.manager');
        $contCopy = $manager->duplicateContent($content);
        $contCopy->setMediaId($copy->getId());
        $contCopy->save();
        $notice->setValidate(1)->setNoticeId($copy->getId())->save();
        $book = $notice->getBook();
        $book->setNoticeId($copy->getId())->save();

        $groupsContributors = WorkshopContentGroupContributorQuery::create()
            ->filterByContentId($media->getWorkshopContent()->getId())
            ->select('group_id')
            ->find()->toArray();
        $usersContributorsIds = WorkshopContentContributorQuery::create()
            ->filterByContentId($media->getWorkshopContent()->getId())
            ->select('user_id')
            ->find()->toArray();
        foreach ($groupsContributors as $groupContributor) {
            $group = $this->get('bns.group_manager')->setGroupById($groupContributor);
            $groupUsersIds = $group->getUsersIds();
            $usersContributorsIds = array_unique(array_merge($usersContributorsIds, $groupUsersIds));
        }
        foreach ($usersContributorsIds as $userContributor) {
            $user = UserQuery::create()->findPk($userContributor);
            if ($user->isChild()) {
                $participation = CompetitionParticipationQuery::create()->filterByCompetitionId($notice->getBook()->getCompetitionId())
                    ->filterByUserId($userContributor)->findOne();
                if (!$participation) {
                    $participation = new CompetitionParticipation();
                    $participation->setCompetitionId($notice->getBook()->getCompetitionId())->setUserId($userContributor)->save();
                }
                $participation->setScore($participation->getScore() + 1)->save();
            }
        }
        return $notice;
    }
}

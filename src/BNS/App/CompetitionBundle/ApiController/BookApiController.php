<?php


namespace BNS\App\CompetitionBundle\ApiController;

use BNS\App\CompetitionBundle\Form\Type\BookType;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\BookNoticeQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BookApiController
 *
 * @package BNS\App\CompetitionBundle\ApiController
 */
class BookApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition-Book",
     *     resource=true,
     *     description="lister les livres",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","book_list"})
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     *
     */
    public function indexAction(ParamFetcherInterface $paramFetcher,$id)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS',$id)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $books = BookQuery::create()
            ->filterByGroupId($id)
            ->lastUpdatedFirst()
            ->find();

        /*return $this->getPaginator($query, new Route('competition_api_index', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);*/
        return $books;
    }
    /**
     * @ApiDoc(
     *  section="Competition-Book",
     *  resource=true,
     *  description="Editer un livre",
     *  statusCodes = {
     *      201 = "livre étdité",
     *   },
     *
     * )
     *
     * @Rest\Patch("/edit/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function editAction(Request $request, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()->findPk($id);
        if(!$book){
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$competitionManager->canManageCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        return $this->restForm(new BookType($book), $book, array(
            'csrf_protection' => false,
        ));
    }

    /**
     * @ApiDoc(
     *     section ="Competition-Book",
     *     resource=true,
     *     description="details d'un livre",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/details/{id}")
     * @Rest\View(serializerGroups={"Default","book_list","book_detail","media_basic","user_avatar", "book_statistics", "competition_participation"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     *@return array
     */
    public function detailsAction(ParamFetcherInterface $paramFetcher,$id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if(!$book){
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        return $book;
    }
}

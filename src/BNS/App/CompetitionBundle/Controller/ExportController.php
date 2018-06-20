<?php

namespace BNS\App\CompetitionBundle\Controller;

use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\Annotation\Rights;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * @Route("/export")
 */
class ExportController extends Controller
{

    /**
     * @Route("/jpg", options={"expose"=true})
     * @Rights("COMPETITION_ACCESS")
     *
     * @param Request $request
     * @return Response
     */
    public function jpgAction(Request $request)
    {
        $session = $this->get('session');
        $router = $this->get('router');

        $competitionManager = $this->get('bns.competition.competition.manager');
        $rightManager = $this->get('bns.right_manager');
        $competition = CompetitionQuery::create()->findPk($request->get('competition_id'));

        if (!$competition) {
            throw new NotFoundHttpException();
        }

        if (!$rightManager->hasRight('COMPETITION_ACCESS') || !$competitionManager->canAccessCompetition($competition, $this->getUser())) {
            throw new AccessDeniedHttpException();
        }

        $params = [];

        if ($request->get('user_id')) {
            $params['user_id'] = $request->get('user_id');
        }


        if ($request->get('group_id')) {
            $params['group_id'] = $request->get('group_id');
        }

        if ($request->get('questionnaire_id')) {
            $params['questionnaire_id'] = $request->get('questionnaire_id');
        }

        if ($request->get('book_id')) {
            $params['book_id'] = $request->get('book_id');
        }


        // build angular app route
        $url = $router->getContext()->getScheme() . '://'
            . $router->getContext()->getHost()
            . $router->getContext()->getBaseUrl()
            . '/app/?embed=1#/competition/print'
            . '/' . $request->get('competition_id')
            . '?'.urldecode(http_build_query($params));

        $session->save();
        session_write_close();
        $result = $this->get('knp_snappy.image')->getOutput($url, array(
            'width' => 1024,
            'height' => 1024,
            'disable-javascript' => false,
            'enable-javascript' => true,
            'javascript-delay' => 10000,
            'debug-javascript' => true,
            'window-status' => 'done',
            'cookie' => array(
                $session->getName() => $session->getId(),
            ),
        ));

        return new Response($result, 200, [
            'Content-Type'        => 'image/jpg',
            'Content-Disposition' => 'attachment; filename="stats.jpg"',
        ]);
    }
    /**
     * @Route("/csv", options={"expose"=true})
     * @Rights("COMPETITION_ACCESS")
     *
     * @param Request $request
     * @return Response
     */
    public function csvAction(Request $request)
    {
        $id = $request->get('competition_id');
        $competitionManager = $this->get('bns.competition.competition.manager');
        $rightManager = $this->get('bns.right_manager');
        $competition = CompetitionQuery::create()->findPk($id);

        if (!$competition) {
            throw new NotFoundHttpException();
        }

        if (!$rightManager->hasRight('COMPETITION_ACCESS') || !$competitionManager->canAccessCompetition($competition, $this->getUser())) {
            throw new AccessDeniedHttpException();
        }

        $csv = $competitionManager->exportStats($competition, $request);
        $response = new Response();
        $response->headers->set('Content-Encoding', 'UTF-8');
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');

         $csv->output($competition->getTitle() . "-statistics.csv");

        return $response;
    }
}

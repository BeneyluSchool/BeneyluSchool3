<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSettingQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkshopQuestionnaireApiController extends BaseWorkshopApiController
{
    /**
     * @ApiDoc(
     *  section="Atelier - Questionnaire",
     *  resource=true,
     *  description="Vérifie si les réponses sont bonnes"
     * ),
     * @ParamConverter("workshopWidget")
     * @Rest\Post("/{id}/{type}/verify")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyAction(Request $request, $id, $type)
    {
        $data = $request->get('data');
        $showSolution = $request->get('show_solution');
        $isCorrect = false;
        $count = 0;
        $total = 0;
        $rightAnswers = [];

        $widget = WorkshopWidgetQuery::create()
            ->findOneById($id);

        if (!$widget) {
            $isCorrect = false;
        }

        $settings = WorkshopWidgetExtendedSettingQuery::create()
            ->filterByWorkshopWidget($widget)
            ->findOne();

        if (!$settings) {
            $isCorrect = false;
        }

        $correctAnswers = $settings->getCorrectAnswers();

        switch ($type) {
            case 'multiple':

                if (count(array_diff(array_merge($data, $correctAnswers), array_intersect($data, $correctAnswers))) === 0) {
                    $isCorrect = true;
                }
                foreach ($data as $item) {
                    if (in_array ($item, $correctAnswers)) {
                        $rightAnswers[] = $item;
                    }
                }
                break;
            case 'simple':
                if ($correctAnswers == $data) {
                    $isCorrect = true;
                }
                break;
            case 'closed':
                if (strtolower($data) == strtolower($correctAnswers)) {
                    $isCorrect = true;
                }
                break;
            case 'gap-fill-text':
                $total = count($correctAnswers);
                $array = [];
                foreach ($correctAnswers as $item) {
                    if ((array_key_exists($item['guid'], $data)) && (strtolower($item['label']) == strtolower($data[$item['guid']]))) {
                        $count++;
                        $array[] = $item['guid'];
                    }
                }
                $correctAnswers = $array;
                $showSolution = true;
                if ($count == $total) {
                    $isCorrect = true;
                } else {
                    $isCorrect = false;
                }
                break;
        }

        if ($showSolution) {
            $response = ['is_correct' => $isCorrect, 'correct_count' => $count, 'total' => $total, 'right_answers' => $rightAnswers, 'correct_answers' => $correctAnswers];
        } else {
            $response =['is_correct' => $isCorrect, 'correct_count' => $count, 'total' => $total, 'right_answers' => $rightAnswers,];
        }

        return new JsonResponse($response);
    }
}

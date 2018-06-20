<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 25/04/2017
 * Time: 17:24
 */

namespace BNS\App\WorkshopBundle\Manager;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSettingQuery;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class QuestionnaireManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class QuestionnaireManager
{


    public function verifyAnswer($answer, $widget, $type, $showSolution){
        $isCorrect = false;
        $count = 0;
        $total = 0;
        $rightAnswers = [];

        $settings = WorkshopWidgetExtendedSettingQuery::create()
            ->filterByWorkshopWidget($widget)
            ->findOne();

        if (!$settings) {
            $isCorrect = false;
        }

        $correctAnswers = $settings->getCorrectAnswers();

        switch ($type) {
            case 'multiple':
                $total = count($correctAnswers);
                if (count(array_diff(array_merge($answer, $correctAnswers), array_intersect($answer, $correctAnswers))) === 0) {
                    $isCorrect = true;
                }
                foreach ($answer as $item) {
                    if (in_array ($item, $correctAnswers)) {
                        $rightAnswers[] = $item;
                        $count++;
                    }
                }
                break;
            case 'simple':
                if (intval($correctAnswers) === intval($answer)) {
                    $isCorrect = true;
                }
                break;
            case 'closed':
                if (mb_strtolower($answer) == mb_strtolower($correctAnswers)) {
                    $isCorrect = true;
                }
                break;
            case 'gap-fill-text':
                $total = count($correctAnswers);
                $array = [];
                foreach ($correctAnswers as $item) {
                    if ((array_key_exists($item['guid'], $answer)) && (mb_strtolower($item['label']) == mb_strtolower($answer[$item['guid']]))) {
                        $count++;
                        $array[] = $item['guid'];
                    }
                }
                $correctAnswers = $array;
                $showSolution = true;
                if ($count === $total) {
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

        return $response;


    }

}

<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 04/05/2017
 * Time: 13:32
 */

namespace BNS\App\CompetitionBundle\Manager;

use BNS\App\CompetitionBundle\Model\Answer;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use Symfony\Component\HttpFoundation\JsonResponse;

class AnswerManager
{

    public function compareAnswers($answers, WorkshopWidget $widget)
    {
        $numberOfChoices = count($widget->getExtendedSetting()->getChoices());
        $response = ["id" => $widget->getId(), "widget" => $widget->getRichContent(), 'choices' => $widget->getExtendedSetting()->getChoices(), 'type' => $widget->getType(), 'mode' => $widget->getExtendedSetting()->getAdvancedSettings()['type']];
        $students = array();
        $matrice = [];
        foreach ($answers as $answer) {
            /** @var Answer $answer */
            switch ($widget->getType()){
                case 'closed':
                    $correctAnswers = $widget->getWorkshopWidgetExtendedSetting()->getCorrectAnswers();

                    $content = $answer->getAnswer();
                    $response['choices'] = $correctAnswers;
                    $matrice = $content;
                    break;
                case 'gap-fill-text':
                    $correctAnswers = $widget->getWorkshopWidgetExtendedSetting()->getCorrectAnswers();
                    $response['answer'] = $widget->getWorkshopWidgetExtendedSetting()->getChoices();
                    $content = $answer->getAnswer();
                    $matrice = $content;
                    break;
                case 'simple' || 'multiple':
                {
                    if ("simple" == $widget->getType()) {
                        $content = [];
                        $content[] = $answer->getAnswer();
                        $correctAnswers[] = $widget->getWorkshopWidgetExtendedSetting()->getCorrectAnswers();
                    } else {
                        $content = $answer->getAnswer();
                        $correctAnswers = $widget->getWorkshopWidgetExtendedSetting()->getCorrectAnswers();
                    }

                    $matrice = [];

                    for ($i = 1; $i <= $numberOfChoices; $i++) {
                        if (in_array($i, $correctAnswers)) {
                            if (in_array($i, $content)) {
                                $matrice[$i] = 'good';
                            } else {
                                if ("multiple" == $widget->getType()) {
                                    $matrice[$i] = 'missed';
                                } else {
                                    $matrice[$i] = null;
                                }
                            }

                        } else {
                            if (in_array($i, $content)) {
                                if (array_diff($content, $correctAnswers)) {
                                    $matrice[$i] = 'bad';
                                }
                            } else {

                                $matrice[$i] = null;
                            }
                        }
                    }
                    break;
                }
            }
            array_push($students, [
                "user" => $answer->getQuestionnaireParticipation()->getUser()->getFullName(),
                "answers" => $matrice
            ]);
        }
        $response['answers'] = $students;
        return $response;
    }

    public function calculateScoreAndPercent(WorkshopWidget $widget, $answers, $retour)
    {
        $score = 0;
        $percent = 0;
        switch ($widget->getType()) {
            case 'simple': {
                if (true == $retour["is_correct"]) {
                    $score = 1;
                    $percent = 1;
                } else {
                    $score = 0;
                    $percent = 0;
                }
                break;
            }
            case 'closed': {
                if (true == $retour["is_correct"]) {
                    $score = 1;
                    $percent = 1;
                }
                break;
            }
            case 'gap-fill-text': {
                if (true == $retour["is_correct"]) {
                    $score = $retour['total'];
                    $percent = 1;
                } else {
                    $score = $retour['correct_count'];
                    $percent = $retour['correct_count'] / $retour['total'];
                }
                break;
            }
            case 'multiple': {
                $score = $retour['correct_count'];
                $percent = $retour['correct_count'] / $retour['total'];
                break;
            }
        }

        return ["score" => $score, "percent" => $percent];

    }
}

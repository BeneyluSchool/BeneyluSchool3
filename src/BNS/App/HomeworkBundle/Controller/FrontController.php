<?php

namespace BNS\App\HomeworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

class FrontController extends Controller
{

    protected $daysInWeek = array('monday' => 'MO', 'tuesday' => 'TU', 'wednesday' => 'WE', 'thursday' => 'TH', 'friday' => 'FR', 'saturday' => 'SA', 'sunday' => 'SU');

    /**
     * Affiche les devoirs
     * @Route("/")
     *
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     */
    public function indexAction()
    {
        $this->get('stat.homework')->visit();

        $right_manager = $this->get('bns.right_manager');
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

        $currentDay = strtolower(date("l", strtotime("today")));
        $currentDayKey = $this->daysInWeek[$currentDay];
        $TempPossibleDays = $preferences->getDays();

        $possibleDays = array_intersect($this->daysInWeek, $TempPossibleDays);

        //On se place sur le jour d'aujourd'hui
        while (current($possibleDays) !== $currentDayKey && next($possibleDays) != false);

        //On prend le prochain jour disponible dans le tableau si l'heure en cours est supérieure à 16
        if(date('H') >16)
        {
            $keyDayToSelect = next($possibleDays);
        }else{
            $keyDayToSelect = current($possibleDays);
        }

        //Si c'était le dernier on revient au début
        if(!$keyDayToSelect)
        {
            reset($possibleDays);
            $keyDayToSelect = current($possibleDays);
        }

        $arrayInverted = array_flip($this->daysInWeek);

        $dayOfWeek = $arrayInverted[$keyDayToSelect];

        $response = $this->forward('BNSAppHomeworkBundle:Front:displayForDay', array(
            'dayOfWeek' => $dayOfWeek
                ));

        return $response;
    }

    /**
     * Affiche les devoirs d'un jour de la semaine
     * @Route("/jour/{dayOfWeek}", name="BNSAppHomeworkBundle_front_display_for_day")
     * @Template()
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     */
    public function displayForDayAction($dayOfWeek)
    {
        $right_manager = $this->get('bns.right_manager');
        $user = $right_manager->getModelUser();

        // prendre lundi comme jour par défaut
        if (!array_key_exists($dayOfWeek, $this->daysInWeek)) {
            $dayOfWeek = 'monday';
        }

        //On garde le jour en cours et affichons les prochains qu'à partir du lendemain
        if(date('Y-m-d') == date('Y-m-d',strtotime($dayOfWeek)))
        {
            $start_day = $dayOfWeek;
        }else{
            $start_day = strtotime('next '.$dayOfWeek);
        }

        $groupIds = $right_manager->getGroupIdsWherePermission('HOMEWORK_ACCESS');

        $due_this_week = HomeworkDueQuery::create()->groupByHomeworkId()->findForDay($groupIds, $start_day);

        $due_later_temp = HomeworkDueQuery::create()->groupByHomeworkId()->findFuturesForDayOfWeekForGroup($groupIds, $this->daysInWeek[$dayOfWeek]);


	    $due_later = $due_later_temp->diff($due_this_week);



        // regrouper les devoirs par jours pour l'affichage
        $due_later_sorted = $this->groupHomeworkDuesByDate($due_later);

        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

        //Affichage front pour admin
        /*$admin = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $right_manager->getCurrentGroupId());
        $due_admin = null;
        $due_later_admin = null;
        $due_later_admin_sorted = null;*/
        /*if($admin) {
            //Récupérer les devoirs pour la classe cette semaine
            $due_admin = HomeworkDueQuery::create()->findForRangeForGroups($start_day, $start_day, $right_manager->getCurrentGroup());
            $due_later_admin_temp = HomeworkDueQuery::create()
                    ->findFuturesForDayOfWeekForGroup($right_manager->getCurrentGroup(), $this->daysInWeek[$dayOfWeek]);

	    $due_later_admin = $due_later_temp->diff($due_admin);

            // regrouper les devoirs par jours pour l'affichage
            $due_later_admin_sorted = $this->groupHomeworkDuesByDate($due_later_admin);
        }*/

        //Récupération des enfants si besoin
        if($this->get('bns.user_manager')->hasChild($right_manager->getModelUser()))
        {
            $children = $this->get('bns.user_manager')->getUserChildren($right_manager->getModelUser());
        }

        return array(
            'start_day' => $start_day,
            'due_this_week' => $due_this_week,
            'due_later' => $due_later,
            'due_later_sorted' => $due_later_sorted,
            'day_of_week' => $dayOfWeek,
            'preferences' => $preferences,
            /*'due_admin' => $due_admin,
            'due_later_admin' => $due_later_admin,
            'due_later_admin_sorted' => $due_later_admin_sorted,
            'admin' => $admin,*/
            'currentUser' => $user,
            'hasSeveralHomeworks' =>  $groupIds,
            'children' => isset($children) ? $children : array()
        );
    }

    /**
     * Affiche les devoirs déjà passés
     * @Route("/historique", name="BNSAppHomeworkBundle_front_history")
     * @Template()
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     */
    public function historyAction()
    {
        $right_manager = $this->get('bns.right_manager');
        $page = $this->getRequest()->get('page');

        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

        return array(
            'page' => $page,
            'preferences' => $preferences
        );
    }

    /**
     * Affiche les devoirs déjà passés, mode paginé
     * @Route("/historique/{page}", name="BNSAppHomeworkBundle_front_history_page")
     * @Template()
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     */
    public function historyPageAction($page = 1)
    {
        $right_manager = $this->get('bns.right_manager');
        $user = $right_manager->getModelUser();

        //Récupérer les préférences pour afficher les jours et les travaux passés
        $preferences = HomeworkPreferencesQuery::create()->findOrInit($right_manager->getCurrentGroupId());

        //Affichage front pour admin
        $admin = $right_manager->hasRight('HOMEWORK_ACCESS_BACK', $right_manager->getCurrentGroupId());


        if (!$admin) {
            $groupIds = $right_manager->getGroupIdsWherePermission('HOMEWORK_ACCESS');
            list($homeworks, $pager) = HomeworkDueQuery::create()->findPastForGroupIds($groupIds, $page, null);
        } else {
            //Récupérer les devoirs pour la classe cette semaine
            list($homeworks, $pager) = HomeworkDueQuery::create()->findPastForGroupIds($right_manager->getCurrentGroupId(), $page, null);
        }

        // fix = propel outputs results even if the current page is beyond the last
        if ($page > $pager->getLastPage()) {
            $homeworks = array();
        }

        $homeworks_sorted = $this->groupHomeworkDuesByDate($homeworks);

        return array(
            'homeworks_sorted' => $homeworks_sorted,
            'pager' => $pager,
            'preferences' => $preferences,
            'admin' => $admin,
            'currentUser' => $user
        );
    }

    /**
     * Regroupe les homeworkdues par jour pour l'affichage front
     * @param type $homeworkdues les homeworkdues à regrouper
     * @return type un array associatif (date -> array de homeworkdues)
     */
    protected function groupHomeworkDuesByDate($homeworkdues) {

        // regrouper les devoirs par jours pour l'affichage
        $homeworkdues_sorted = array();
        foreach($homeworkdues as $hd) {
            if(array_key_exists($hd->getDueDate("Y-m-d"), $homeworkdues_sorted)) {
                array_push($homeworkdues_sorted[$hd->getDueDate("Y-m-d")], $hd);
            } else {
                 $homeworkdues_sorted[$hd->getDueDate("Y-m-d")] = array($hd);
            }
        }

        return $homeworkdues_sorted;
    }

}

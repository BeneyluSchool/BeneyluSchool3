<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\ClassroomBundle\Model\ClassroomNewspaper;
use BNS\App\ClassroomBundle\Model\ClassroomNewspaperQuery;
use BNS\App\ClassroomBundle\Model\AventCalendarQuery;
use BNS\App\ClassroomBundle\Model\ClassroomPushQuery;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Criteria;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\Annotations as Rest;

use BNS\App\CoreBundle\Annotation\Rights;
use Symfony\Component\HttpFoundation\Response;

class FrontController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_front", options={"expose": true})
     * @Rights("CLASSROOM_ACCESS")
     */
    public function indexAction(Request $request)
    {
        if ($this->container->hasParameter('home.window_view_filename')) {
            $customWindow = $this->container->getParameter('home.window_view_filename');
        } else {
            $customWindow = "default";
        }

        $groupManager = $this->get('bns.right_manager')->getCurrentGroupManager();
        if ($groupManager->getProjectInfo('has_new_year_status') && date('m',time()) == '01') {
            $redis = $this->get('snc_redis.default');
            if(!$redis->exists('new_year_status'))
            {
                $newYearStatus = ProfileFeedQuery::create()->filterByNewYearStatus()->find();
                $array = array();
                foreach($newYearStatus as $status)
                {
                    $user = $status->getProfile()->getUser();
                    $um = $this->get('bns.user_manager');
                    $um->setUser($user);
                    $schools = $um->getSimpleGroupsAndRolesUserBelongs(true,3);
                    foreach($schools as $school)
                    {
                        $address = $school->getAttribute('CITY') . ' (' . substr($school->getAttribute('ZIPCODE'),0,2) . ')';
                    }
                    if(!isset($address))
                    {
                        $address = "";
                    }
                    $array[] = array(
                        'text' => $status->getProfileFeedStatus()->getContent(),
                        'username' => strtoupper(substr($user->getFirstName(),0,1)) . '. ' . strtoupper(substr($user->getLastName(), 0,1)) . '.',
                        'address' => $address
                    );
                    unset($address);
                }
                $redis->set('new_year_status',json_encode($array));
                $redis->expire('new_year_status', 3600);

            }
            //On melange
            $status = json_decode($redis->get('new_year_status'));
            $keys = array_keys($status);
            shuffle($keys);
            foreach($keys as $key) {
                $new[$key] = $status[$key];
            }
            $status = $new;
            $newYearResults = $status;
        }

        //rendre le calendrier accessible seulement aux francais
        $user = $this->getUser();
        $lang= $user->getLang();

        $lastFlux = array();
        $blackboard = null;
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        if ($this->get('bns.group_manager')->setGroup($group)->getProjectInfo('has_group_blackboard')) {
            $blackboard = $this->get('bns_core.blackboard_manager')->getBlackboard($group);
            if ($blackboard) {
                $lastFlux = $this->get('bns_core.blackboard_manager')->getLastNews($blackboard, $group);
            }
        }

        // check if user has profile completion
        $profileCompletion = false;
        if ($groupManager->isOnPublicVersion()) {
            if ($profileCompletion = $this->get('bns.right_manager')->hasRightSomeWhere('CLASSROOM_ACCESS_BACK')) {
                $classroom = $groupManager->getClassroom();
                $school = $this->get('bns.group_manager')->setGroup($classroom)->getParent();

                // get list of values that should be filled
                $defaultClassroomLabels = [];
                $defaultSchoolLabels = [];
                foreach ($this->get('bns.locale_manager')->getNiceAvailableLanguages() as $languageCode => $value) {
                    $defaultClassroomLabels[] = $this->get('translator')->trans('LABEL_MY_CLASSROOM', [], 'USER', $languageCode);
                    $defaultSchoolLabels[] = $this->get('translator')->trans('LABEL_MY_SCHOOL', [], 'USER', $languageCode);
                }
                $values = [
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getGender(),
                    $user->getLang(),
                    $school->getCountry() || $classroom->getCountry(),
                    in_array($classroom->getLabel(), $defaultClassroomLabels) ? null : $classroom->getLabel(),
                    count($classroom->getAttribute('LEVEL')),
                    in_array($school->getLabel(), $defaultSchoolLabels) ? null : $school->getLabel(),
                    $school->getAttribute('ZIPCODE'),
                    $school->getAttribute('CITY'),
                    count($this->get('bns.classroom_manager')->setGroup($classroom)->getPupils()),
                ];

                // count filled values
                $completed = 0;
                foreach ($values as $value) {
                    if ($value) {
                        $completed++;
                    }
                }
                $profileCompletion = $completed / count($values) * 100;

                // hide if profile is complete
                if ($profileCompletion === 100) {
                    $profileCompletion = false;
                }
            }
        }

        $push = ClassroomPushQuery::create()->getCurrent();
        if ($push) {
            $push->setLocale($user->getLang());
        }

        return $this->render('BNSAppClassroomBundle:Front:front_classroom_index.html.twig', array(
            'profile_completion' => $profileCompletion,
            'message' => $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('HOME_MESSAGE'),
            'customWindow' => $customWindow,
            'push' => $push,
            'newYearStatus' => isset($newYearResults) && count($newYearResults) > 0 ? $newYearResults : false,
            'school' => $this->get('bns.right_manager')->getCurrentGroupManager()->getParent(),
            'main_role_id' => $this->get('bns.right_manager')->getUserSession()->getHighRoleId(),
            'publicVideoUrl' =>  $this->get('service_container')->getParameter('public_version_url') . $this->get('router')->generate('BNSAppClassroomBundle_front_daily_video'),
            'blackboard' => $blackboard,
            'lastFlux' => $lastFlux,
            'lang' => $lang,
            'hasAventCalendar' => $this->hasAventCalendar($request),
        ));
    }

    /**
     *
     * @Route("/newsPaper/expose/{date}", name="BNSAppClassroomBundle_front_expose_newspaper")
     */
    public function exposeNewsPaperAction($date)
    {
        $newsPaper = ClassroomNewspaperQuery::create()->filterByIsCalendar(null, \Criteria::ISNULL)->findOneByDate($date);
        if ($newsPaper) {
            $context = new SerializationContext();
            $context->setGroups(['Default']);
            return new Response($this->get('jms_serializer')->serialize($newsPaper, 'json', $context),200);
        } else {
            return new Response('no news paper', 404);
        }
    }

    /**
     * @Route("/statut-nouvelle-annee", name="BNSAppClassroomBundle_front_newYearStatus", options={"expose": true})
     * @Rights("CLASSROOM_ACCESS")
     * @Template()
     */
    public function newYearStatusAction()
    {
        $newYearStatus = ProfileFeedQuery::create()->filterByNewYearStatus()->find();


    }

    /**
     * @Route("/journal/compteur/{id}", name="BNSAppClassroomBundle_front_newspaper_count", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS")
     */
    public function newspaperCountAction($id)
    {
        $newspaper = ClassroomNewspaperQuery::create()->filterByIsCalendar(null, \Criteria::ISNULL)->findOneById($id);
        $newspaper->read();

        return new Response();
    }

    /**
     * @Route("/journal/video-du-jour", name="BNSAppClassroomBundle_front_daily_video")
     */
    public function dailyVideoAction()
    {
        $newsPaper = ClassroomNewspaperQuery::create()->filterByIsCalendar(null, \Criteria::ISNULL)->findOneByDate(date('Y-m-d'));
        $media = $newsPaper->getMediaRelatedByMediaId();
        if ($media && $media->getTypeUniqueName() == "EMBEDDED_VIDEO") {
            return new Response($media->getEmbeddedVideoCode('100%'));
        }

        throw $this->createNotFoundException();
    }

    /**
     *
     * @Route("/avent", name="BNSAppClassroomBundle_calendrier_avent")
     * @Rights("CLASSROOM_ACCESS")
     */
    public function calendrierAventAction(Request $request)
    {
        if (!$this->hasAventCalendar($request)) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_front'));
        }
        //trouver les calendriers de l'avent (=les journaux ayant 1 dans is_calendar) d'aujourd'hui et passés
        $newsPaper = $this->getCurrentAventCalendarsQuery()->find();

        //pour l'ordre d'affichage des classes
        $order = [2, 7, 25, 11, 18, 6, 14, 10, 4, 12, 1, 5, 23, 17, 22, 13, 8, 20, 3, 19, 21, 16, 9, 24, 15];
        $days = $newsPaper;

        //recupere l'utilisateur pour mettre les jours vus dans la table Avent Calendar
        $user = $this->getUser();
        $game = AventCalendarQuery::create()->filterByUser($user)->findOne();

        // si il y ai deja allé, je recupere les jours
        $dejavus = $game ? $game->getDays() : null;

        //les jours du calendrier de l'avant (aujourd hui et passés), l'ordre et les jours deja vus
        return $this->render('BNSAppClassroomBundle:Front:calendrier_avent.html.twig', [
            'days' => $days,
            'order' => $order,
            'dejavus' => $dejavus
        ]);
    }

    /**
     *
     * @Route("/avent/{id}/{type}", name="BNSAppClassroomBundle_calendrier_one_day")
     * @Rights("CLASSROOM_ACCESS")
     */
    public function calendrierOneDayAction($id = null, $type = null)
    {
        $newsPaper = ClassroomNewspaperQuery::create()
            ->filterByIsCalendar(1)
            ->filterById($id)
            ->filterByDate(date('Y-m-d'), \Criteria::LESS_EQUAL)
            ->findOne();

        if (!$newsPaper) {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_calendrier_avent'));
        }

        //recupere la date du jour vu
        $vu = $newsPaper->getDate('j');

        //recupere l'user en session
        $user = $this->getUser();

        //recupere les jours qu'il a deja vu. si il n'en a jamais vu, ça lui créé une ligne dans la table
        $game = AventCalendarQuery::create()
            ->filterByUser($user)
            ->findOneOrCreate();

        //recupere les jours deja vus
        $dejavus = $game->getDays();

        //si il n'y a pas le jour vu dans la table deja vu, il l'ajoute
        if (!in_array($vu, $dejavus, true)) {
            $game->addDay($vu);
            $game->save();
        }

        if ($game->isNew()) {
            $game->save();
        }

        //passe le jour et le type (text ou image)
        return $this->render('BNSAppClassroomBundle:Front:day_avent.html.twig', array('day' => $newsPaper, 'type' => $type));
    }


    private function hasAventCalendar(Request $request)
    {
        if ('fr' === $request->getLocale()) {
            return ClassroomNewspaperQuery::create()
                    ->filterByIsCalendar(1)
                    ->filterByDate(date('Y-m-d', strtotime('-10 days')), \Criteria::GREATER_THAN)
                    ->filterByDate(date('Y-m-d'), \Criteria::LESS_EQUAL)
                    ->count() > 0
            ;
        }

        return false;
    }

    protected function getCurrentAventCalendarsQuery()
    {
        return ClassroomNewspaperQuery::create()
            ->orderByDate()
            ->filterByIsCalendar(1)
            ->filterByDate(date('Y-m-d', strtotime('-50 days')), \Criteria::GREATER_THAN)
            ->filterByDate(date('Y-m-d'), \Criteria::LESS_EQUAL)
        ;
    }
}

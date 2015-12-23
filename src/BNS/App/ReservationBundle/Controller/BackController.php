<?php

namespace BNS\App\ReservationBundle\Controller;



use Pagerfanta\Adapter\PropelAdapter;

use Pagerfanta\Pagerfanta;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\ReservationBundle\Form\Model\ReservationEventFormModel;
use BNS\App\ReservationBundle\Form\Type\ReservationEventType;
use BNS\App\ReservationBundle\Form\Type\ReservationItemType;
use BNS\App\ReservationBundle\Model\Reservation;
use BNS\App\ReservationBundle\Model\ReservationItem;
use BNS\App\ReservationBundle\Model\ReservationItemQuery;
use BNS\App\ReservationBundle\Model\ReservationEvent;
use BNS\App\ReservationBundle\Model\ReservationPeer;
use BNS\App\ReservationBundle\Model\ReservationQuery;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/gestion")
 */

class BackController extends Controller
{
    /**
     * Homepage du back du module de calendrier
     *
     * @Route("/", name="BNSAppReservationBundle_back")
	 * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
    	$rightManager = $this->get('bns.right_manager');

    	// On récupère les paramètres d'initialisation de wdReservation grâce au ReservationManager
    	$array = $this->get('bns.reservation_manager')->getWdReservationInitParameters($request->getSession(), true);
    	$array['agendas'] = $this->get('bns.reservation_manager')->getReservationsFromGroupIds($rightManager->getGroupIdsWherePermission('RESERVATION_ACCESS_BACK'));
    	$array['colors'] = Reservation::$colorsClass;

    	return $this->render('BNSAppReservationBundle:Back:index.html.twig', $array);
    }


    /**
     * Donne accès à la page d'étion de l'événement portant le slug $slug
     *
     * @Route("/editer-reservation/{slug}", name="BNSAppReservationBundle_back_edit_event", options={"expose"=true})
     * @Rights("RESERVATION_ACCESS_BACK")
	 *
     * @param String $slug slug de l'événement à éditer
     */
    public function editEventAction(Request $request, $slug)
    {
        $rightManager = $this->get('bns.right_manager');
        // Check if user has rigths

        $reservationManager = $this->get('bns.reservation_manager');
        $event = $reservationManager->getEventBySlug($slug);

        $rightManager->forbidIf(!$rightManager->hasRight('RESERVATION_ACCESS_BACK', $event->getReservation()->getGroupId()));

        // Création du formulaire avec tous les paramètres d'initialisation nécessaire
        $form = $this->createForm(new ReservationEventType($rightManager->getLocale()), new ReservationEventFormModel($event));

        if ($request->isMethod('post')) {
            $form->bind($this->getRequest());
            if ($form->isValid()) {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();

                return $this->redirect($this->generateUrl('reservation_manager_event_visualisation', array(
                    'slug'	=> $event->getReservationEvent()->getSlug()
                )));
            }
        }

        return $this->render('BNSAppReservationBundle:Back:back_event_form.html.twig', array(
            'form'		=> $form->createView(),
            'event' 	=> $event,
            'locale' 	=> $rightManager->getLocale(),
            'isEdition'	=> true
    	));
    }

	/**
	 * Fourni le formulaire de création d'un nouvel événement
	 * Les paramètres GET que sont start, end et allday sont facultatives et ont une valeur par défaut; ils sont renseignés
	 * lorsque l'utilisateur souhaite créer un événement au moyen du drag sur le calendrier
	 *
	 * @Route("/creer-reservation/{start}/{end}/{allday}", name="BNSAppReservationBundle_back_add_event", defaults={"start" = 0, "end" = 0, "allday" = 0}, options={"expose"=true})
	 * @Rights("RESERVATION_ACCESS_BACK")
	 */
    public function newEventAction(Request $request, $start, $end, $allday)
    {
    	$rightManager = $this->get('bns.right_manager');
        $reservation = ReservationQuery::create()->filterByGroupId($rightManager->getCurrentGroupId())->findOneOrCreate();

        $reservationEvent = new ReservationEvent();
        $reservationEvent->setReservation($reservation);
        $reservationEvent->setDateStart(date('H:i'));
        $reservationEvent->setDateEnd(date('H:i'));

        // Si $start et $end sont différents de 0, alors la création d'événement est demandé suite à un drag de la part de l'utilisateur
        if ($start > 0 && $end > 0) {
            // Un traitement est opéré pour récupérer et prendre en considération la date de début et de fin fourni par l'utilisateur
            $reservationEvent->setDateStart($start);
            $reservationEvent->setDateEnd($end);
            if ($allday) {
                $reservationEvent->setIsAllDay(true);
            } else {
                $reservationEvent->setTimeStart(date('H:i', $start));
                $reservationEvent->setTimeEnd(date('H:i', $end));
            }

        }
        $form = $this->createForm(new ReservationEventType($rightManager->getLocale()), new ReservationEventFormModel($reservationEvent));

        if ($request->isMethod('post')) {
            $form->bind($request);
            if ($form->isValid()) {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();

                return $this->redirect($this->generateUrl('reservation_manager_event_visualisation', array(
                    'slug' => $event->getReservationEvent()->getSlug()
                )));
            }
        }

        return $this->render('BNSAppReservationBundle:Back:back_event_form.html.twig', array(
            'form'      => $form->createView(),
            'locale'    => $rightManager->getLocale(),
            'event'		=> $form->getData()->getReservationEvent(),
            'isEdition'	=> false
        ));
    }


    /**
     * Supprime l'événement dont le slug est $slug
     *
     * @Route("/supprimer-reservation/{slug}", name="BNSAppReservationBundle_back_delete_event")
     * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function deleteEventAction($slug)
    {
        $reservationManager = $this->get('bns.reservation_manager');
        $event = $reservationManager->getEventBySlug($slug);
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasRight('RESERVATION_ACCESS_BACK', $event->getReservation()->getGroupId()));

        $this->get('bns.reservation_manager')->deleteEvent($slug);

        return $this->redirect($this->generateUrl('BNSAppReservationBundle_back'));
    }

    /**
     * @Route("/modifier-reservation", name="BNSAppReservationBundle_back_update_event")
	 * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function quickUpdateEventAction(Request $request)
    {
    	// On récupère le slug de l'événement concerné par les modifications, et sa nouvelle date de début et de fin
    	$eventSlug = $request->request->get('event_slug');

    	$datetimeStart = $request->request->get('datetime_start');
    	$datetimeEnd = $request->request->get('datetime_end');

    	$reservationManager = $this->get('bns.reservation_manager');
    	$event = $reservationManager->getEventBySlug($eventSlug);

		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('RESERVATION_ACCESS_BACK', $event->getReservation()->getGroupId()));

    	$eventUpdatedInfos = array(
    		'dtstart' 	=> strtotime($datetimeStart),
    		'dtend'		=> strtotime($datetimeEnd),
    		'allday'	=> $event->getIsAllday(),
    	);
    	$reservationManager->editEvent($event, $eventUpdatedInfos);

    	return new Response(json_encode(true));
    }

	/**
     * @Route("/visualisation/{slug}", name="reservation_manager_event_visualisation", options={"expose"=true})
	 * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function eventDetailAction($slug)
    {
		$event = $this->get('bns.reservation_manager')->getEventBySlug($slug);
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('RESERVATION_ACCESS_BACK', $event->getReservation()->getGroupId()));

		$array = array();
    	$array['event'] = $event;
        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');
    	$array['hoursdial'] = (($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2;
    	$array['sundial'] = ($hours * 60 + $minutes) / 4;
		$array['agendas'] = $this->get('bns.reservation_manager')->getReservationsFromGroupIds($this->get('bns.right_manager')->getGroupIdsWherePermission('RESERVATION_ACCESS_BACK'));
    	$array['colors'] = Reservation::$colorsClass;

    	return $this->render('BNSAppReservationBundle:Back:back_event_visualisation.html.twig', $array);
    }


    /**
     * @Route("/liste-elements/{page}", name="BNSAppReservationBundle_back_list_item", defaults={"page"=1})
     * @Template()
     * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function reservationItemAction($page = 1)
    {
        $rightManager = $this->get('bns.right_manager');
        $query = ReservationItemQuery::create()
            ->orderByType()
            ->orderByTitle()
            ->useReservationQuery()
                ->filterByGroupId($rightManager->getCurrentGroupId())
            ->endUse();
        $pager = new Pagerfanta(new PropelAdapter($query));
        $pager->setCurrentPage($page);

        return array(
                'items' => $pager,
                'page' => $page
                );
    }

    /**
     * @Route("/creer-element/", name="BNSAppReservationBundle_back_new_item")
     * @Template("BNSAppReservationBundle:Back:formReservationItem.html.twig")
     * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function newReservationItemAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $reservation = $reservation = ReservationQuery::create()->filterByGroupId($rightManager->getCurrentGroupId())->findOneOrCreate();

        $reservationItem = new ReservationItem();
        $reservationItem->setReservation($reservation);

        $form = $this->createForm(new ReservationItemType(), $reservationItem);

        if ($request->isMethod('post')) {
            $form->bind($request);
            if ($form->isValid()) {
                $reservationItem->save();

                return $this->redirect($this->generateUrl('BNSAppReservationBundle_back_list_item'));
            }
        }

        return array(
                'form' => $form->createView(),
                'isEdition' => false,
                'item' => $reservationItem,
                );
    }

    /**
     * @Route("/editer-element/{slug}", name="BNSAppReservationBundle_back_edit_item")
     * @Template("BNSAppReservationBundle:Back:formReservationItem.html.twig")
     * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function editReservationItemAction(Request $request, $slug)
    {
        $rightManager = $this->get('bns.right_manager');
        $reservationItem = ReservationItemQuery::create()
            ->useReservationQuery()
                ->filterByGroupId($rightManager->getCurrentGroupId())
            ->endUse()
            ->filterBySlug($slug)
            ->findOne();

        if (!$reservationItem) {
            $this->createNotFoundException('item not found');
        }


        $form = $this->createForm(new ReservationItemType(), $reservationItem);

        if ($request->isMethod('post')) {
            $form->bind($request);
            if ($form->isValid()) {
                $reservationItem->save();

                return $this->redirect($this->generateUrl('BNSAppReservationBundle_back_list_item'));
            }
        }

        return array(
                'form' => $form->createView(),
                'isEdition' => true,
                'item' => $reservationItem,
        );
    }

    /**
     * @Route("/supprimer-element/{slug}", name="BNSAppReservationBundle_back_delete_item", options={"expose"=true})
     * @Rights("RESERVATION_ACCESS_BACK")
     */
    public function deleteReservationItemAction(Request $request, $slug)
    {
        $rightManager = $this->get('bns.right_manager');
        $reservationItem = ReservationItemQuery::create()
            ->useReservationQuery()
                ->filterByGroupId($rightManager->getCurrentGroupId())
            ->endUse()
            ->filterBySlug($slug)
            ->findOne();

        if (!$reservationItem) {
            $this->createNotFoundException('item not found');
        }

        $reservationItem->delete();

        return $this->redirect($this->generateUrl('BNSAppReservationBundle_back_list_item'));

    }
}

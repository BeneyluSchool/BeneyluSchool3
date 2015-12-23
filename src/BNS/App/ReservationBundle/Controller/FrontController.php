<?php

namespace BNS\App\ReservationBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\ReservationBundle\Model\Reservation;
use BNS\App\ReservationBundle\Model\ReservationEvent;
use BNS\App\ReservationBundle\Model\ReservationQuery;
use BNS\App\ReservationBundle\Model\ReservationPeer;
use BNS\App\ReservationBundle\Form\Type\ReservationEventType;
use BNS\App\ReservationBundle\Form\Model\ReservationEventFormModel;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class FrontController extends Controller
{
    /**
     * @Route("/", name="BNSAppReservationBundle_front")
	 * @Rights("RESERVATION_ACCESS")
     */
    public function indexAction(Request $request)
    {
        // Init wdReservation
        $array = $this->get('bns.reservation_manager')->getWdReservationInitParameters($request->getSession());

        $rightManager = $this->get('bns.right_manager');

        $reservation = ReservationQuery::create()->filterByGroupId($rightManager->getCurrentGroupId())->findOneOrCreate();

        if ($reservation->isNew()) {
            $reservation->setTitle('Réservations');
            $reservation->setColorClass('cal-red');
            $reservation->save();
        }


        $array['agendas'] = $reservation;

        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');

        $array['hoursdial'] = round((($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2);
        $array['sundial'] = round(($hours * 60 + $minutes) / 4);
        $array['colors'] = Reservation::$colorsClass;

    	return $this->render('BNSAppReservationBundle:Front:index.html.twig', $array);
    }


    /**
     * Méthode qui permet de récupérer la liste des événements à afficher par wdReservation;
     * Appel AJAX effectué par wdReservation; la date de début, la date de fin et le type d'affichage sont reçus par POST
	 *
     * @Route("/liste-evenements/{isAdmin}", name="BNSAppReservationBundle_front_list_events", options={"expose"=true}, defaults={"isAdmin" = 0})
     */
    public function listEventsAction(Request $request, $isAdmin)
    {
    	// AJAX?
    	if (!$request->isXmlHttpRequest()) {
    		throw new NotFoundHttpException();
    	}

    	// Check if user has rigths
    	if (0 == $isAdmin) {
    		$this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere('RESERVATION_ACCESS');
    	}
    	else {
    		$this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere('RESERVATION_ACCESS_BACK');
    	}

    	// Récupération des paramètres showdate et viewtype passé par POST lors de l'appel de wdReservation
    	$date = $request->request->get('showdate');
    	$viewtype = $request->request->get('viewtype');

    	// Sauvegarde en session de la date courante et du type d'affichage courant employé par l'utilisateur
    	$session = $request->getSession();
    	$session->set('bns.reservation.currentDate', $date);
    	$session->set('bns.reservation.currentViewType', $viewtype);

    	// Traitement sur la chaîne de caractère de date fourni par wdReservation pour obtenir
    	// la tranche de date sur laquelle l'utilisateur souhaite connaître les événements
    	$date = explode('/', $date);
    	$date = $date[2].'-'.$date[0].'-'.$date[1];
    	$dateStart = '';
    	$dateEnd = '';
    	// On test si c'est un affichage de type semaine ou non
    	if ($viewtype == 'week') {
	    	$dateStart = date('o-\WW', strtotime($date));
	    	$dateEnd = $dateStart.'+6 days';
    	}
    	elseif ($viewtype == 'day') {
    		$dateStart = $dateEnd = $date;
    	}
    	else {
    		// On n'autorise pas l'affichage au mois;
    		throw new Exception('Illegal viewtype given: '.$viewtype);
    	}

        $reservations = $this->get('bns.reservation_manager')->getReservationsFromGroupIds($this->get('bns.right_manager')->getGroupIdsWherePermission('RESERVATION_ACCESS'));

    	// La date de début et de fin est obtenu; on peut maintenant faire appel au ReservationManager pour qu'il nous retourne
    	// tous les événements compris entre $dateStart et $dateEnd (bornes incluses);
   	$events = $this->get('bns.reservation_manager')->selectEventsByDates(strtotime($dateStart), strtotime($dateEnd), $reservations, $isAdmin, !$isAdmin);

    	return new Response(json_encode($events));
    }


    /**
     * Permet d'afficher les détails de l'événement qui a pour slug $slug
     * @Route("/detail-evenement/{slug}", name="BNSAppReservationBundle_front_event_detail", options={"expose"=true})
	 * @RightsSomeWhere("RESERVATION_ACCESS")
	 *
     * @param String $slug
     */
    public function eventDetailAction($slug)
    {
        $array = array();
        $event = $this->get('bns.reservation_manager')->getEventBySlug($slug);

	$rm = $this->get('bns.right_manager');
	$rm->forbidIf(!$rm->hasRight('RESERVATION_ACCESS', $event->getReservation()->getGroupId()));

    	$array['event'] = $event;
        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');
    	$array['hoursdial'] = round((($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2);
    	$array['sundial'] = round(($hours * 60 + $minutes) / 4);

    	return $this->render('BNSAppReservationBundle:Front:event_detail.html.twig', $array);
    }


    /**
	* Fourni le formulaire de création d'un nouvel événement
	*
	* @Route("/creer-reservation", name="BNSAppReservationBundle_front_add_event", options={"expose"=true})
	* @Rights("RESERVATION_ACCESS")
	 */
    public function newEventAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $reservation = ReservationQuery::create()->filterByGroupId($rightManager->getCurrentGroupId())->findOneOrCreate();

        $reservationEvent = new ReservationEvent();
        $reservationEvent->setReservation($reservation);
        $reservationEvent->setDateStart(date('H:i'));
        $reservationEvent->setDateEnd(date('H:i'));

        $form = $this->createForm(new ReservationEventType($rightManager->getLocale()), new ReservationEventFormModel($reservationEvent));

        if ($request->isMethod('post')) {
            $form->bind($request);

            if ($form->isValid()) {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();

                return $this->redirect($this->generateUrl('BNSAppReservationBundle_front_event_detail', array(
                        'slug' => $event->getReservationEvent()->getSlug()
                )));
            }
        }

        return $this->render('BNSAppReservationBundle:Front:front_event_form.html.twig', array(
            'form'      => $form->createView(),
            'locale'    => $rightManager->getLocale(),
            'event'		=> $form->getData()->getReservationEvent(),
            'isEdition'	=> false
        ));
    }

//     /**
//      * Donne accès à la page d'étion de l'événement portant le slug $slug
//      *
//      * @Route("/editer-reservation/{slug}", name="BNSAppReservationBundle_front_edit_event", options={"expose"=true})
//      * @Rights("RESERVATION_ACCESS")
//      *
//      * @param String $slug slug de l'événement à éditer
//      */
//     public function editEventAction(Request $request, $slug)
//     {
//     	$rightManager = $this->get('bns.right_manager');
//     	// Check if user has rigths

//     	$reservationManager = $this->get('bns.reservation_manager');
//     	$event = $reservationManager->getEventBySlug($slug);

// 	$rm = $this->get('bns.right_manager');
// 	$rm->forbidIf($event->getReservation()->getUserId() != $rightManager->getUserSessionId());

//     	$reservations = ReservationQuery::create()->findByUserId($rightManager->getUserSessionId());

//     	// Création du formulaire avec tous les paramètres d'initialisation nécessaire
//     	$form = $this->createForm(new ReservationEventType($rightManager->getLocale()), new ReservationEventFormModel($event));

//     	if ($request->isMethod('post')) {
//             $form->bind($request);
//             if ($form->isValid()) {
//                 // Création du nouvel événement
//                 $event = $form->getData();
//                 $event->save();

//                 return $this->redirect($this->generateUrl('BNSAppReservationBundle_front_event_detail', array(
// 					'slug'	=> $event->getReservationEvent()->getSlug()
// 				)));
//             }
//         }

//     	return $this->render('BNSAppReservationBundle:Front:front_event_form.html.twig', array(
//             'form'		=> $form->createView(),
//             'event' 	=> $event,
//             'locale' 	=> $rightManager->getLocale(),
//             'agendas'   => $reservations,
// 	    'isEdition'	=> true
//     	));
//     }

}

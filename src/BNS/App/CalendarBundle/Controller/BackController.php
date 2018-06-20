<?php

namespace BNS\App\CalendarBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CalendarBundle\Form\Model\CalendarEventFormModel;
use BNS\App\CalendarBundle\Form\Type\CalendarEventType;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;


class BackController extends Controller
{
    /**
     * Homepage du back du module de calendrier
     *
     * @Route("/", name="BNSAppCalendarBundle_back_old")
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
        $this->get('stat.calendar')->visit();

    	$rightManager = $this->get('bns.right_manager');

    	// On récupère les paramètres d'initialisation de wdCalendar grâce au CalendarManager
    	$array = $this->get('bns.calendar_manager')->getWdCalendarInitParameters($request->getSession(), true);
    	$array['agendas'] = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));
    	$array['colors'] = Agenda::$colorsClass;

        if (array_key_exists($this->getUser()->getLang(), $this->getParameter('bns_date_calendar_patterns'))) {
            $array['myPattern'] = $this->getParameter('bns_date_calendar_patterns')[$this->getUser()->getLang()];
        } else {
            $array['myPattern'] = $this->getParameter('bns_date_calendar_patterns')['en'];
        }
    	return $this->render('BNSAppCalendarBundle:Back:index.html.twig', $array);
    }


    /**
     * Donne accès à la page d'étion de l'événement portant le slug $slug
     *
     * @Route("/editer-evenement/{slug}", name="BNSAppCalendarBundle_back_edit_event", options={"expose"=true})
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
	 *
     * @param String $slug slug de l'événement à éditer
     */
    public function editEventAction($slug)
    {
    	$rightManager = $this->get('bns.right_manager');
    	// Check if user has rigths

    	$calendarManager = $this->get('bns.calendar_manager');
    	$event = $calendarManager->getEventBySlug($slug);

		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$event->getAgenda()->getGroupId()));

    	$agendas = $calendarManager->getAgendasFromGroupIdsAndUser($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));

    	// Création du formulaire avec tous les paramètres d'initialisation nécessaire
    	$form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()), new CalendarEventFormModel($event));

    	if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());
			$this->get('bns.media.manager')->bindAttachments($event,$this->getRequest());
            if ($form->isValid()) {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();

				//Gestion des PJ
				$this->get('bns.media.manager')->saveAttachments($event->getAgendaEvent(), $this->getRequest());

                return $this->redirect($this->generateUrl('calendar_manager_event_visualisation', array(
					'slug'	=> $event->getAgendaEvent()->getSlug()
				)));
            }
        }

    	return $this->render('BNSAppCalendarBundle:Back:back_event_form.html.twig', array(
            'form'		=> $form->createView(),
            'event' 	=> $event,
            'locale' 	=> $rightManager->getLocale(),
            'agendas'   => $agendas,
			'isEdition'	=> true
    	));
    }

	/**
	 * Fourni le formulaire de création d'un nouvel événement
	 * Les paramètres GET que sont start, end et allday sont facultatives et ont une valeur par défaut; ils sont renseignés
	 * lorsque l'utilisateur souhaite créer un événement au moyen du drag sur le calendrier
	 *
	 * @Route("/ajouter-evenement/{start}/{end}/{allday}", name="BNSAppCalendarBundle_back_add_event", defaults={"start" = 0, "end" = 0, "allday" = 0}, options={"expose"=true})
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
	 */
    public function newEventAction($start, $end, $allday)
    {
    	$rightManager = $this->get('bns.right_manager');
    	$agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));

        //[bns-9411] Préselectionner la classe courante
        // On récupère l'agenda lié au groupe courant de l'utilisateur.
        // On lie cet agenda à un objet agendaEvent que l'on compléte avec la date courante comme date début et fin.
        $agendaEvent = new AgendaEvent();
        $currentGroupAgenda = AgendaQuery::create()->findOneByGroupId($rightManager->getCurrentGroupId());
        $agendaEvent->setAgenda($currentGroupAgenda);
        $agendaEvent->setDateStart(date('H:i'));
        $agendaEvent->setDateEnd(date('H:i'));
        //[bns-9411]

    	// Si $start et $end sont différents de 0, alors la création d'événement est demandé suite à un drag de la part de l'utilisateur
    	if ($start > 0 && $end > 0) {
            // Un traitement est opéré pour récupérer et prendre en considération la date de début et de fin fourni par l'utilisateur
            $agendaEvent->setDateStart($start);
            $agendaEvent->setDateEnd($end);
            if ($allday) {
                $agendaEvent->setIsAllDay(true);
            }
            else {
                $agendaEvent->setTimeStart(date('H:i', $start));
                $agendaEvent->setTimeEnd(date('H:i', $end));
            }

            $form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()), new CalendarEventFormModel($agendaEvent));
    	}
    	else {
                //[bns-9411] Préselectionner la classe courante
                // Ajout d'un objet agendaEvent pour pré-sélectioner la classe du groupe courant à la création du Form.
                //[bns-9411]
                // Sinon c'est une création d'événement classique, on créé donc juste un formulaire sans aucun traitement
                $form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()), new CalendarEventFormModel($agendaEvent));
            }

    	if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());
			$this->get('bns.media.manager')->bindAttachments($form->getData()->getAgendaEvent(),$this->getRequest());
            if ($form->isValid()) {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();

                //Gestion des PJ
				$this->get('bns.media.manager')->saveAttachments($event->getAgendaEvent(), $this->getRequest());

                //statistic action
                $this->get("stat.calendar")->newEvent();

                return $this->redirect($this->generateUrl('calendar_manager_event_visualisation', array(
					'slug' => $event->getAgendaEvent()->getSlug()
				)));
            }
    	}

    	return $this->render('BNSAppCalendarBundle:Back:back_event_form.html.twig', array(
            'form'      => $form->createView(),
            'locale'    => $rightManager->getLocale(),
            'agendas'   => $agendas,
			'event'		=> $form->getData()->getAgendaEvent(),
			'isEdition'	=> false
    	));
    }


    /**
     * Supprime l'événement dont le slug est $slug
     *
     * @Route("/supprimer-evenement/{slug}", name="BNSAppCalendarBundle_back_delete_event")
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function deleteEventAction($slug)
    {
		$calendarManager = $this->get('bns.calendar_manager');
		$event = $calendarManager->getEventBySlug($slug);
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$event->getAgenda()->getGroupId()));

    	$this->get('bns.calendar_manager')->deleteEvent($slug);

    	return $this->redirect($this->generateUrl('BNSAppCalendarBundle_back'));
    }

    /**
     * @Route("/modifier-evenement", name="BNSAppCalendarBundle_back_update_event")
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function quickUpdateEventAction(Request $request)
    {
    	// On récupère le slug de l'événement concerné par les modifications, et sa nouvelle date de début et de fin
    	$eventSlug = $request->request->get('event_slug');

    	$datetimeStart = $request->request->get('datetime_start');
    	$datetimeEnd = $request->request->get('datetime_end');

    	$calendarManager = $this->get('bns.calendar_manager');
    	$event = $calendarManager->getEventBySlug($eventSlug);

		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$event->getAgenda()->getGroupId()));

    	$eventUpdatedInfos = array(
    		'dtstart' 	=> strtotime($datetimeStart),
    		'dtend'		=> strtotime($datetimeEnd),
    		'allday'	=> $event->getIsAllday(),
    	);
    	$calendarManager->editEvent($event, $eventUpdatedInfos);

    	return new Response(json_encode(true));
    }

    /**
     * @Route("/changer-couleur-agenda/{agendaId}/{colorHex}", name="BNSAppCalendarBundle_back_color_change", options={"expose"=true})
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function changeAgendaColorAction($agendaId, $colorHex)
    {
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		$this->get('bns.right_manager')->forbidIf(true);
    	}

    	$agenda = AgendaQuery::create()
    		->add(AgendaPeer::ID, $agendaId)
    	->findOne();

		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$agenda->getGroupId()));

    	$agenda->saveColorClassFromColorHex($colorHex);

    	return new Response(json_encode(true));
    }

	/**
     * @Route("/visualisation/{slug}", name="calendar_manager_event_visualisation", options={"expose"=true})
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function eventDetailAction($slug)
    {
		$event = $this->get('bns.calendar_manager')->getEventBySlug($slug);
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$event->getAgenda()->getGroupId()));

		$array = array();
    	$array['event'] = $event;
        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');
    	$array['hoursdial'] = (($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2;
    	$array['sundial'] = ($hours * 60 + $minutes) / 4;
		$array['agendas'] = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));
    	$array['colors'] = Agenda::$colorsClass;

    	return $this->render('BNSAppCalendarBundle:Back:back_event_visualisation.html.twig', $array);
    }
}

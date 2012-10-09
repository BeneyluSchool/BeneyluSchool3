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
     * @Route("/", name="BNSAppCalendarBundle_back")
	 * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {   
    	$rightManager = $this->get('bns.right_manager');
    	
    	// On récupère les paramètres d'initialisation de wdCalendar grâce au CalendarManager
    	$array = $this->get('bns.calendar_manager')->getWdCalendarInitParameters($request->getSession(), true);
    	$array['agendas'] = $this->get('bns.calendar_manager')->getAgendasFromGroupIds($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));
    	$array['colors'] = Agenda::$colorsClass;
    	
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
    	$agendas = $calendarManager->getAgendasFromGroupIds($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));
    	
    	// Création du formulaire avec tous les paramètres d'initialisation nécessaire
    	$form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()), new CalendarEventFormModel($event));
    	
    	if ('POST' == $this->getRequest()->getMethod())
    	{
            $form->bindRequest($this->getRequest());
			
            if ($form->isValid())
            {
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();
                
				//Gestion des PJ
				$this->get('bns.resource_manager')->saveAttachments($event->getAgendaEvent(), $this->getRequest());

                return $this->redirect($this->generateUrl('BNSAppCalendarBundle_back'));
            }
        }
    	
    	return $this->render('BNSAppCalendarBundle:Back:edit_event.html.twig', array(
            'form'		=> $form->createView(),
            'event' 	=> $event,
            'locale' 	=> $rightManager->getLocale(),
            'agendas'   => $agendas,
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
    	
    	$form;
    	$agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds($rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK'));
    	// Si $start et $end sont différents de 0, alors la création d'événement est demandé suite à un drag de la part de l'utilisateur
    	if ($start > 0 && $end > 0)
    	{
            // Un traitement est opéré pour récupérer et prendre en considération la date de début et de fin fourni par l'utilisateur
            $agendaEvent = new AgendaEvent();
            $agendaEvent->setDateStart($start);
            $agendaEvent->setDateEnd($end);
            if ($allday)
            {
                $agendaEvent->setIsAllDay(true);
            }
            else
            {
                $agendaEvent->setTimeStart(date('H:i', $start));
                $agendaEvent->setTimeEnd(date('H:i', $end));    			
            }

            $form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()), new CalendarEventFormModel($agendaEvent));
    	}
    	else // Sinon c'est une création d'événement classique, on créé donc juste un formulaire sans aucun traitement
    	{
            $form = $this->createForm(new CalendarEventType($agendas, $rightManager->getLocale()));
    	}
    	
    	if ('POST' == $this->getRequest()->getMethod())
    	{
            $form->bindRequest($this->getRequest());
            if ($form->isValid())
            {
                    
                // Création du nouvel événement
                $event = $form->getData();
                $event->save();
				
                //Gestion des PJ
				$this->get('bns.resource_manager')->saveAttachments($event->getAgendaEvent(), $this->getRequest());
					                    					
                return $this->redirect($this->generateUrl('BNSAppCalendarBundle_back'));
            }
    	}
    	
    	return $this->render('BNSAppCalendarBundle:Back:new_event.html.twig', array(
            'form'      => $form->createView(),
            'locale'    => $rightManager->getLocale(),
            'agendas'   => $agendas,
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
    	$rightManager = $this->get('bns.right_manager');
    	
    	// AJAX?
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		$this->get('bns.right_manager')->forbidIf(true);
    	}
    	
    	$agenda = AgendaQuery::create()
    		->add(AgendaPeer::ID, $agendaId)
    	->findOne();
    	$agenda->saveColorClassFromColorHex($colorHex);
    	
    	return new Response(json_encode(true));
    }
}

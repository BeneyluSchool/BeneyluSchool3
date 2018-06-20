<?php

namespace BNS\App\CalendarBundle\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\AgendaPeer;

class FrontController extends Controller
{
    /**
     * @Route("/", name="BNSAppCalendarBundle_front_old")
	 * @RightsSomeWhere("CALENDAR_ACCESS")
     */
    public function indexAction(Request $request)
    {
        $this->get('stat.calendar')->visit();
		// Init wdCalendar
    	$array = $this->get('bns.calendar_manager')->getWdCalendarInitParameters($request->getSession());
    	$array['agendas'] = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS'));

        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
    	$array['currentHour'] = $hours = $date->format('H');
    	$minutes = $date->format('i');

    	$array['hoursdial'] = round((($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2);
		$array['sundial'] = round(($hours * 60 + $minutes) / 4);

        if (array_key_exists($this->getUser()->getLang(), $this->getParameter('bns_date_calendar_patterns'))) {
            $array['myPattern'] = $this->getParameter('bns_date_calendar_patterns')[$this->getUser()->getLang()];
        } else {
            $array['myPattern'] = $this->getParameter('bns_date_calendar_patterns')['en'];
        }

    	return $this->render('BNSAppCalendarBundle:Front:index.html.twig', $array);
    }


    /**
     * Méthode qui permet de récupérer la liste des événements à afficher par wdCalendar;
     * Appel AJAX effectué par wdCalendar; la date de début, la date de fin et le type d'affichage sont reçus par POST
	 *
     * @Route("/liste-evenements/{isAdmin}", name="BNSAppCalendarBundle_front_list_events", options={"expose"=true}, defaults={"isAdmin" = 0})
     */
    public function listEventsAction(Request $request, $isAdmin)
    {
    	// AJAX?
    	if (!$request->isXmlHttpRequest()) {
    		throw new NotFoundHttpException();
    	}

    	// Check if user has rigths
    	if (0 == $isAdmin) {
    		$this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere('CALENDAR_ACCESS');
    	}
    	else {
    		$this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere('CALENDAR_ACCESS_BACK');
    	}

    	// Récupération des paramètres showdate et viewtype passé par POST lors de l'appel de wdCalendar
    	$date = $request->request->get('showdate');
    	$viewtype = $request->request->get('viewtype');

    	// Sauvegarde en session de la date courante et du type d'affichage courant employé par l'utilisateur
    	$session = $request->getSession();
    	$session->set('bns.calendar.currentDate', $date);
    	$session->set('bns.calendar.currentViewType', $viewtype);

    	// Traitement sur la chaîne de caractère de date fourni par wdCalendar pour obtenir
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
    		throw new \Exception('Illegal viewtype given: '.$viewtype);
    	}

    	$agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS'));
    	// La date de début et de fin est obtenu; on peut maintenant faire appel au CalendarManager pour qu'il nous retourne
    	// tous les événements compris entre $dateStart et $dateEnd (bornes incluses);
   		$events = $this->get('bns.calendar_manager')->selectEventsByDates(strtotime($dateStart), strtotime($dateEnd), $agendas, $isAdmin);

    	return new Response(json_encode($events));
    }


    /**
     * Permet d'afficher les détails de l'événement qui a pour slug $slug
     * @Route("/detail-evenement/{slug}", name="BNSAppCalendarBundle_front_event_detail", options={"expose"=true})
	 * @RightsSomeWhere("CALENDAR_ACCESS")
	 *
     * @param String $slug
     */
    public function eventDetailAction($slug)
    {
		$array = array();
    	$event = $this->get('bns.calendar_manager')->getEventBySlug($slug);

		$rm = $this->get('bns.right_manager');

        $canSee = false;

        if(date('m') == 12)
        {
            $agendaXmas = AgendaQuery::create()->findOneByGroupId(1);
            if ($agendaXmas) {
                $agendaXmasId = $agendaXmas->getId();
                if($event->getAgendaId() == $agendaXmasId)
                {
                    $canSee = true;
                }
            }
        }

		$rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS',$event->getAgenda()->getGroupId()) && !$canSee);

    	$array['event'] = $event;
        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');
    	$array['hoursdial'] = round((($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2);
    	$array['sundial'] = round(($hours * 60 + $minutes) / 4);

    	return $this->render('BNSAppCalendarBundle:Front:event_detail.html.twig', $array);
    }

    /**
     * @Route("/detail-evenement-anniversaire", name="front_birthday_event_detail", options={"expose"=true})
     */
    public function birthdayEventDetailAction()
    {
        // Check si la méthode de la requête est POST
        $request = $this->getRequest();
        if (!$request->isMethod('POST')) {
            throw new HttpException(500, 'Request must be send by POST\'s method');
        }

        // Check si les informations nécessaires à la contruction d'un événement anniversaire sont fournies
        $title = $request->get('title', null);
        $description = $request->get('description', null);
        $date = $request->get('date', null);
        if (null == $title || null == $description || null == $date) {
            throw new HttpException(500, 'Missing parameters, you must provide: title, description, date.');
        }

        // Création d'un objet AgendaEvent pour utiliser le même template twig que l'affichage d'un événement classique
        $agendaEvent = new AgendaEvent();
        $agendaEvent->setTitle($title);
        $agendaEvent->setDescription($description);

        $dateArray = explode('-', $date);

        $agendaEvent->setDateStart(mktime(0, 0, 0, $dateArray[0], $dateArray[1], $dateArray[2]));
        $agendaEvent->setIsAllDay(true);
        $agendaEvent->setAgenda(AgendaQuery::create()
            ->add(AgendaPeer::GROUP_ID, $this->get('bns.right_manager')->getCurrentGroupId())
        ->findOne());

        $array = array();

        $array['event'] = $agendaEvent;

        // Calcule des informations nécessaires pour initialiser correctement l'horloge
        $date = new \DateTime('now',new \DateTimeZone($this->get('bns.user_manager')->getUser()->getTimezone()));
        $array['currentHour'] = $hours = $date->format('H');
        $minutes = $date->format('i');
        $array['hoursdial'] = round((($hours > 12? $hours - 12 : $hours) * 60 + $minutes) / 2);
        $array['sundial'] = round(($hours * 60 + $minutes) / 4);

        return $this->render('BNSAppCalendarBundle:Front:event_detail.html.twig', $array);
    }

    /**
     * @Route("/export/{agendaId}/{secretKey}", name="front_agenda_export", options={"expose"=true})
     */
    public function getExportAction ($agendaId, $secretKey)
    {
        if (md5('calendar-' . $agendaId) !== $secretKey) {
            throw new BadRequestHttpException('Lien incorrect');
        }

        $agenda = AgendaQuery::create()->findPk($agendaId);
        return $this->render('BNSAppCalendarBundle:Back:export.html.twig', array('events' => $agenda->getAgendaEvents()));
    }
}

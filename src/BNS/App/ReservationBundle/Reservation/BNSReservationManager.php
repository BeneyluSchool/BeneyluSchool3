<?php

namespace BNS\App\ReservationBundle\Reservation;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Exception;

use BNS\App\ReservationBundle\Model\ReservationPeer;
use BNS\App\ReservationBundle\Model\ReservationQuery;
use BNS\App\ReservationBundle\Model\ReservationEventPeer;
use BNS\App\ReservationBundle\Model\ReservationEventQuery;
use BNS\App\ReservationBundle\Model\ReservationEvent;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use \Criteria;

class BNSReservationManager
{
	/**
	 * @var String si aucun type d'affichage n'est renseigné par l'utilisateur, $DEFAULT_VIEW_TYPE est utilisé par défaut
	 */
	public static $DEFAULT_VIEW_TYPE = 'week';

	private $container;

	/**
	 * Constructeur du service BNSReservationManager; est dépendant de iCalcreator (lib externe), on rapatrie les classes
	 * à l'aide d'un require
	 */
	public function __construct($container)
	{
        require __DIR__.'/../Librairies/iCalcreator.php';
		$this->container = $container;
	}


	/**
	 * @param array $eventInfos contient tous les paramètres nécessaires à la création d'un événement
	 * La clé d'un paramètre doit être le nom du paramètre; Les paramètres obligatoires sont dtend, dtstart et summary;
	 * Le paramètre allday (de type boolean) peut également être renseigné pour indiquer que c'est un événement qui dure toute
	 * la journée (donc pas d'heure de début et de fin)
	 * @throws Exception lève une exception si les champs obligatoire (dtstart, dtend et summary) ne sont pas renseigné
	 */
	public function createEvent($reservationId, array $eventInfos)
	{
            // icalcreator vevent object process
            $event = new vevent();

            // tableau qui contient les attributs que l'utilisateur doit obligatoirement fournir
            $obligatoryProperties = array('dtstart', 'dtend', 'summary');
            // On boucle sur le tableau eventInfos fourni en paramètre pour s'assurer de la présence de ces attributs
            foreach ($obligatoryProperties as $obligatoryProperty)
            {
                if (!array_key_exists($obligatoryProperty, $eventInfos))
                {
                    throw new Exception($obligatoryProperty.' argument is missing in array passing as parameter of createEvent() method.');
                }

                if (0 == strcmp($obligatoryProperty, 'dtstart') || 0 == strcmp($obligatoryProperty, 'dtend'))
                {
                    $event->setProperty($obligatoryProperty, array('timestamp' => $eventInfos[$obligatoryProperty]));
                }
                else
                {
                    $event->setProperty($obligatoryProperty, $eventInfos[$obligatoryProperty]);
                }
            }

            // On ajoute ici tous les autres champs qu'à pu fournir l'utilisateur
            foreach ($eventInfos as $key => $value)
            {
                if (in_array($key, $obligatoryProperties) || strcmp($key, 'allday') == 0 || (strcmp($key, 'rrule') == 0 && !is_array($eventInfos['rrule'])))
                {
                        continue;
                }

                $event->setProperty($key, $value);
            }

            // l'objet vevent étant hydraté, on génère maintenant le code vevent selon la norme iCalendar
            $veventCode = $this->generateICalendarVeventCode($event);

            // Création de l'événement dans le modèle ReservationEvent (côté Propel)
            $reservationEvent = new ReservationEvent();
            $reservationEvent->setTitle($eventInfos['summary']);
            $reservationEvent->setIcalendarVevent($veventCode);
            $reservationEvent->setDateStart(date('Y-m-d', $eventInfos['dtstart']));
            if (array_key_exists('dtend', $eventInfos))
            {
                    $reservationEvent->setDateEnd(date('Y-m-d', $eventInfos['dtend']));
            }

            $reservationEvent->setIsRecurring((false === $event->getProperty('RRULE')? false : true));
            $reservationEvent->setIsAllDay((array_key_exists('allday', $eventInfos) && $eventInfos['allday']? true : false));
            $reservationEvent->setReservationId($reservationId);
            $reservationEvent->setReservationItem($eventInfos['reservationItem']);

            // sauvegarde de l'objet ReservationEvent nouvellement créé
            $reservationEvent->save();

			return $reservationEvent;
	}



	/**
	 *
	 * @param ReservationEvent $reservationEvent correspond à l'objet dans notre modèle de l'événement que l'on souhaite modifier
	 * @param array $eventUpdatedInfos contient tous les paramètres nécessaires à l'édition d'un événement
	 * La clé d'un paramètre doit être le nom du paramètre; Les paramètres obligatoires sont dtend, dtstart et summary;
	 * Le paramètre allday (de type boolean) peut également être renseigné pour indiquer que c'est un événement qui dure toute
	 * la journée (donc pas d'heure de début et de fin)
	 * @throws Exception si l'objet agendaEvent fourni en paramètre = null, une exception est levée
	 */
	public function editEvent(ReservationEvent $reservationEvent, array $eventUpdatedInfos)
	{
		// On test que l'objet agendaEvent fourni en paramètre ne soit pas égale à null, sinon on lève une exception
		if (null == $reservationEvent) {
			throw new Exception('ReservationEvent given equals to null!');
		}

		// In case of update
		if (isset($eventUpdatedInfos['summary'])) {
			$reservationEvent->setTitle($eventUpdatedInfos['summary']);
		}

		// création d'un objet vevent
		$vevent = new vevent();
		// On hydrate l'objet vevent à partir du code vevent (suivant la norme iCalendar) que l'on a stocké dans l'objet
		// ReservationEvent
		$vevent->parse($reservationEvent->getIcalendarVevent());

		// On boucle sur tous les informations contenues dans le tableau eventUpdatedInfos fourni en paramètre et on effectue
		// des modifications demandé par l'utilisateur
		foreach ($eventUpdatedInfos as $key => $value)
		{
			if ('dtstart' == $key || 'dtend' == $key)
			{
				$vevent->setProperty($key, array('timestamp' => $value));

				$date = date('Y-m-d', $value);
				if ('dtstart' == $key)
				{
						$reservationEvent->setDateStart($date);
				}
				else
				{
						$reservationEvent->setDateEnd($date);
				}
			}
			else
			{
				if ('rrule' == $key)
				{
					//$vevent->deleteProperty('rrule');
					while ($vevent->deleteProperty('rrule'))
					{
						continue;
					}

					if ('' != $value)
					{
						$vevent->setProperty($key, $value);
					}

					$reservationEvent->setIsRecurring('' == $value? false : true);
				}
				else
				{
					 $vevent->setProperty($key, $value);
				}
			}
		}

		$reservationEvent->setIsAllDay(isset($eventUpdatedInfos['allday']) && true === $eventUpdatedInfos['allday']? true : false);

		// On regénère le code vevent à partir de l'objet vevent modifié
		$reservationEvent->setIcalendarVevent($this->generateICalendarVeventCode($vevent));

		// Enfin, on sauvegarde l'objet ReservationEvent
		$reservationEvent->save();

		return $reservationEvent;
	}



	/**
	 *
	 * @param String $slug slug de l'événement que l'on souhaite supprimer
	 * @throws Exception lève une exception si aucun événement est associé au slug fournit en paramètre
	 */
	public function deleteEvent($slug)
	{
		// retrieve the event with id provides by user
		$eventToDelete = ReservationEventQuery::create()
			->add(ReservationEventPeer::SLUG, $slug)
		->findOne();

		// test if the required event exist or not
		if (null == $eventToDelete)
		{
			throw new Exception('Event with slug '.$slug.' does not exist!');
		}

		// delete the event
		$eventToDelete->delete();
	}



	/**
	 * Méthode qui permet, à partir d'une date de début et de fin, de récupérer tous les événements accessible par l'utilisateur
	 *
	 * @param timestamp $dateStart date à partir de laquelle on souhaite récupérer tous les événements
	 * @param timestamp $dateEnd date jusqu'à laquelle on souhaite récupérer tous les événements
	 */
	public function selectEventsByDates($dateStart, $dateEnd, $reservations, $isEditable = false, $addHomework = false)
	{
		// Initialisation du tableau qui sera reçu par wdCalendar
		$wdCalendarEvents = array();
		$wdCalendarEvents['events'] = array();
		$wdCalendarEvents["issort"] = false;
		$wdCalendarEvents["start"] = date('m/d/Y H:i', $dateStart);
		$wdCalendarEvents["end"] = date('m/d/Y H:i', $dateEnd + 24 * 60 * 60);
		$wdCalendarEvents['error'] = null;

		$ids = array();
		foreach ($reservations as $reservation) {
			$ids[] = $reservation->getId();
		}

		$events = ReservationEventQuery::create('a')
			->joinWith('Reservation')
			->add(ReservationPeer::ID, $ids, \Criteria::IN)
    		// Sélectionne les événements dont la date de début est > $dateStart et la date de fin est < $dateEnd + 1 jour
    		 	->condition('cond1', 'a.DateStart < ?', date('Y-m-d', $dateEnd + 24 * 60 * 60))
    			->condition('cond2', 'a.DateEnd >= ?', date('Y-m-d', $dateStart))
    		->combine(array('cond1', 'cond2'), 'and', 'cond-12')
    			->condition('cond3', 'a.IsRecurring = ?', true)
    		->where(array('cond-12', 'cond3'), 'or')
    	->find();

    	if (0 == count($events)) {
    		return $wdCalendarEvents;
    	}

    	$config = array('unique_id' => 'bns.reservation');
    	// On créé un vcalendar pour pouvoir déléguer le travail de tri des événements à iCalcreator
    	$vcalendar = new vcalendar($config);

    	// On boucle sur tous les ReservationEvent que l'on a récupéré depuis la base de données et créer les objets vevent
    	foreach ($events as $event) {
    		$vevent = new vevent();
    		$vevent->parse($event->getIcalendarVevent());
    		// on ajoute des paramètres custom à chaque vevent pour s'éviter de faire par la suite de nouvelle requête en base
    		$customParameters = array(
    			'SLUG' 			=> $event->getSlug(),
    			'COLOR' 		=> $event->getColorClass(),
    			'ALL_DAY' 		=> $event->getIsAllDay(),
    			'SEVERAL_DAYS'	=> $this->isSeveralDaysEvent($vevent->getProperty('dtstart'), $vevent->getProperty('dtend')),
    			'IS_RECURRING'	=> $event->getIsRecurring(),
    			'AGENDA_ID'		=> $event->getReservationId(),
    		);
    		$vevent->setProperty('comment', $vevent->getProperty('comment'), $customParameters);
    		$vevent->setProperty('summary', $event->getTitle());
    		// On ajoute le nouvel objet vevent créé à l'objet vcalendar d'iCalcreator
    		$vcalendar->setComponent($vevent);
    	}

    	// Le calendrier vcalendar d'iCalcreator est maintenant hydraté, on peut utiliser son select pour trier les vevent
    	$vevents = $vcalendar->selectComponents(
			date('Y', $dateStart), date('m', $dateStart), date('d', $dateStart),
			date('Y', $dateEnd), date('m', $dateEnd), date('d', $dateEnd)
		);

    	if (false === $vevents)
    	{
    		return $wdCalendarEvents;
    	}

    	// Les vevents sont triés, on commence dès à présent à formater les événements en fonction du format de wdCalendar
    	$vevents = $this->vEventCustomSort($vevents);

	$isFirstLoop = true;
    	// On boucle à présent sur tous les événements qui ont survécu aux différents tris pour les ajouter au tableau que recevra wdCalendar
    	foreach ($vevents as $vevent) {
    		$customParameters = $vevent->getProperty('comment', false, true);
    		$slug = $customParameters['params']['SLUG'];
    		$color =  $customParameters['params']['COLOR'];
    		$isAllDay = $customParameters['params']['ALL_DAY'];
    		$isSevaralDays = $customParameters['params']['SEVERAL_DAYS'];
    		$reservationId = $customParameters['params']['AGENDA_ID'];
    		$isRecurring = $customParameters['params']['IS_RECURRING'];

    		$start = $this->convertToStringDate($vevent->getProperty('dtstart'));
    		$end = $this->convertToStringDate($vevent->getProperty('dtend'));
    		$wdCalendarEvents['events'][] = array(
		    	$slug, // slug de l'événement; ici j'ai donné le slug de l'objet ReservationEvent correspondant
		    	$vevent->getProperty('summary'), // titre de l'événement
		    	$start, // date de début de l'événement
		    	$end, // date de fin de l'événement
		    	$isAllDay, // boolean : true = dure toute la journée, sinon false
		    	$isSevaralDays, // boolean : true = dure sur plusieurs jour, sinon false
		    	0, // boolean : true = événement récurrent, false sinon; On gère nous même la réccurence, wdCalendar ne s'occupe que de la vue
		    	0, // Code couleur allant de 0 à 13, il faudrait tweaker ça du côté de wdCalendar FIXME
		    	($isRecurring? false : $isEditable), // boolean : true = éditable, false sinon
		    	$vevent->getProperty('location')? $vevent->getProperty('location') : ' ', // éventuel lieu de l'événement
		    	'', // participant
		    	'agenda-'.$reservationId . ($isFirstLoop? ' agenda-event' : ''), // la chaîne de caractère "agenda-" concaténé avec l'id de l'agenda
		    	$color,
				($isRecurring? 'recurrent' : '')
    		);
		$isFirstLoop = false;
    	}

    	return $wdCalendarEvents;
	}


	public function getReservationsFromGroupIds($ids)
	{
		return ReservationQuery::create('a')
			->joinWith('Group', Criteria::LEFT_JOIN)
			->where('a.GroupId IN ?', $ids)
			->orderBy('a.Id')
		->find();
	}

	/**
	 * Permet de récupérer un objet de type ReservationEvent à partir du slug $slug;
	 * L'agenda associé est également accessible; l'objet est complètement hydraté (cf la classe ReservationEvent)
	 *
	 * @param String $slug est le slug à partir duquel on souhaite retrouver un événement
	 * @throws NotFoundHttpException est levé si aucun événement est retrouvé à partir du slug fourni en paramètre
	 * @throws Exception lève une exception si les conditions minimales requises concernant les règles de récurrences ne sont pas réunies
	 */
	public function getEventBySlug($slug)
	{
		$reservationEvent = ReservationEventQuery::create()
			->joinWith('Reservation a')
		->findOneBySlug($slug);

		if (null == $reservationEvent) {
			throw new NotFoundHttpException('The event with the slug ' . $slug . ' does not exist!');
		}

		return $this->hydrateEvent($reservationEvent);
	}

	/**
	 * @param int $id
	 *
	 * @return ReservationEvent
	 *
	 * @throws NotFoundHttpException
	 */
	public function getEventById($id)
	{
		$reservationEvent = ReservationEventQuery::create('ae')
			->joinWith('Reservation a')
		->findPk($id);

		if (null == $reservationEvent) {
			throw new NotFoundHttpException('The reservation event with id : ' . $id . ' is NOT found !');
		}

		return $this->hydrateEvent($reservationEvent);
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\ReservationEvent $reservationEvent
	 *
	 * @return ReservationEvent
	 *
	 * @throws Exception
	 */
	public function hydrateEvent(ReservationEvent $reservationEvent)
	{
		$vevent = new vevent();
		$vevent->parse($reservationEvent->getIcalendarVevent());
		$description = $vevent->getProperty('description');
		$author = $vevent->getProperty('organizer');
		$location = $vevent->getProperty('location');
		$reservationEvent->setDescription($description === false? '' : $description);
		$reservationEvent->setAuthor($author === false? '' : str_replace('MAILTO:', '', $author));
		$reservationEvent->setLocation($location === false? '' : $location);
		$dateStart = $vevent->getProperty('dtstart');
		$reservationEvent->setTimeStart($dateStart['hour'].':'.$dateStart['min']);
		$dateEnd = $vevent->getProperty('dtend');
		$reservationEvent->setTimeEnd($dateEnd['hour'].':'.$dateEnd['min']);
		$recurringParams = $vevent->getProperty('rrule');

		if (false !== $recurringParams && is_array($recurringParams) && count($recurringParams) > 0) {
			$reservationEvent->setRecurringType($recurringParams['FREQ']);
			if (array_key_exists('COUNT', $recurringParams)) {
				$reservationEvent->setRecurringCount($recurringParams['COUNT']);
			}
			elseif (array_key_exists('UNTIL', $recurringParams)) {
				$recurringEndDate = $recurringParams['UNTIL'];
				$reservationEvent->setRecurringEndDate($recurringEndDate['year'].'-'.$recurringEndDate['month'].'-'.$recurringEndDate['day']);
			}
			else {
				throw new Exception('Recurring rules need at least 2 paramaters (FREQ and COUNT or UNTIL); one parameter is missing!');
			}
		}

		return $reservationEvent;
	}

	/**
	 * Retourne un tableau qui contient tous les paramètres d'initialisations nécessaires au fonctionnement spécifique
	 * de wdCalendar
	 *
	 * @param Session $session correspond à la session contenue dans Request $request
	 * @param boolean $isAdmin indique si oui ou non on souhaite des paramètres d'initialisation pour l'interface d'admin
	 */
	public function getWdReservationInitParameters($session, $isAdmin = false)
	{
		$params = array();

		if (null != $session->get('bns.reservation.currentDate'))
		{
			$params['dateShow'] = $session->get('bns.reservation.currentDate');
		}

		if (null != $session->get('bns.reservation.currentViewType'))
		{
			$params['viewType'] = $session->get('bns.reservation.currentViewType');
		}
		else
		{
			$params['viewType'] = self::$DEFAULT_VIEW_TYPE;
		}

		if (true === $isAdmin)
		{
			$params['is_admin'] = true;
		}

		return $params;
	}

	/**
	 * Méthode qui permet de trier les événements de type vevent; est utilisé sur les événements qui résultent
	 * du selectComponents() de vcalendar; Certains événements doivent être concaténé tandis que d'autres doivent avoir leur
	 * date de début ajusté
	 *
	 * @param array $vevents contient des objets de type vevent
	 * @return array renvoi tous les événements triés/concaténés, selon le contexte de chaque événement, dans un tableau
	 */
	private function vEventCustomSort(array $vevents)
	{
		$vEventsSorted = array();
		$vevents1D = $this->convertTo1DArray($vevents);
		foreach ($vevents1D as $vevent)
		{
			$customParameters = $vevent->getProperty('comment', false, true);
			$vevent->setProperty('comment', $vevent->getProperty('comment'), $customParameters['params']);
			// get the current dtstart from the event
			$currentDtStart = $vevent->getProperty('X-CURRENT-DTSTART');
			$currentDtStart = $currentDtStart[1];

			// get the dtstart of the current event
			$dStart = $vevent->getProperty('dtstart');
			$dateStr = $dStart['year'].'-'.$dStart['month'].'-'.$dStart['day'].' '.$dStart['hour'].':'.$dStart['min'].':'.$dStart['sec'];

			// Recurring
			if($dateStr != $currentDtStart && $customParameters['params']['IS_RECURRING'])
			{
				$vevent->setProperty('dtstart', array('timestamp' => strtotime($currentDtStart)));
				$currentDtEnd = $vevent->getProperty('X-CURRENT-DTEND');
				$currentDtEnd = $currentDtEnd[1];
				$vevent->setProperty('dtend', array('timestamp' => strtotime($currentDtEnd)));
			}

			// Event during several days process
			if ($customParameters['params']['SEVERAL_DAYS'])
			{
				$vEventsSorted[$vevent->getProperty('uid')] = $vevent;
			}
			else
			{
				$vEventsSorted[] = $vevent;
			}
		}

		return $vEventsSorted;
	}

	/**
	 * Méthode qui "casse" l'arborescence en année->mois->jour rendu par la méthode selectComponents() de vcalendar
	 * pour remettre tous les objets dans la même dimension
	 * @param array $array tableau d'objet de type vevent rendu par la méthode selectComponents() de vcalendar
	 * @return array tableau à une dimension qui contient tous les objets contenus dans le tableau fourni en paramètre
	 */
	private function convertTo1DArray(array $array)
	{
		$result = array();

		foreach ($array as $months) {
			foreach ($months as $days) {
				foreach ($days as $events) {
					foreach ($events as $event)
					{
						$result[] = $event;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Converti le tableau de date fourni par la méthode getProperty('dtstart') ou getProperty('dtend') de vevent
	 * en une chaîne de caractère
	 *
	 * @param array $date tableau de date renvoyé par les méthodes getProperty('dtstart'), getProperty('dtend')
	 * @return String une chaîne de caractère contenant la date au format :  MM/dd/YYYY H:i
	 */
	private function convertToStringDate(array $date, $returnData = true, $separator = '/')
	{
		$dateStr = $date['month'].$separator.$date['day'].$separator.$date['year'];
		if ($returnData) {
			$dateStr .= ' '.$date['hour'].':'.$date['min'];
		}

		return $dateStr;
	}


	/**
	 * Test si un événement dure plusieurs jours ou non
	 *
	 * @param array $dateStart tableau de date de début fourni par getProperty('dtstart') de vevent
	 * @param array $dateEnd tableau de date de fin fourni par getProperty('dtend') de vevent
	 * @return boolean true si l'événement dure plusieurs jours, false sinon
	 */
	private function isSeveralDaysEvent(array $dateStart, array $dateEnd)
	{
		return !($dateStart['year'] == $dateEnd['year'] && $dateStart['month'] == $dateEnd['month'] && $dateStart['day'] == $dateEnd['day']);
	}


	/**
	 * Retourne le code vevent dans la norme iCalendar associé à l'objet vevent $vevent fourni en paramètre
	 *
	 * @param vevent $vevent objet dont on souhaite obtenir le code vevent dans la norme iCalendar
	 * @return string chaîne de caractère correspondant au code vevent associé généré
	 */
	private function generateICalendarVeventCode($vevent)
	{
		// icalcreator object process
		$reservation = new vcalendar();

		// add the vevent component to container vcalendar
		$reservation->setComponent($vevent);
		// generate the icalendar format from vcalendar
		$str = $reservation->createCalendar();

		// extract from icalendar format the vevent code
		$str = substr($str, strpos($str, 'BEGIN:VEVENT'));
		$str = substr($str, 0, strpos($str, 'END:VEVENT') + 10);

		return $str;
	}

	/**
	 *
	 */
	private function getUsersBirthdayEvent($dateStart, $dateEnd, $reservations)
	{
		$currentGroupId = $this->container->get('bns.right_manager')->getCurrentGroupId();
		$currentGroupReservation = null;
		// Tableau qui classe les utilisateurs par date de naissance
		$userBirthdays = array();
		// On boucle sur tous les agendas pour récupérer les groupes associés
		foreach ($reservations as $reservation) {
			if ($currentGroupId == $reservation->getGroupId()) {
				$currentGroupReservation = $reservation;
			}
			//Reservation personnel
			if($reservation->getGroup() != null)
			{
			    // Pour chaque groupe, on boucle sur tous les utilisateurs
			    foreach ($this->container->get('bns.group_manager')->setGroup($reservation->getGroup())->getUsers(true) as $user) {
				    $userBirthdayTimestamp = (null != $user->getBirthday()? $user->getBirthday()->getTimestamp(): null);

				    // Si aucune date de naissance est setté, on passe à l'utilisateur suivant
				    if (null == $userBirthdayTimestamp) {
					    continue;
				    }

				    // On calcule le nombre d'année qui sépare la date de naissance de l'utilisateur et la date à afficher
				    $yearDiff = intval(date('Y', $dateStart)) - intval(date('Y', $userBirthdayTimestamp));
				    // On créé maintenant la date d'anniversaire par rapport à l'année courante
				    $userBirthdayTimestamp = mktime(0, 0, 0, date('m', $userBirthdayTimestamp), date('d', $userBirthdayTimestamp), date('Y', $userBirthdayTimestamp) + $yearDiff);
				    if ($userBirthdayTimestamp < $dateStart || $userBirthdayTimestamp > $dateEnd) {
					    continue;
				    }

				    if (!isset($userBirthdays[$userBirthdayTimestamp])) {
					    $userBirthdays[$userBirthdayTimestamp] = array(
						    $user->getId() => $user->getFullName()
					    );
				    }
				    elseif (!isset($userBirthdays[$userBirthdayTimestamp][$user->getId()])) {
					    $userBirthdays[$userBirthdayTimestamp][$user->getId()] = $user->getFullName();
				    }
			    }
			}
		}

		$vevents = array();
		foreach ($userBirthdays as $birthday => $users) {
			$vevent = new vevent();
			$isSeveralUsersBirthday = count($users) > 1;
			$eventTitle = ($isSeveralUsersBirthday? count($users) . ' anniversaires' : 'Anniversaire : '. reset($users));
			$vevent->setProperty('summary', $eventTitle);

			$descriptionStr = 'A cette date, il y a l\'anniversaire de ';

			if ($isSeveralUsersBirthday) {
				$descriptionStr .= ': ' . implode(', ', $users);
			}
			else {
				$descriptionStr .= reset($users);
			}

			$vevent->setProperty('description', $descriptionStr . '.');
			$vevent->setProperty('dtstart', array('timestamp' => $birthday));
			$vevent->setProperty('dtend', array('timestamp' => $birthday));

			$vevents[] = $vevent;

			// On vide la mémoire
			$vevent = null;
		}

		$wdCalendarEvents = array();
		foreach ($vevents as $vevent)
    	{
    		$birthdaySlug = $this->convertToStringDate($vevent->getProperty('dtstart'), false, '-');
    		$birthdayStr = $this->convertToStringDate($vevent->getProperty('dtstart'));

    		$wdCalendarEvents[] = array(
		    	'anniversaire/'. $birthdaySlug . '_' . $vevent->getProperty('summary') . '_' . $vevent->getProperty('description'), // start de l'événement; ici j'ai donné le slug de l'objet ReservationEvent correspondant
		    	$vevent->getProperty('summary'), // titre de l'événement
		    	$birthdayStr, // date de début de l'événement
		    	$birthdayStr, // date de fin de l'événement
		    	true, // boolean : true = dure toute la journée, sinon false
		    	false, // boolean : true = dure sur plusieurs jour, sinon false
		    	0, // boolean : true = événement récurrent, false sinon; On gère nous même la réccurence, wdCalendar ne s'occupe que de la vue
		    	0, // Code couleur allant de 0 à 13, il faudrait tweaker ça du côté de wdCalendar FIXME
		    	false, // boolean : true = éditable, false sinon
		    	'', // éventuel lieu de l'événement
		    	'', // participant
		    	'agenda-' . $currentGroupReservation->getId(), // la chaîne de caractère "agenda-" concaténé avec l'id de l'agenda
		    	$currentGroupReservation->getColorClass(),
				'recurrent'
    		);
    	}

		return $wdCalendarEvents;
	}
}
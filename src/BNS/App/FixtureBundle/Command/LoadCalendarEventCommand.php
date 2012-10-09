<?php
namespace BNS\App\FixtureBundle\Command;

use BNS\App\CoreBundle\Model\AgendaQuery;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Propel;

/**
 *
 * @author Eric Chau
 */
class LoadCalendarEventCommand extends ContainerAwareCommand
{
		
    protected function configure()
    { 
        $this
			->setName('bns:load-calendar')
			->setDescription('Load calendar\'s events')
   			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
        ;
    }
	
	protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new \InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);
    }
    
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
    	$this->con = Propel::getConnection($connectionName);
    	Propel::setForceMasterConnection(true);
    	
    	$summarys			= file_get_contents(__DIR__ . '/../Resources/data/Calendar/summary.txt');
    	$summarys			= preg_split('#\r\n#', $summarys);
    	$countSummarys		= count($summarys) - 1;
    	
    	$descriptions		= file_get_contents(__DIR__ . '/../Resources/data/Calendar/description.txt');
    	$descriptions		= preg_split('#\r\n#', $descriptions);
    	$countDescriptions	= count($descriptions) - 1;
    	
    	$locations			= file_get_contents(__DIR__ . '/../Resources/data/Calendar/location.txt');
    	$locations			= preg_split('#\r\n#', $locations);
    	$countLocations		= count($locations) - 1;
    	
    	$authors			= file_get_contents(__DIR__ . '/../Resources/data/Calendar/author.txt');
    	$authors			= preg_split('#\r\n#', $authors);
    	$countAuthors		= count($authors) - 1;
    	
    	try
    	{
    		$this->con->beginTransaction();
			
    		$agendas = AgendaQuery::create()->find();
    		
    		$agendasId = array();
    		foreach ($agendas as $agenda)
    		{
    			$agendasId[] = $agenda->getId();
    		}
    		
    		$calendarManager = $this->getContainer()->get('bns.calendar_manager');
    		
    		$currentMonday = date('o-\WW');
    		
    		for ($i = 0; $i < count($agendasId); $i++)
    		{	    		
	    		$this->writeSection($output, 'Event 1'.'-'.$i);
	    		// Evénement : début mercredi à 9h, fin à 12h, de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
	    			'dtstart' 		=> strtotime($currentMonday.'+2 days 9 hours'),
	    			'dtend' 		=> strtotime($currentMonday.'+2 days 12 hours'),
	    			'summary' 		=> $summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
	    			'location'		=> $locations[rand(0, $countLocations)],
	    			'organizer'		=> $authors[rand(0, $countAuthors)],
	    		));
	    		
	    		$this->writeSection($output, 'Event 2'.'-'.$i);
	    		// Evénement : début mardi et fin vendredi de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+1 days'),
		    		'dtend' 		=>strtotime($currentMonday.'+4 days'),
		    		'summary' 		=> $summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
	    		));
	    		
	    		$this->writeSection($output, 'Event 3 - with recurrence'.'-'.$i);
	    		// Evénement : tous les jours de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+0 day 14 hours'),
		    		'dtend' 		=> strtotime($currentMonday.'+0 day 16 hours'),
		    		'summary' 		=> $summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
		    		'rrule'			=> array(
		    							'FREQ'	=> 'DAILY',
		    							'UNTIL' => array('timestamp' => strtotime($currentMonday.'+7 days')),
		    		),
	    		));
	    		
				/*
	    		$this->writeSection($output, 'Event 4'.'-'.$i);
	    		// Evénement : début vendredi de la semaine précédente et fin mercredi de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday) - 3 * 24 * 60 * 60,
		    		'dtend' 		=> strtotime($currentMonday.'+2 days'),
		    		'summary' 		=> 'Event 4',//$summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
	    		));
	    		
	    		$this->writeSection($output, 'Event 5'.'-'.$i);
	    		// Evénement : début jeudi de la semaine courante et fin dimanche de la semaine suivante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+3 days'),
		    		'dtend' 		=> strtotime($currentMonday.'+13 days'),
		    		'summary' 		=> 'Event 5',//$summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
	    		));
				
	    		
	    		
	    		$this->writeSection($output, 'Event 7'.'-'.$i);
	    		// Evénement : commence et termine dans un mois par rapport à la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+1 month 14 hours'),
		    		'dtend' 		=> strtotime($currentMonday.'+1 month 16 hours'),
		    		'summary' 		=> 'Event 7',//$summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
	    		));
	    		
	    		$this->writeSection($output, 'Event 8'.'-'.$i);
	    		// Evénement : début vendredi de la semaine précédente et fin mercredi de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday) - 3 * 24 * 60* 60,
		    		'dtend' 		=> strtotime($currentMonday.'+7 days'),
		    		'summary' 		=> 'Event 8',//$summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
	    		));
	    		
	    		// FIXME: écrire la règle de récurrence différement ?
	    		$this->writeSection($output, 'Event 9 - with recurrence'.'-'.$i);
	    		// Evénement : commence le vendredi de la semaine précédente par rapport à la semaine courante, se termine le dimanche
	    		// de la semaine courante; récurrence : tous les vendredis
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday) - 3 * 24 * 60 * 60,
		    		'dtend' 		=> strtotime($currentMonday) - 4 * 24 * 60 * 60,
		    		'summary' 		=> 'Event 9',//$summarys[rand(0, $countSummarys)],
		    		'allday'		=> true,
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
		    		'rrule'			=> array(
							    		'FREQ'	=> 'WEEKLY',
							    		'COUNT' => 2,
		    		),
	    		));
	    		
	    		// FIXME: écrire la règle de récurrence différement ?
	    		$this->writeSection($output, 'Event 10 - with recurrence'.'-'.$i);
	    		// Evénement : commence dans 2 mois par rapport à la semaine courante et à une occurrence au mois pendant 1 an
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+2 months 9 hours 30 minutes'),
		    		'dtend' 		=> strtotime($currentMonday.'+2 months 1 day 12 hours 30 minutes'),
		    		'summary' 		=> 'Event 10',//$summarys[rand(0, $countSummarys)],
 		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
 		    		'organizer'		=> $authors[rand(0, $countAuthors)],
 		    		'location'		=> $locations[rand(0, $countLocations)],
		    		'rrule'			=> array(
							    		'FREQ'	=> 'MONTHLY',
							    		'COUNT' => 12,
	    			),
	    		));
	    		
	    		$this->writeSection($output, 'Event 11'.'-'.$i);
	    		// Evénement : dure toute la journée du samedi de la semaine courante
	    		$calendarManager->createEvent($agendasId[$i], array(
		    		'dtstart' 		=> strtotime($currentMonday.'+5 days 00:00:00'),
		    		'dtend' 		=> strtotime($currentMonday.'+5 days 00:00:00'),
		    		'summary' 		=> 'Event 11',//$summarys[rand(0, $countSummarys)],
		    		'description' 	=> $descriptions[rand(0, $countDescriptions)],
		    		'organizer'		=> $authors[rand(0, $countAuthors)],
		    		'location'		=> $locations[rand(0, $countLocations)],
		    		'allday'		=> true,
	    		));*/
    		}
    		
    		$this->con->commit();
    	}
    	catch (Exception $e)
    	{
    		$this->con->rollBack();
    		throw $e;
    	}
    }
	
	
	protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }
}
<?php

namespace BNS\App\NotificationBundle\Command;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarHappyBirthdayNotification;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarNewBirthdayNotification;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ClearCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('notification:clear')
            ->setDescription('Launch notifications clear process')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('This task will delete old notifications from the database. 1 month for read notifications, and 2 months for unread.')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$con = \Propel::getConnection($connectionName);
		\Propel::setForceMasterConnection(true);
		
		try
		{
			$con->beginTransaction();
			
			$this->clearProcess($input, $output);
			
			$con->commit();
		}
		catch (\Exception $e)
		{
			$con->rollBack();
			
            throw $e;
		}
	}
	
	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	private function clearProcess($input, $output)
	{
		$this->writeSection($output, 'Launch notifications clear process...');
		
		$now = new ExtendedDateTime();
		$unReadCondition = clone $now;
		$unReadCondition->modify($this->getContainerParameter('notification.clear_command.unread_condition', '-2 month'));
		
		$readCondition = clone $now;
		$readCondition->modify($this->getContainerParameter('notification.clear_command.read_condition', '-1 month'));
		
		// Retreive notification count
		$unReadCount = NotificationQuery::create('n')
			->where('n.IsNew = ?', true)
			->where('n.Date < ?', $unReadCondition)
		->count();
		
		$readCount = NotificationQuery::create('n')
			->where('n.IsNew = ?', false)
			->where('n.Date < ?', $readCondition)
		->count();
		
		$this->writeSection($output, 'Deleting ' . $unReadCount . ' unread and ' . $readCount . ' read notifications.');
		
		// Deleting process
		// Unread
		NotificationQuery::create('n')
			->where('n.IsNew = ?', true)
			->where('n.Date < ?', $unReadCondition)
		->delete();
		
		// Read
		NotificationQuery::create('n')
			->where('n.IsNew = ?', false)
			->where('n.Date < ?', $readCondition)
		->delete();
		
		$this->writeSection($output, 'End of notifications clear process.');
	}
	
	/**
	 * @param string $name
	 * @param mixed  $default
	 * 
	 * @return mixed 
	 */
	private function getContainerParameter($name, $default)
	{
		if ($this->getContainer()->hasParameter($name)) {
			return $this->getContainer()->getParameter($name);
		}
		
		return $default;
	}
}
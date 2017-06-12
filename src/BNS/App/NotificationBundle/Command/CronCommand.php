<?php

namespace BNS\App\NotificationBundle\Command;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarNewBirthdayNotification;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarHappyBirthdayNotification;

use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CronCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('notification:cron')
            ->setDescription('Launch cron notifications')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Calendar: birthdays; ')
        ;
    }
	
	/**
	 * @param InputInterface  $input
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
			
			$this->launchCalendarBirthayCron($input, $output);
			
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
	private function launchCalendarBirthayCron($input, $output)
	{
		$this->writeSection($output, 'Launch calendar birthday cron...');
		
		$userManager = $this->getContainer()->get('bns.user_manager');
		$classRoomManager = $this->getContainer()->get('bns.classroom_manager');
		$users = UserQuery::create('u')
			->where('u.Birthday = ?', date('Y-m-d', time()))
		->find();
		
		$this->writeSection($output, count($users) . ' birthdays user found, processing...');
		
		// Send notification for classroom, exclude birtday user
		foreach ($users as $user) {
			$classRooms = $userManager->setUser($user)->getClassroomUserBelong();
			foreach ($classRooms as $classRoom) {
				$this->getContainer()->get('notification_manager')->send(
					$classRoomManager->setGroup($classRoom)->getUsers(true),
					new CalendarNewBirthdayNotification($this->getContainer(), $user->getId(), $classRoom->getId()),
					array($user)
				);
			}

			// Send notification for birthday user
			$this->getContainer()->get('notification_manager')->send($user, new CalendarHappyBirthdayNotification($this->getContainer()));
		}
		
		
		$this->writeSection($output, 'End of calendar birthday cron.');
	}
}
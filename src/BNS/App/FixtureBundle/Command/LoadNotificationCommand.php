<?php
namespace BNS\App\FixtureBundle\Command;

use BNS\App\NotificationBundle\Model\NotificationType;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Propel;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadNotificationCommand extends AbstractCommand
{
	/**
	 * @var PropelPDO MySQL connexion
	 */
	private $con;
	
	protected function configure()
    {
        $this
            ->setName('bns:load-notifications')
            ->setDescription('Load all notification type')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Creer les types de notification pour chacun des modules')
        ;
    }
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$this->con = Propel::getConnection($connectionName);
		Propel::setForceMasterConnection(true);
		
		try
		{
			$this->con->beginTransaction();
			
			$notificationsData = Yaml::parse(__DIR__ . '/../Resources/data/Notification/notification_type.yml');
			foreach ($notificationsData as $moduleName => $moduleData) {
				foreach ($moduleData as $uniqueName => $data) {
					$notificationType = new NotificationType();
					$notificationType->setModuleUniqueName($moduleName);
					$notificationType->setUniqueName($uniqueName);
					
					if (null != $data['disabled_engine']) {
						$notificationType->setDisabledEngine($data['disabled_engine']);
					}
					
					if (isset($data['is_correction'])) {
						$notificationType->setIsCorrection($data['is_correction']);
					}
					
					$notificationType->save();
					
					$notificationType = null;
				}
			}
			
			$this->con->commit();
		}
		catch (Exception $e)
		{
			$this->con->rollBack();
            throw $e;
		}
	}
}
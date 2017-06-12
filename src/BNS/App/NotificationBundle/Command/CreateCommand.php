<?php

namespace BNS\App\NotificationBundle\Command;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Yaml\Yaml;
use Exception;
use Propel;

use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\NotificationBundle\Model\NotificationType;
use BNS\App\NotificationBundle\Generator\NotificationClassGenerator;
use BNS\App\NotificationBundle\Generator\TranslationGenerator;
use BNS\App\NotificationBundle\Generator\MigrationGenerator;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CreateCommand extends AbstractCommand
{
	private $bundleName;
	private $notificationName;
	
	protected function configure()
    {
        $this
            ->setName('notification:create')
            ->setDescription('Create a notification class')
			->addArgument('bundle_name', InputArgument::REQUIRED, 'The bundle name for the notification. i.e: Demo or DemoBundle')
			->addArgument('notification_name', InputArgument::REQUIRED, 'The notification name. The name must be in pascal template, ex: NewHelloWorld')
			->addOption('disabled_engine', null, InputOption::VALUE_OPTIONAL, 'If you want to disable an engine, put the engine name here, ex: SYSTEM. IMPORTANT: if you want to put more than one engine, the names must be separated with a coma without space, ex: SYSTEM,EMAIL. Put "null" to enable all engines.')
			->addOption('is_correction', null, InputOption::VALUE_OPTIONAL, 'Mark this notification as a correction notification. Users will able to see the notification in the corrections panel.', false)
			->addArgument('attributes', InputArgument::IS_ARRAY, 'The notification attributes, required for the translation. Default attributes are : target_user_id and group_id', array())
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Cette commande genere une classe pour un type de notification ainsi que les fichiers de traduction correspondant.')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$this->bundleName = ucfirst($input->getArgument('bundle_name'));
		if (substr($this->bundleName, -6) == 'Bundle') {
			$this->bundleName = substr($this->bundleName, 0, -6);
		}
		
		$this->notificationName = $this->bundleName . $input->getArgument('notification_name');
		
		// Processes
		$this->createClassFile($input, $output);
		$this->createTranslationFiles($input, $output);
		$this->insertNotificationType($input, $output);
		$this->writeFixtures($input, $output);
		
		$this->writeSection($output, 'Notification created successfully !');
	}
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function createClassFile(InputInterface $input, OutputInterface $output)
	{
		$this->writeSection($output, 'Creating notification class...');
		
		$classGenerator = new NotificationClassGenerator($this->getContainer()->get('filesystem'), __DIR__.'/../Resources/skeleton/class');
		$classGenerator->generate(
			$this->bundleName,
			$this->notificationName,
			$input->getArgument('attributes')
		);
	}
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function createTranslationFiles(InputInterface $input, OutputInterface $output)
	{
		$this->writeSection($output, 'Creating notification i18n...');
		
		$classGenerator = new TranslationGenerator($this->getContainer()->get('filesystem'), __DIR__.'/../Resources/skeleton/translations');
		$classGenerator->generate(
			$this->bundleName,
			$this->notificationName,
			$this->getContainer()->getParameter('available_languages'),
			$input->getArgument('attributes')
		);
	}
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * 
	 * @throws Exception 
	 */
	private function insertNotificationType(InputInterface $input, OutputInterface $output)
	{
		$this->writeSection($output, 'Insert new notification type in the database...');
		
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$con = Propel::getConnection($connectionName);
		Propel::setForceMasterConnection(true);
		
		try
		{
			$con->beginTransaction();
			
			$moduleUniqueName = strtoupper($input->getArgument('bundle_name'));
			if (substr($moduleUniqueName, -6) == 'BUNDLE') {
				$moduleUniqueName = substr($moduleUniqueName, 0, -6);
			}
			$disabledEngine = $input->getOption('disabled_engine') == 'null' ? null : $input->getOption('disabled_engine');
			$isCorrection = $input->getOption('is_correction') === false ? false : true;
			
			// Vérification de l'existence du module par rapport à l'unique name
			$module = ModuleQuery::create()
				->add(ModulePeer::UNIQUE_NAME, $moduleUniqueName)
			->findOne();
			
			if (null == $module) {
				throw new Exception('Unable to find the module with the unique name : ' . $moduleUniqueName . ' ! Please check the database.');
			}
			
			$notificationType = new NotificationType();
			$notificationType->setModuleUniqueName($module->getUniqueName());
			$notificationType->setUniqueName(strtoupper(Container::underscore($this->notificationName)));
			$notificationType->setDisabledEngine($disabledEngine);
			$notificationType->setIsCorrection($isCorrection);
			$notificationType->save();
			
			$con->commit();
		}
		catch (Exception $e)
		{
			$con->rollBack();
            throw $e;
		}
	}
	
	/**
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 */
	private function writeFixtures(InputInterface $input, OutputInterface $output)
	{
		$this->writeSection($output, 'Dump fixtures & SQL production file...');
		
		$installDataFile = __DIR__ . '/../../' . $this->bundleName . 'Bundle/Resources/install/install_data.yml';
        if (!is_file($installDataFile)) {
			throw new \RuntimeException('The install_data.yml file file is NOT found for bundle ' . $this->bundleName . 'Bundle !');
		}
        
		$notificationUniqueName = strtoupper(Container::underscore($this->notificationName));
		$notificationsData = Yaml::parse($installDataFile);
		$bundleName = strtoupper($this->bundleName);

        if (!isset($notificationsData['notification_types'])) {
            $notificationsData['notification_types'] = array();
        }

        if (null == $input->getOption('disabled_engine')) {
            $notificationsData['notification_types'][$notificationUniqueName] = array();
        }
        else {
            $notificationsData['notification_types'][$notificationUniqueName] = array(
                'disabled_engine' => $input->getOption('disabled_engine') == 'null' ? null : $input->getOption('disabled_engine')
            );
        }
		
		$isCorrection = $input->getOption('is_correction') === false ? false : true;
		if ($isCorrection) {
			$notificationsData['notification_types'][$notificationUniqueName]['is_correction'] = true;
		}
		
		file_put_contents($installDataFile, Yaml::dump($notificationsData, 4, 2));
		
		// Migration file
		$migrationGenerator = new MigrationGenerator($this->getContainer()->get('filesystem'), __DIR__.'/../Resources/skeleton/migration');
		$migrationGenerator->generate(
			$this->getContainer()->getParameter('kernel.root_dir'),
			$bundleName,
			$notificationUniqueName,
			$isCorrection,
			$input->getOption('disabled_engine')
		);
	}
}
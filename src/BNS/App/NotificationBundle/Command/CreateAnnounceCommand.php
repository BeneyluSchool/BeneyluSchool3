<?php

namespace BNS\App\NotificationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Propel\PropelBundle\Command\AbstractCommand;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CreateAnnounceCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('notification:create-announce')
            ->setDescription('Create a notification announce class')
			->addArgument('notification_name', InputArgument::REQUIRED, 'The notification name. The name must be in upper camal case, ex: NewHelloWorld')
			->addOption('disabled_engine', null, InputOption::VALUE_OPTIONAL, 'If you want to disable an engine, put the engine name here, ex: SYSTEM. IMPORTANT: if you want to put more than one engine, the names must be separated with a coma without space, ex: SYSTEM,EMAIL. Put "null" to enable all engines.')
			->addArgument('attributes', InputArgument::IS_ARRAY, 'The notification attributes, required for the translation. Default attributes are : target_user_id and group_id', array())
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Cette commande genere une classe pour une notification de type annonce ainsi que les fichiers de traduction correspondant.')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$command = 'php app/console notification:create Notification ' . $input->getArgument('notification_name') . ' ' . $input->getArgument('attributes');
		if (null != $input->getOption('disabled_engine')) {
			$command .= ' --disabled_engine=' . $input->getOption('disabled_engine');
		}
		
		exec($command, $out, $returnStatus);

		$output->writeln($out);
		
		if ($returnStatus > 0) {
			$this->writeSection($output,'/!\ ERROR while creating notification announce');
        }
	}
}
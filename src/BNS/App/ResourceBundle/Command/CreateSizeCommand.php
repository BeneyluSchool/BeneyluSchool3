<?php

namespace BNS\App\ResourceBundle\Command;

use \BNS\App\ResourceBundle\Model\ResourceQuery;
use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CreateSizeCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('resource:create-size')
            ->setDescription('Create size for document with empty size')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Create size for document with empty size.')
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
			
			$this->createSizeProcess($input, $output);
			
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
	private function createSizeProcess($input, $output)
	{
		$this->writeSection($output, 'Create size process...');
		
		$resources = ResourceQuery::create('r')
			->where('r.Size IS NULL')
			->where('r.TypeUniqueName IN ?', array('IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'FILE'))
		->find();

		$this->writeSection($output, count($resources) . ' documents with empty size found');

		$resourceManager = $this->getContainer()->get('bns.resource_manager');
		$errorFiles = array();

		foreach ($resources as $resource) {
			try {
				$resource->setSize($resourceManager->getSize($resource));
			}
			catch (\Exception $e) {
				$errorFiles[] = array(
					'resource' => $resource,
					'message'  => $e->getMessage()
				);
			}
		}

		$resources->save();

		if (isset($errorFiles[0])) {
			$this->writeSection($output, 'There is errors during the process :');
			foreach ($errorFiles as $error) {
				$output->writeln('ID #' . $error['resource']->getId() . ': "' . $error['resource']->getLabel() . '", ' . $error['message']);
			}
		}
		
		$this->writeSection($output, 'End creating size process.');
	}
}
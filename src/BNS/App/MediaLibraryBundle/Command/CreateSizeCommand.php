<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;


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
		
		$medias = MediaQuery::create('m')
			->where('m.Size IS NULL')
			->where('m.TypeUniqueName IN ?', array('IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'FILE'))
		->find();

		$this->writeSection($output, count($medias) . ' documents with empty size found');

		$mediaManager = $this->getContainer()->get('bns.media_manager');
		$errorFiles = array();

		foreach ($medias as $media) {
			try {
                $media->setSize($mediaManager->getSize($media));
			}
			catch (\Exception $e) {
				$errorFiles[] = array(
					'media' => $media,
					'message'  => $e->getMessage()
				);
			}
		}

        $medias->save();

		if (isset($errorFiles[0])) {
			$this->writeSection($output, 'There is errors during the process :');
			foreach ($errorFiles as $error) {
				$output->writeln('ID #' . $error['media']->getId() . ': "' . $error['media']->getLabel() . '", ' . $error['message']);
			}
		}
		
		$this->writeSection($output, 'End creating size process.');
	}
}
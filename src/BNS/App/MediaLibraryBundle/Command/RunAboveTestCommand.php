<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaLinkGroup;
use BNS\App\MediaLibraryBundle\Model\MediaLinkGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaLinkUser;
use BNS\App\MediaLibraryBundle\Model\MediaLinkUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Gaufrette\Filesystem;

class RunAboveTestCommand extends ContainerAwareCommand
{

	protected function configure()
    {
        $this
            ->setName('media-library:runabove-test')
            ->setDescription('Test Runabove')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset query',0)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit query',1000)
        ;
    }

	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit','-1');
        $container = $this->getContainer();
        
        $buzz = $container->get('buzz');
        $i = 0;
		$m=0;
        while($i < 100000)
        {
			$medias = MediaQuery::create()->filterById(3081201,\Criteria::GREATER_THAN)->find();
            $j = 0;
            $timeCount = 0;
			
            foreach($medias as $media)
            {
				
                $download = $container->get('bns.media.download_manager');
                $time = microtime(true) * 1000;
                $response = $buzz->head($download->getDownloadUrl($media));
				$m++;
                $code = $response->getStatusCode();
                //$output->writeln(microtime(true) * 1000 - $time);
                if($code != "200")
                {
                    $output->writeln('ERROR ' . $media->getId());
                }else{
                    $timeCount += microtime(true) * 1000 - $time;
                    $j++;
                }
				
				$time = microtime(true) * 1000;
                $response = $buzz->get($download->getDownloadUrl($media));
                $code = $response->getStatusCode();
				if($code != "200")
                {
                    $output->writeln('ERROR DL' . $media->getId());
                }
				$m++;
            }
            $output->writeln('Moyenne : ' . $timeCount / $j . ' - ' . $m);
            $i++;
        }



	}
}
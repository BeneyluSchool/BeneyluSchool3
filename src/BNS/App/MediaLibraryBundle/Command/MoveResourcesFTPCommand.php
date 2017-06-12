<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use \BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Gaufrette\Adapter\Ftp;
use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Gaufrette\Filesystem;

class MoveResourcesFTPCommand extends ContainerAwareCommand
{

    protected $output;
    protected $verbose;

	protected function configure()
    {
        $this
            ->setName('resource:move-resources-ftp')
            ->setDescription('Move From FTP to chosen place')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset query')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit query')
            ->addOption('pass', null, InputOption::VALUE_OPTIONAL, 'FTP password')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->output = $output;
        $this->verbose = $input->getOption('verbose');
        $pass = $input->getOption('pass');
        $useOvh = false;
        $container = $this->getContainer();

        $ftpAdapter = new Ftp('/','185.15.140.238', array('username' => 'entdata','password' =>  $pass));
        $localAdapter = $container->get("bns.local.adapter");
        $s3Fs = new Filesystem($ftpAdapter);
        $localFs = new Filesystem($localAdapter);


        if($useOvh)
        {
            $ovhAdapter = $container->get("bns.ovh_pcs.adapter");
            $ovhFs = new Filesystem($ovhAdapter);
        }

        $resourceOffset = 3000000;

        //Récupération des ressources
        $resourcesQuery = MediaQuery::create()
            ->filterByStatusDeletion('-1',\Criteria::NOT_EQUAL)
            ->filterById($resourceOffset,\Criteria::GREATER_EQUAL)
            ->orderById();
        if($input->hasOption('offset'))
        {
            $resourcesQuery->offset($input->getOption('offset'));
        }
        if($input->hasOption('limit'))
        {
            $resourcesQuery->limit($input->getOption('limit'));
        }
        $resources = $resourcesQuery->find();

        $this->write('BENEYLU SCHOOL - Deplacement des ressources');
        $this->write('Nombre des ressources concernees : '.$resources->count());

        $error = 0;



        foreach($resources as $resource)
        {
            $newPath = $resource->getFilePath();
            $resource->setId($resource->getId() - $resourceOffset);
            $isError = false;
            $path = $resource->getFilePath();
            $oldPath = '/data/data/resources/' . $path;

            echo $newPath . ' - ';

            if(!$localFs->has($newPath))
            {
                if($s3Fs->has($oldPath))
                {
                    $this->write('Début Lecture - ' . $resource->getSize() . ' - ' . $oldPath);
                    try{
                        $file = $s3Fs->read($oldPath);
                    }catch (\Exception $e){
                        $this->write('Erreur lecture Amazon => ' . $oldPath . ' - ' . $e->getMessage());
                        $isError = true;
                        $error++;
                    }
                    if(!$isError)
                    {
                        try{
                            if(!$localFs->has($newPath))
                            {
                                $localFs->write($newPath,$file);
                            }
                        }catch (\Exception $e){
                            $this->write('ERREUR ecriture / ecriture locale => ' . $newPath . ' - ' . $e->getMessage());
                            $isError = true;
                            $error++;
                        }
                        if(!$isError)
                        {
                            $this->write('Local Ok');
                            if($useOvh && !$ovhFs->has($path))
                            {
                                try{
                                    $ovhFs->write($path,$file);
                                }catch (\Exception $e){
                                    $this->write('ERREUR ecriture OVH  => ' . $path . ' - '  . $e->getMessage());
                                    $error++;
                                }
                                $this->write('OVH Ok');
                            }
                            $this->write('Fichier ' . $resource->getLabel() . ' Termine - ' . $path);
                        }
                    }
                    $this->write('Lecture Ok ');

                }else{
                    $this->write("Fichier inexistant sur S3");
                    $this->write("Suppression " . $resource->getId());
                    $resource->delete();

                }
            }else{
                $this->write('Fichier ' . $resource->getLabel() . ' deja traite');
            }
            unset($file,$path,$resource);
        }
        $this->write('NB erreurs : ' . $error);
	}

    public function write($text)
    {
        if($this->verbose)
        {
            $this->output->writeln($text);
        }
    }
}
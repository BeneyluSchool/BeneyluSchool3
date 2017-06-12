<?php

namespace BNS\App\MediaLibraryBundle\Command;

use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Gaufrette\Filesystem;

class MoveResourcesCommand extends ContainerAwareCommand
{

    // DEPRECATED !!!!!! conservé uniquement au cas où


    protected $output;
    protected $verbose;

	protected function configure()
    {
        $this
            ->setName('resource:move-resources')
            ->setDescription('Move From S3 to chosen place')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset query')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit query')
            ->addOption('use-ovh',false, InputOption::VALUE_OPTIONAL, 'Use OVH adapter')
        ;
    }

	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    /*
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->output = $output;
        $this->verbose = $input->getOption('verbose');
        $useOvh = $input->getOption('use-ovh');
        $container = $this->getContainer();
        /* @var $rm BNSResourceManager
        $rm = $container->get('bns.resource_manager');

        $s3Adapter = $container->get("bns.s3.adapter");
        $localAdapter = $container->get("bns.local.adapter");
        $s3Fs = new Filesystem($s3Adapter);
        $localFs = new Filesystem($localAdapter);

        if($useOvh)
        {
            $ovhAdapter = $container->get("bns.ovh_pcs.adapter");
            $ovhFs = new Filesystem($ovhAdapter);
        }

        //Récupération des ressources
        $resourcesQuery = ResourceQuery::create()
            ->filterByStatusDeletion('-1',\Criteria::NOT_EQUAL)
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
            $isError = false;
            $rm->setObject($resource);
            $path = $resource->getFilePath();
            if(!$localFs->has($path))
            {
                if($s3Fs->has($path))
                {
                    $this->write('Début Lecture - ' . $resource->getSize() . ' - ' . $path);
                    try{
                        $file = $s3Fs->read($path);
                    }catch (\Exception $e){
                        $this->write('Erreur lecture Amazon => ' . $path . ' - ' . $e->getMessage());
                        $isError = true;
                        $error++;
                    }
                    if(!$isError)
                    {
                        try{
                            if(!$localFs->has($path))
                            {
                                $localFs->write($path,$file);
                            }
                        }catch (\Exception $e){
                            $this->write('ERREUR ecriture / ecriture locale => ' . $path . ' - ' . $e->getMessage());
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
    }*/
}
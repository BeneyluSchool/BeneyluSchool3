<?php

namespace BNS\App\MediaLibraryBundle\Command;

use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetRemoteSecretCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('media-library:set-secret')
            ->setDescription('Set secret for remote temporary url')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $objectStoreService =  $container->get('bns.runabove.object_store_factory')->getObjectStore();
        $objectStoreService->getAccount()->setTempUrlSecret($container->getParameter('symfony_secret'));

        $output->write('Secret saved successfully');
    }

}

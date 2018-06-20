<?php

namespace BNS\App\MediaLibraryBundle\Command;

use OpenCloud\ObjectStore\Service;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetRemoteSecretCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('media-library:set-secret')
            ->setDescription('Set secret for remote temporary url')
            ->addOption('secret', null, InputOption::VALUE_OPTIONAL, 'Set a secret for temporary url', null)
            ->addOption('secondary', null, InputOption::VALUE_NONE, 'Use the secondary temp url Temp-Url-Key-2', null)
            ->addOption('store', null, InputOption::VALUE_OPTIONAL, 'Store type OVH/runabove', 'ovh')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        if (!$secret = $input->getOption('secret')) {
            $secret = $container->getParameter('symfony_secret');
        }

        $store = 'bns.ovh_public_cloud.object_store_factory';
        if ('runabove' === strtolower($input->getOption('store'))) {
            $store = 'bns.runabove.object_store_factory';
        }

        /** @var Service $objectStoreService */
        $objectStoreService = $container->get($store)->getObjectStore();
        $account = $objectStoreService->getAccount();

        if ($input->getOption('secondary')) {
            // fix method
            $account->saveMetadata($account->appendToMetadata(array('Temp-Url-Key-2' => $secret)));

            $output->writeln('Secret saved successfully to secondary url');
        } else {
            $account->setTempUrlSecret($secret);

            $output->writeln('Secret saved successfully');
        }
    }
}

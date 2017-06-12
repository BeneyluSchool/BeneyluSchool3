<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Adapter\LazyOpenCloud;
use Gaufrette\Adapter;
use Predis\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SwiftTransferCommand extends ContainerAwareCommand
{

    /** @var  OutputInterface */
    protected $output;

    protected $verbose;

    /** @var  Client */
    protected $redis;

    /** @var  LazyOpenCloud */
    protected $sourceAdapter;

    /** @var  LazyOpenCloud */
    protected $destinationAdapter;

    protected function configure()
    {
        $this
            ->setName('media-library:swift:transfer')
            ->setDescription('Copy swift container to another one')
            ->addOption('marker', null, InputOption::VALUE_OPTIONAL, 'The marker to use as a start point (filename)', '')
            ->addOption('batch-limit', null, InputOption::VALUE_OPTIONAL, 'The number of batch to run (no value = all)', null)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'The number of item per batch', 10000)
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        // init services
        $this->redis = $container->get('snc_redis.transfer');
        $this->output = $output;

        // init container info
        $this->sourceAdapter = $container->get('bns.runabove.adapter');
        $this->destinationAdapter = $container->get('bns.ovh_public_cloud.adapter');
        $sourceContainer = $this->sourceAdapter->getContainer();

        $output->writeln('BENEYLU SCHOOL - Media Transfer');

        $marker = $input->getOption('marker');
        $limit = (int)$input->getOption('limit');
        $batchLimit = $input->getOption('batch-limit');
        $batchNumber = 0;

        while (null !== $marker) {
            $params = [
                'marker' => $marker,
                'limit'  => $limit
            ];

            $batchNumber++;
            $output->writeln(sprintf('Start batch <info>%s</info> with %s items with marker : "%s"', $batchNumber, $limit, $marker));

            $objects = $sourceContainer->objectList($params);
            $total = $objects->count();
            $count = 0;

            if ($total == 0) {
                break;
            }

            foreach ($objects as $object) {
                $this->transferKey($object->getName());
                $count++;

                $marker = ($count == $total) ? $object->getName() : null;
            }

            $output->writeln('Batch done');
            if (null !== $batchLimit && $batchLimit <= $batchNumber) {
                $marker = null;
            }
        }

        $output->writeln('Transfer finished');
    }

    protected function transferKey($key)
    {
        $this->output->write(sprintf('migrate %s : ', $key));
        $done = $this->redis->get('sw:' . $key);
        if ('OK' !== $done) {
            $this->redis->set('sw:' . $key, 0);
        } else {
            $this->output->write(sprintf('<comment>skip</comment> key already exist'));
            $this->output->writeln('');
            return ;
        }

        try {
            $checksumDest = $this->destinationAdapter->checksum($key);
            $checksumSource = $this->sourceAdapter->checksum($key);
            if ($checksumDest && $checksumSource && $checksumSource === $checksumDest) {
                $this->redis->set('sw:' . $key, 'OK');
                $this->output->write(sprintf('<comment>skip</comment> key already exist'));
                $this->output->writeln('');
                return;
            }
            $this->destinationAdapter->write(rawurlencode($key), $this->sourceAdapter->read($key));

            $this->output->write('<info>OK</info>');
        } catch (\Exception $e) {
            $this->output->write(sprintf('<error>error : %s ---  %s --- %s</error>', $e->getMessage(), $e->getRequest(), $e->getResponse()->getBody()));
        }
        $this->output->writeln('');
    }
}

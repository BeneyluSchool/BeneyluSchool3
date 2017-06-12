<?php
namespace BNS\App\MediaLibraryBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SwiftBatchRunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('media-library:swift:batch-transfer')
            ->setDescription('execute batch of transfert for swift')
            ->addOption('marker', null, InputOption::VALUE_OPTIONAL, 'The marker to use as a start point (filename)', '')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'The number of item per batch', 1000)
            ->addOption('nb-process', null, InputOption::VALUE_OPTIONAL, 'The number of processus to lunch', 20)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $logger = $container->get('logger');

        $marker = $input->getOption('marker');
        $limit = (int)$input->getOption('limit');

        $baseCmd = 'php app/console media-library:swift:transfer --batch-limit 1 --limit=' . escapeshellarg($limit) .  ' --env=' . $input->getOption('env') . ' --marker=';

        $nbProcessus = (int)$input->getOption('nb-process');
        $processes = array();
        for ($i = 0; $i < $nbProcessus; $i++) {
            $processes[] = array(
                'cmd' => $baseCmd . escapeshellarg($marker),
                'process' => new Process(''),
                'finished' => false
            );
        }

        while (true) {
            $nbProcess = count($processes);
            if (0 >= $nbProcess) {
                break;
            }

            for ($i = 0; $i < $nbProcess; $i++) {
                /** @var Process $process */
                $process = $processes[$i]['process'];
                if (!$process->isRunning()) {
                    $output->writeln($marker);
                    if (null === $marker || (null === $marker = $this->getNextMarker($marker, $limit))) {
                        $processes[$i]['finished'] = true;
                        continue;
                    }
                    $finished = false;
                    $cmd = $baseCmd . escapeshellarg($marker);
                    $process->setCommandLine($cmd);
                    $process->setTimeout(null);
                    $output->writeln('Start process ' . $process->getCommandLine());
                    $process->start(function ($type, $data) use ($logger) {
                        if ('err' == $type) {
                            // log Erreur
                            $logger->error('Swift batch erreur : ' . $data);
                        } else {
                            $logger->info('Swift batch  debug : ' . $data);
                        }
                    });
                }
            }
            sleep(1);
            foreach ($processes as $process) {
                if (!$process['finished']) {
                    continue 2;
                }
            }
            break;
        }
    }

    public function getNextMarker($marker, $limit)
    {
        $this->sourceAdapter = $this->getContainer()->get('bns.runabove.adapter');
        $sourceContainer = $this->sourceAdapter->getContainer();

        $objects = $sourceContainer->objectList([
            'marker' => $marker,
            'limit' => $limit,
        ]);

        $total = $objects->count();
        $last = $objects->offsetGet($total -1);

        if (isset($last->name)) {
            return $last->name;
        }

        return null;
    }
}

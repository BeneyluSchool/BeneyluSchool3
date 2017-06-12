<?php
namespace BNS\App\CommandBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class RunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('bns:run-cron')
            ->setDescription('Execute all cron jobs')
            ->setHelp('Execute all cron jobs')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $debug = $container->get('kernel')->isDebug();
        $crons = $container->getParameter('rmq.consumers');
        $timeout = $this->getContainer()->getParameter('cron.timeout');
        $logger = $container->get('logger');
        $processes = array();
        foreach ($crons as $cron) {
            $processes[] = array(
                'cmd' => 'php app/console rabbitmq:consumer -m 100 ' . $cron . ' --env=' . $input->getOption('env') . ($debug ? ' --debug' : ' --no-debug'),
                'process' => new Process('')
                );
        }
        $count = count($processes);
        while ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                /** @var Process $process */
                $process = $processes[$i]['process'];
                $cmd = $processes[$i]['cmd'];
                if (!$process->isRunning()) {
                    $process->setCommandLine($cmd);
                    $process->setTimeout(null);
                    $output->writeln('Start process ' . $process->getCommandLine());
                    $process->start(function ($type, $data) use ($logger) {
                        if ('err' == $type) {
                            // log Erreur
                            $logger->error('bns:run-cron command  erreur : ' . $data);
                        } else {
                            $logger->debug('bns:run-cron command  debug : ' . $data);
                        }
                    });
                }
            }
            // 0.1 second
            usleep(100000);
        }

        return 0;
    }
}

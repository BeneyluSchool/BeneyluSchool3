<?php
namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Process\Process;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MigrationAutoCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('bns:migration-auto')
            ->setDescription('Execute toutes les migrations de la V2 à la V3')
            ->addArgument('step', InputArgument::OPTIONAL, 'reprend la migration a cette étape')
            ->addOption('bnsEnv', null, InputOption::VALUE_OPTIONAL, "nom de l'environnement (www.beneyluschool.net)")
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandList = array(
                'schools',
                'classrooms',
                'teachers',
                'pupils',
                'parents',
                'blogCategories',
                'blogPosts',
                'blogComments',
                'folders',
                'files',
                'avatars',
                'directors'
                );

        $step = $input->getArgument('step');
        if (null !== $step) {
            if (false !== ($key = array_search($step, $commandList, true))) {
                $commandList = array_slice($commandList, $key);
            } else {
                $output->writeln('<error>Invalid argument ' . $step . ' is not a valid step</error>');
                exit(1);
            }
        }

        $output->writeln('Started at ' . date('Y-m-d H:i:s'));

        foreach ($commandList as $commandName) {
            $output->writeln('Start migration of <info>' . $commandName .'</info>');
            $output->write('...');

//             $command = $this->getApplication()->find('bns:migration');

//             $arguments = array(
//                     'bns:migration',
//                     'step' => $commandName,
//                     '--verbose' => true,
//                     '--process-isolation' => true,
//                     '--env' => $input->getOption('env'),
//                     '--bnsEnv' => $input->getOption('bnsEnv')
//                     );

            $logFile = $this->getContainer()->get('kernel')->getRootDir() . '/logs/migration_' . $commandName . '_' . date('Y_m_d') . '.log';

//             $returnCode = $command->run(new ArrayInput($arguments), new StreamOutput(fopen($logFile, 'a+')));

            $process = new Process('php app/console bns:migration ' . $commandName . ' --verbose --env=' . $input->getOption('env') . ' --bnsEnv=' . $input->getOption('bnsEnv'));
            $process->setTimeout(36000);
            $process->run();

            file_put_contents($logFile, $process->getOutput(), FILE_APPEND);

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            $output->writeln(' done. ' . date('Y-m-d H:i:s'));
        }
        $output->writeln('Stoped at ' . date('Y-m-d H:i:s'));
    }

}

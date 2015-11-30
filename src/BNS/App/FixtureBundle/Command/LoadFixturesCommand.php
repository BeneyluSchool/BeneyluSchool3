<?php

namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create and load fixtures for BNS
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadFixturesCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('bns:load-fixtures')
                ->setDescription('Load Fixtures datas ')
                ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
                ->setHelp('Note: les données des fixtures générées se trouvent dans BNS/App/FixtureBundle/Resources/data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $given_env = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ? : 'app_dev');
        $env = explode("_", $given_env);
        $auth_env = 'auth_' . $env[1];
        $app_env = 'app_' . $env[1];

        $output->writeln("Creation des marqueurs de statistique");
        $this->execCommand("php app/console bns:load-statistic", $app_env, $output);
        $output->writeln("Creation des classrooms");
        $this->execCommand("php app/console bns:load-classrooms", $app_env, $output);
        $output->writeln("Creation des blogs");
        $this->execCommand("php app/console bns:load-blogs", $app_env, $output);
        $output->writeln("Creation des minisites");
        $this->execCommand("php app/console bns:load-minisite", $app_env, $output);
        $output->writeln("Creation des notifications");
        $this->execCommand("php app/console bns:load-notifications", $app_env, $output);
        $output->writeln("Creation des calendriers");
        $this->execCommand("php app/console bns:load-calendar", $app_env, $output);
        $output->writeln("Creation des carnets de liaison");
        $this->execCommand("php app/console bns:load-liaisonbook", $app_env, $output);
        $output->writeln("Creation des lieux du GPS : to uncomment pb proxy ?");
        $this->execCommand("php app/console bns:load-gps", $app_env, $output);
        $output->writeln("Creation de cahiers de texte");
        $this->execCommand("php app/console bns:load-homework", $app_env, $output);
    }

    protected function execCommand($command, $env, $output) {

        exec($command . " --env=" . $env, $out, $return_status);

        if ($return_status > 0) {
            $output->writeln("-- ERROR while executing command: " . $command . " --env=" . $env);
            foreach ($out as $line) {
                $output->writeln($line);
            }
        }
    }

}
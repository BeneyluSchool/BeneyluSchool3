<?php

namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Init a BNS 3 project
 *
 * @author Eymeric Taelman
 */
class ProjectInitCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
                ->setName('bns:project-init')
                ->setDescription('Init a project')
                ->addOption('no-build', true, InputOption::VALUE_OPTIONAL, 'Sans Build ?');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output 
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $given_env = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ? : 'app_dev');
        $env = explode("_", $given_env);
        $auth_env = 'auth_' . $env[1];
        $app_env = 'app_' . $env[1];
        
        $this->writeSection($output, 'Drop des bases de donnees');

        
        if ($env == "dev") {
            $this->writeSection($output, 'App');
            $this->execCommand("php app/console propel:database:drop --force", $app_env, $output);
            $this->writeSection($output, 'Auth');
            $this->execCommand("php app/console_auth propel:database:drop --force", $auth_env, $output);
        }

        $this->writeSection($output, 'Recuperation des bases de donnees');
        $this->writeSection($output, 'App');
        $this->execCommand("php app/console propel:database:create", $app_env, $output);
        $this->writeSection($output, 'Auth');
        $this->execCommand("php app/console_auth propel:database:create", $auth_env, $output);

        $this->writeSection($output, 'Rebuild des models');
        $this->writeSection($output, 'App');
        $this->execCommand("php app/console propel:model:build", $app_env, $output);
        $this->writeSection($output, 'Auth');
        $this->execCommand("php app/console_auth propel:model:build", $auth_env, $output);

        $this->execCommand("php app/console propel:sql:build", $app_env, $output);

        $this->execCommand("php app/console propel:sql:insert --force", $app_env, $output);

        $this->execCommand("php app/console_auth propel:sql:build", $auth_env, $output);

        //Suppression du default qui surcharge contre notre volonté la classe client

        unlink($this->getContainer()->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . 'propel' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'default.sql');

        $this->execCommand("php app/console_auth propel:sql:insert --force", $auth_env, $output);


        $this->writeSection($output, 'Jeux de donnees initiales');

        $this->writeSection($output, 'Auth');

        $this->execCommand("php app/console_auth fixtures:load-central", $auth_env, $output);

        $this->writeSection($output, 'App');

        $this->execCommand("php app/console bns:load-init", $app_env, $output);
    }

    protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }

    protected function execCommand($command, $env, $output) {

        exec($command . " --env=".$env, $out, $return_status);

        if($return_status >0) {
                $this->writeSection($output,"-- ERROR while executing command: " . $command. " --env=".$env);
            foreach($out as $line) {
                $this->writeSection($output,$line);
            }     
        }

    }

}
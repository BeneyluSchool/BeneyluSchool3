<?php

namespace BNS\App\InstallBundle\Command;

use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\ArrayInput;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\ConsoleOutput;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Process\Process;

/**
 * @author Eymeric Taelman
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class InitCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('bns:install')
            ->setDescription('Initialize a project with basic features')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Initialization type: fast, normal, full', 'normal')
            ->addOption('app-only', null, InputOption::VALUE_NONE, 'Initialize app instance only')
            ->addOption('firstname', null, InputOption::VALUE_NONE, 'Administrator first name')
            ->addOption('lastname', null, InputOption::VALUE_NONE, 'Administrator last name')
            ->addOption('email', null, InputOption::VALUE_NONE, 'Administrator email adress')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output 
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = explode('_', $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ? : 'app_dev'));
        $appEnv = 'app_' . $environment[1];
        $authEnv = 'auth_' . $environment[1];
        
        $startTime = microtime(true);

        $this->writeSection($output, '
			###########################################################
			 
			          BENEYLU SCHOOL - PROJECT INITIALIZATION
			          ---------------------------------------

			                  SELECTED MODE : ' . strtoupper($input->getOption('type')) . '

			###########################################################'
        );

        // Drop databases
        if ($environment[1] == 'dev') {
            $this->writeSection($output, '# Deleting databases');
            $output->write(' - Database: App...	');
            $this->execProcess("propel:database:drop --force", $appEnv, $input, $output);
            $output->write('Finished', true);

            $output->write(' - Database: Stat...	');
            $this->execProcess("propel:database:drop --connection=stat --force", $appEnv, $input, $output);
            $output->write('Finished', true);
            
            if (!$input->getOption('app-only')) {
                $output->write(' - Database: Auth...	');
                $this->execProcess("propel:database:drop --force", $authEnv, $input, $output);
                $output->write('Finished', true);
            }
        }

        // Creating new databases
        $this->writeSection($output, '# Creating new databases');
        $output->write(' - Database: App...	');
        $this->execProcess("propel:database:create", $appEnv, $input, $output);
        $output->write('Finished', true);
        
        $output->write(' - Database: Stat...	');
        $this->execProcess("propel:database:create  --connection=stat", $appEnv, $input, $output);
        $output->write('Finished', true);

        if (!$input->getOption('app-only')) {
            $output->write(' - Database: Auth...	');
            $this->execProcess('propel:database:create', $authEnv, $input, $output);
            $output->write('Finished', true);
        }


        // Propel model:build
        $this->writeSection($output, '# Building Propel models');
        $output->write(' - Database: App...	');
        $this->execProcess('propel:model:build', $appEnv, $input, $output);
        $output->write('Finished', true);
        
        $output->write(' - Database: Stat...	');
        $this->execProcess('propel:model:build --connection=stat', $appEnv, $input, $output);
        $output->write('Finished', true);
        

        if (!$input->getOption('app-only')) {
            $output->write(' - Database: Auth...	');
            $this->execProcess('propel:model:build', $authEnv, $input, $output);
            $output->write('Finished', true);
        }

        // Generating SQL & insert
        //App && Stat
        $this->writeSection($output, '# Generating & insert database structure');
        $output->write(' - Database: App...	');
        $this->execProcess('propel:sql:build', $appEnv, $input, $output);
        $this->execProcess('propel:sql:insert --force', $appEnv, $input, $output);
        $output->write('Finished', true);
        
        if (!$input->getOption('app-only')) {
            $output->write(' - Database: Auth...	');
            $this->execProcess('propel:sql:build', $authEnv, $input, $output);

            // Deleting default.sql file
            unlink($this->getContainer()->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . 'propel' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'default.sql');
            $this->execProcess('propel:sql:insert --force', $authEnv, $input, $output);
            $output->write('Finished', true);
        }

        // Initial data
        $this->writeSection($output, '# Generating initial data');
        if (!$input->getOption('app-only')) {
            $output->write(' - Instance: Auth...	');
            $this->execProcess('fixtures:load-central', $authEnv, $input, $output);
            $output->write('Finished', true);
        }

        $output->write(' - Instance: App...	');
        $this->execCommand("bns:load --type=" .
                $input->getOption('type') .
                ($input->getOption('app-only') ? ' --app-only' : '') .
                ($input->getOption('firstname') ? ' --firstname=' . $input->getOption('firstname') : '') .
                ($input->getOption('lastname') ? ' --lastname=' . $input->getOption('lastname') : '') .
                ($input->getOption('email') ? ' --email=' . $input->getOption('email') : ''),
            $appEnv,
            $input,
            $output,
            true
        );
        $output->writeln('');

        $this->writeSection($output, '# The project initialization is finished (' . (microtime(true) - $startTime) . ') secs.');
    }

    /**
     * @param string $command
     * @param string $env
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param boolean $isVerbose
     */
    protected function execCommand($command, $env, InputInterface $input, OutputInterface $output, $forceVerbose = false)
    {
        $commands = explode(' ', $command);
        $command = $this->getApplication()->find($commands[0]);
        $argc = count($commands);
        $arguments = array(
            'command' => $commands[0],
            '--env' => $env
        );

        for ($i = 1; $i < $argc; $i++) {
            $argv = explode('=', $commands[$i]);
            $arguments[$argv[0]] = isset($argv[1]) ? $argv[1] : 'true';
        }

        // Verbosity
        if ($forceVerbose || $input->getOption('verbose')) {
            $arguments['--verbose'] = 'true';
        }

        $inputs = new ArrayInput($arguments);
        $newOutput = new ConsoleOutput($forceVerbose || $input->getOption('verbose') ? ConsoleOutput::VERBOSITY_VERBOSE : ConsoleOutput::VERBOSITY_QUIET);
        $returnCode = $command->run($inputs, $newOutput);

        if ($returnCode > 0) {
            $this->writeSection($output, "-- ERROR while executing command: " . $command . " --env=" . $env);

            exit($returnCode);
        }
    }

    /**
     * @param string $command
     * @param string $env
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param boolean $isVerbose
     */
    public function execProcess($command, $env, InputInterface $input, OutputInterface $output, $forceVerbose = false)
    {
        $process = new Process('php app/console ' . $command . ' --env=' . $env . ($forceVerbose || $input->getOption('verbose') ? ' --verbose' : ''));
        $process->run(function ($type, $buffer) use ($input) {
            if ($input->getOption('verbose')) {
                echo $buffer;
            }
        });
    }
}
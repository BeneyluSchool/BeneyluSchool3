<?php

namespace BNS\App\CommandBundle\Command;

use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ChangePasswordCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bns:change-password')
            ->setDescription("Change User's password")
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
            ->addArgument('username')
            ->addArgument('password')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $output->writeln('Change password for user ' . $username);
        $user = UserQuery::create()->findOneByLogin($username);
        if($user)
        {
            $um = $this->getContainer()->get('bns.user_manager');
            $um->setUser($user);
            $um->setPassword($input->getArgument('password'));
            $output->writeln('Password successfully changed');
        }else{
            $output->writeln('User ' . $username . ' not found');
        }

    }

}
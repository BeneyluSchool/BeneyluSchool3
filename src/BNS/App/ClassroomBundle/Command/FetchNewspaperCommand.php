<?php

namespace BNS\App\ClassroomBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FetchNewspaperCommand
 *
 * @package BNS\App\ClassroomBundle\Command
 */
class FetchNewspaperCommand extends ContainerAwareCommand
{

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('bns:classroom:fetchNewspaper');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = $this->getContainer()->get('router')->getContext();
        $context->setBaseUrl('/ent');

        $output->write('Fetching newspaper for today... ');
        $newspaper = $this->getContainer()->get('bns_app_classroom.newspaper_manager')->getForDate(date('Y-m-d'), true);
        $output->writeln($newspaper ? 'OK' : 'FAIL');
    }

}

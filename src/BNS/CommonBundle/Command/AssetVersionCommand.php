<?php
namespace BNS\CommonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AssetVersionCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bns:asset-version')
            ->setDescription('Get or the asset version')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version to set', null)
            ->addOption('timestamp', '-t', InputOption::VALUE_NONE, 'set the version to current timestamp')
            ;
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $redis = $this->getContainer()->get('snc_redis.default');

        if (!$input->getArgument('version') && !$input->getOption('timestamp')) {
            $version = $redis->get('assets_version');

            $output->writeln(sprintf('Current assets version is : "%s"', $version));
        } else {
            if ($version = $input->getArgument('version')) {
                $redis->set('assets_version', $version);
            } elseif ($input->getOption('timestamp')) {
                $redis->set('assets_version', time());
            } else {
                throw new \InvalidArgumentException();
            }

            $version = $redis->get('assets_version');

            $output->writeln(sprintf('New assets version is : "%s"', $version));
        }
    }
}

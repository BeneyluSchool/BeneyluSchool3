<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\MigrationBundle\Command\BaseMigrationCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MigrationIconitoCommand extends BaseMigrationCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:all')
            ->addOption('partial', null, InputOption::VALUE_NONE, 'Import iconito partiel')
            ->addOption('with-club', null, InputOption::VALUE_NONE, 'Import des club iconito')
            ->setDescription('Execute toutes les migrations de iconito à la V3')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandList = array(
                'group'       => array('cityGroup', 'city', 'school', 'classroom'),
                'user'        => array('user', 'external', 'pupil', 'parent', 'linkUser', 'linkExternal', 'linkPupil', 'linkParent'),
                'club'        => array('club'),
                'resource'    => array('album', 'albumFolder', 'albumPhoto', 'classeur', 'classeurFolder', 'classeurFolderUser', 'classeurFile'),
                'message'     => array('message'),
                'agenda'      => array('agenda', 'event'),
                'blog'        => array('blog', 'category', 'article', 'comment', 'page', 'link'),
                'homework'    => array('lecon', 'subject', 'homework', 'memo'),
                'liaisonbook' => array('liaisonbook', 'liaisonbookAnswer'),
                'minisite'    => array('minisite', 'club', 'clubPage'),
                );

        $optionList = array(
                'domainId',
                'albumFolder',
                'blogFolder',
                'classeurFolder',
                'minimailFolder',
                'miniSiteFolder',
                'pupils_file',
                'users_file',
                'filterRNE'
            );

        if ($input->getOption('partial')) {
            unset($commandList['agenda']);
            unset($commandList['homework']);
            unset($commandList['liaisonbook']);
        }

        $step = $input->getArgument('step');
        if (null !== $step) {
            if (isset($commandList[$step])) {
                foreach ($commandList as $key => $cmd) {
                    if ($step == $key) {
                        break;
                    }
                    unset($commandList[$key]);
                }
            } else {
                $output->writeln('<error>Invalid argument ' . $step . ' is not a valid step</error>');
                exit(1);
            }
        }

        $output->writeln('Started at ' . date('Y-m-d H:i:s'));

        foreach ($commandList as $commandName => $subCommands) {
            $output->writeln('Start migration of <info>' . $commandName .'</info>');
            $output->write('...');

            foreach ($subCommands as $subCommand) {
                $output->writeln('... sub command : ' . $subCommand);
                $cmd = 'php app/console bns:migration:iconito:' . $commandName . ' ' . $subCommand .
                    ' --end --verbose --env=' . $input->getOption('env') . ' --bnsEnv=' . $input->getOption('bnsEnv');

                foreach ($optionList as  $option) {
                    if ($input->getOption($option)) {
                        $cmd .= ' --' . $option .'=' . $input->getOption($option);
                    }
                }
                $output->writeln('cmd : ' . $cmd);

                $process = new Process($cmd);
                $process->setTimeout(36000);
                $process->run();

                $output->writeln($process->getOutput());

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException($process->getErrorOutput());
                }
            }
            $output->writeln(' <info>done.</info> ' . date('Y-m-d H:i:s'));
            if ($input->getOption('end')) {
                break;
            }
        }
        $output->writeln('Stoped at ' . date('Y-m-d H:i:s'));
    }

}

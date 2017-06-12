<?php

namespace BNS\App\ForumBundle\Command;

use BNS\App\ForumBundle\Model\ForumQuery;

use Propel\PropelBundle\Command\AbstractCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ArchiveCronCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('bns:forum-cron')
            ->setDescription('Launch cron that archive old forum')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
        ->setHelp('this cron will archive closed forum based on the "archive_after_closed" value');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $con = \Propel::getConnection($connectionName);
        \Propel::setForceMasterConnection(true);

        try {
            $con->beginTransaction();

            $this->launchArchiveForumCron($input, $output);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function launchArchiveForumCron($input, $output)
    {
        $this->writeSection($output, 'Launch Archive Forum cron...');

        $forums = ForumQuery::create()
            ->filterByClosedAt(null, \Criteria::ISNOTNULL)
            ->filterByIsArchived(false)
            ->withColumn('DATE_ADD(closed_at, INTERVAL archive_after_closed MONTH)', 'archive_date')
            ->where("DATE_ADD(closed_at, INTERVAL archive_after_closed MONTH) < NOW()")
            ->find();

        foreach ($forums as $forum) {
            $forum->setIsArchived(true);
            $forum->save();

            $forum->anonymizeAll();

            $output->writeln('Forum : ' . $forum->getTitle() . ' archivé.');
        }

        $this->writeSection($output, 'End of Archive Forum cron.');
    }
}

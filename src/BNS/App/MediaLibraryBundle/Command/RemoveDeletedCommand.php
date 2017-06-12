<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RemoveDeletedCommand
 *
 * @package BNS\App\MediaLibraryBundle\Command
 */
class RemoveDeletedCommand extends AbstractCommand
{

    /**
     * @var MediaLibraryManager
     */
    private $mediaLibraryManager;

    protected function configure()
    {
        $this
            ->setName('media-library:remove-deleted')
            ->setDescription('Remove (from database and disk) medias deleted and older than the given age (in days)')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Batch size', 1000)
            ->addOption('age', null, InputOption::VALUE_OPTIONAL, 'Age in days', 30)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mediaLibraryManager = $this->getContainer()->get('bns.media_library.manager');
        $limit = $input->getOption('batch');
        $age = $input->getOption('age');
        $processed = 0;
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $con = \Propel::getConnection($connectionName);
        try {
            $query = MediaQuery::create()
                ->filterByStatusDeletion(MediaManager::STATUS_DELETED)
                ->filterByUpdatedAt(array('max' => 'now -'.$age.'days'))
            ;
            $con->beginTransaction();

            // count total number of medias
            $output->write('Counting medias... ');
            $nbMediasQuery = clone $query;
            $nbMedias = $nbMediasQuery->count();
            $output->writeln(sprintf('<info>%s</info>', $nbMedias));

            while ($processed < $nbMedias) {
                $mediasQuery = clone $query;
                $medias = $mediasQuery
                    ->limit($limit)
                    ->offset(0)     // always start from start, since already-processed medias are no longer here
                    ->find();

                $output->writeln(sprintf('Handling <comment>%s-%s</comment>', $processed + 1, $processed + $medias->count()));

                /** @var Media $media */
                foreach ($medias as $media) {
                    $this->mediaLibraryManager->deleteMediaDirectory($media);
                    $media->delete($con);
                }

                $processed += $limit;
            }

            $output->writeln('<info>Done</info>.');

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

}

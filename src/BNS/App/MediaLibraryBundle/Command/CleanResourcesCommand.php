<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanResourcesCommand
 *
 * @package BNS\App\MediaLibraryBundle\Command
 */
class CleanResourcesCommand extends AbstractCommand
{

    /**
     * @var MediaLibraryManager
     */
    private $mediaLibraryManager;

    protected function configure()
    {
        $this
            ->setName('media-library:clean-resources')
            ->setDescription('Clean all resource files, except originals, for existing medias')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Batch size', 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mediaLibraryManager = $this->getContainer()->get('bns.media_library.manager');
        $limit = $input->getOption('batch');
        $offset = 0;
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $con = \Propel::getConnection($connectionName);
        try {
            $con->beginTransaction();

            // count total number of medias
            $output->write('Counting medias... ');
            $nbMedias = MediaQuery::create()->count();
            $output->writeln(sprintf('<info>%s</info>', $nbMedias));

            // handle them by batches
            while ($offset < $nbMedias) {
                $medias = MediaQuery::create()
                    ->limit($limit)
                    ->offset($offset)
                    ->find();

                $output->writeln(sprintf('Handling <comment>%s-%s</comment>', $offset + 1, $offset + $medias->count()));

                /** @var  $media Media */
                foreach ($medias as $media) {
                    $this->mediaLibraryManager->cleanMediaDirectory($media);
                }

                $offset += $limit;
            }

            $output->writeln('<info>Done</info>.');

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

}

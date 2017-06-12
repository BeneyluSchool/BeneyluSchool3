<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Gaufrette\Exception\FileAlreadyExists;
use Gaufrette\Filesystem;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MoveMediaToRunaboveCommand extends ContainerAwareCommand
{

    protected $output;
    protected $verbose;

    protected function configure()
    {
        $this
            ->setName('media-library:move-media-runabove')
            ->setDescription('Move local media to runabove')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset query')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit query');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $localAdapter = $container->get('bns.local.adapter');
        $runaboveAdapter = $container->get('bns.runabove.adapter');
        $localFs = new Filesystem($localAdapter);
        $runaboveFs = new Filesystem($runaboveAdapter);

        \Propel::disableInstancePooling();
        // media query
        $mediaQuery = MediaQuery::create()
            ->filterByStatusDeletion('-1', \Criteria::NOT_EQUAL)
            ->orderById();
        if ($input->hasOption('offset')) {
            $mediaQuery->offset($input->getOption('offset'));
        }
        if ($input->hasOption('limit')) {
            $mediaQuery->limit($input->getOption('limit'));
        }
        $media = $mediaQuery
            ->setFormatter(\ModelCriteria::FORMAT_ON_DEMAND)
            ->find();

        $output->writeln('BENEYLU SCHOOL - Media Transfer');
        $output->writeln(sprintf('Number of media to transfer : <info>%s</info>', $media->count()));

        $errors = 0;
        foreach ($media as $m) {
            $path = $m->getFilePath();

            if ($localFs->has($path)) {
                try {
                    if(!$runaboveFs->has($path)){
                        $newFilename = $this->cleanupFileName($m->getFilename());
                        if($m->getFilename() != $newFilename)
                        {
                            $m->setFilename($this->cleanupFileName($m->getFilename()));
                            $m->save();
                        }
                        $runaboveFs->write($m->getFilePath(), $localFs->read($path));
                        $output->writeln(sprintf('<info>%s</info> transfered', $m->getId()));
                    }else{
                        $output->writeln(sprintf('<comment>%s</comment> already transfered', $m->getId()));
                    }


                } catch (FileAlreadyExists $e) {
                    $output->writeln(sprintf('<comment>%s</comment> already transfered', $m->getId()));
                } catch (\Exception $e) {
                    $output->writeln(sprintf('<error>%s</error> error : %s', $m->getId(), $e->getMessage()));
                    $errors++;
                }
            } else {
                $output->writeln(sprintf('<error>%s</error> absent local file', $m->getId()));
                $errors++;
            }
        }
        $output->writeln('Transfer finished');
        $output->writeln(sprintf('Errors : <error>%s</error>', $errors));
    }

    protected function cleanupFileName($slug, $replacement = '.')
    {
        // transliterate
        if (function_exists('iconv')) {
            $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        }

        // lowercase
        if (function_exists('mb_strtolower')) {
            $slug = mb_strtolower($slug);
        } else {
            $slug = strtolower($slug);
        }

        // remove accents resulting from OSX's iconv
        $slug = str_replace(array('\'', '`', '^'), '', $slug);

        // replace non letter or digits with separator
        $slug = preg_replace('/\W+/', $replacement, $slug);

        // trim
        $slug = trim($slug, $replacement);

        if (empty($slug)) {
            return 'n-a';
        }

        return $slug;
    }
}

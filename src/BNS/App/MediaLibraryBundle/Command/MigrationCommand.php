<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaLinkGroup;
use BNS\App\MediaLibraryBundle\Model\MediaLinkGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaLinkUser;
use BNS\App\MediaLibraryBundle\Model\MediaLinkUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Gaufrette\Filesystem;

class MigrationCommand extends ContainerAwareCommand
{

	protected function configure()
    {
        $this
            ->setName('media-library:migration')
            ->setDescription('Migration vers nouvelle médiathèque')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset query',0)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit query',1000)
        ;
    }

	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit','-1');

        $limit = $input->getOption('limit');
        $offset = $input->getOption('offset');

        $groupLinks = MediaLinkGroupQuery::create()
            ->offset($offset)
            ->limit($limit)
            ->find();

        $i = 0;



        foreach($groupLinks as $link)
        {

            /** @var MediaLinkGroup $link */
            if(!$link->getIsStrongLink())
            {
                $link->delete();
            }else{
                try{

                    $media = MediaQuery::create()->findOneById($link->getResourceId());
                    $mediaId = $media->getId();
                    $media->setMediaFolderId($link->getResourceFolderGroupId());
                    $media->setMediaFolderType('GROUP');
                    if($media->getStatusDeletion() != MediaManager::STATUS_ACTIVE)
                    {
                        $media->setDeletedBy($media->getUserId());
                    }

                    $media->save();

                    /*if($link->getStatus() == 0)
                    {

                        $folder = $media->getMediaFolder();
                        $folder->setStatusDeletion(MediaFolderManager::STATUS_GARBAGED);
                        $folder->setDeletedBy($media->getUserId());
                        $folder->save();
                    }*/
                }catch(\Exception $e){
                    $media->delete();
                    $output->writeln('ALERT ! ' . $mediaId);
                }

                $link->delete();
            }


            $i++;

            if($i%100 == 0)
            {
                $output->writeln($i);
            }
        }


        $userLinks = MediaLinkUserQuery::create()
            ->offset($offset)
            ->limit($limit)
            ->find();

        $i = 0;

        foreach($userLinks as $link)
        {
            /** @var MediaLinkUser $link */
            if(!$link->getIsStrongLink())
            {
                $link->delete();
            }else{
                $media = MediaQuery::create()->findOneById($link->getResourceId());
                $media->setMediaFolderId($link->getResourceFolderUserId());
                $media->setMediaFolderType('USER');
                if($media->getStatusDeletion() != MediaManager::STATUS_ACTIVE)
                {
                    $media->setDeletedBy($media->getUserId());
                }

                $media->save();

                /*if($link->getStatus() == 0)
                {
                    $folder = $media->getMediaFolder();
                    $folder->setStatusDeletion(MediaFolderManager::STATUS_GARBAGED);
                    $folder->setDeletedBy($media->getUserId());
                    $folder->save();
                }*/

                $link->delete();
            }


            $i++;

            if($i%100 == 0)
            {
                $output->writeln($i);
            }
        }
	}
}
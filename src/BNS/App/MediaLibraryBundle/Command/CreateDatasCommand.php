<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\MediaLibraryBundle\Manager\MediaCreator;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;


class CreateDatasCommand extends ContainerAwareCommand
{
	public function configure()
    {
        $this
            ->setName('media:create-datas')
            ->addArgument('userId',null, InputArgument::OPTIONAL, 'User to create personnal datas')
            ->addArgument('groupId', null, InputOption::VALUE_OPTIONAL,'User to create personnal datas')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Create size for document with empty size.')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $groupId = $input->getArgument('groupId');
        $userId = $input->getArgument('userId');

		MediaFolderGroupQuery::create()->filterByGroupId($groupId)->delete();

        $labels = array(
            "Maths",
            "Français",
            'Photos',
            'Histoire',
            'Géo',
            'Selfies'
        );

        $folderCreated = array();

        $root = new MediaFolderGroup();
        $root->makeRoot();
        $root->setLabel('Mon dossier de groupe');
        $root->setGroupId($groupId);
        $root->save();
        $folderCreated[] = array('GROUP',$root->getId());

        $userFolder = new MediaFolderGroup();
        $userFolder->insertAsFirstChildOf($root);
        $userFolder->setIsUserFolder(true);
        $userFolder->setLabel('Dossier utilisateurs');
        $userFolder->save();
        $folderCreated[] = array('GROUP',$userFolder->getId());

        foreach($labels as $label)
        {
            $folder = new MediaFolderGroup();
            $folder->insertAsLastChildOf($root);
            $folder->setIsUserFolder(false);
            $folder->setLabel($label);
            $folder->save();
            $folderCreated[] = array('GROUP',$folder->getId());
        }

        $lastFolder = $folder;

        $folder = new MediaFolderGroup();
        $folder->insertAsLastChildOf($lastFolder);
        $folder->setIsUserFolder(false);
        $folder->setLabel('Sous dossier');
        $folder->save();
        $folderCreated[] = array('GROUP',$folder->getId());

        MediaFolderUserQuery::create()->filterByUserId($userId)->delete();

        $userFolder = new MediaFolderUser();
        $userFolder->setUserId($userId);
        $userFolder->setLabel('Mes documents');
        $userFolder->makeRoot();
        $userFolder->save();

        $folderCreated[] = array('USER',$userFolder->getId());

        $labels = array(
            "Mes copains",
            "Ma copine",
            'Les vacances',
            'Selfies'
        );

        foreach($labels as $label)
        {
            $folder = new MediaFolderUser();
            $folder->insertAsLastChildOf($userFolder);
            $folder->setLabel($label);
            $folder->save();
            $folderCreated[] = array('USER',$folder->getId());
        }

        $medias = array(
            array(
                'Ma photo de chat',
                'La description de mon image',
                'IMAGE',
                'http://static.wamiz.fr/images/news/medium/acheter-jouet-pour-chat-pas-cher.jpg',
                'acheter-jouet-pour-chat-pas-cher.jpg'
            ),
            array(
                'Ma photo de chien',
                'La description de mon image',
                'IMAGE',
                'http://davidfeldmanshow.com/wp-content/uploads/2014/01/dogs-wallpaper.jpg',
                'dogs-wallpaper.jpg'
            ),
            array(
                'Ma selfie',
                'La description de mon image',
                'IMAGE',
                'http://bobritzema.files.wordpress.com/2013/12/justin-bieber-2013-selfie.jpg',
                'justin-bieber-2013-selfie.jpg'
            ),
            array(
                'Ma photo de paysage',
                'La description de mon image',
                'IMAGE',
                'http://atelierscientifiquevernant.e-monsite.com/medias/images/paysage-montagneux.jpg',
                'paysage-montagneux.jpg'
            )
        );

        foreach($folderCreated as $folder)
        {
            foreach($medias as $media)
            {
                $new = new Media();
                $new->setLabel($media[0]);
                $new->setDescription($media[1]);
                $new->setTypeUniqueName($media[2]);
                $new->setValue($media[3]);
                $new->setFileMimeType($media[4]);
                $new->setMediaFolderType($folder[0]);
                $new->setMediaFolderId($folder[1]);
                $new->setUserId($userId);
                $new->save();
            }
        }
	}

}
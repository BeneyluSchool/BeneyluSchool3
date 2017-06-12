<?php

namespace BNS\App\CoreBundle\Import;

use BNS\App\CoreBundle\Model\Import;
use BNS\App\ResourceBundle\FileSystem\BNSFileSystemManager;

/**
 * Description of ImportManager
 *
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class ImportManager {

    private $producerUser;
    private $producerClass;
    private $producerSchool;
    private $logger;
    private $fileSystem;

	/**
	 * @param type $producer
	 */
	public function __construct($logger, $fileSystem, $producerUser, $producerClass, $producerSchool)
	{
		$this->producerUser = $producerUser;
        $this->producerClass = $producerClass;
        $this->producerSchool = $producerSchool;

        $this->fileSystem = $fileSystem->getFileSystem();
        $this->logger = $logger;
	}

    /**
     * creation du fichier d'import apres upload
     *
     * @param \BNS\App\CoreBundle\Model\Import $import
     * @param type $file
     */
    public function createFile(Import $import, $file)
	{
        $id = $import->getId();
        //sauvegarde dans /data/resources/imports/
        $key = "/imports/".$id.".csv";

        $this->fileSystem->createFile($key);
        $this->fileSystem->write($key, file_get_contents($file));
    }

    /**
     * redirige l'importation en fonction du type
     *
     * @param \BNS\App\CoreBundle\Model\Import $import
     * @param type $file
     */
	public function import(Import $import, $file)
	{
        $id = $import->getId();

        $parameters = array(
			'id' => $id,
		);

        switch($import->getType()){
            case "CLASSROOM":
                $this->producerClass->publish(serialize($parameters));
                break;
            case "SCHOOL":
                $this->producerSchool->publish(serialize($parameters));
                break;
            default://adults and pupils (USER)
                $this->producerUser->publish(serialize($parameters));
                break;
        }
	}
}

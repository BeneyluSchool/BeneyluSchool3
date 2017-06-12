<?php

namespace BNS\App\CoreBundle\Import;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupDataQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\User\BNSUserManager;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use BNS\App\CoreBundle\Model\Import;
use BNS\App\CoreBundle\Model\ImportQuery;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use Gaufrette\Adapter\InMemory as InMemoryAdapter;
use Gaufrette\Filesystem;
use Gaufrette\StreamWrapper;
use Propel;

/**
 * Description of ImportUserConsumer
 *
 * @author alexandre.melard@worldline.com
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
abstract class AbstractImportConsumer implements ConsumerInterface
{
    abstract protected function onImport(Import $import);
    abstract protected function onLineRead(Import $import, $line, $classroomStructure, $schoolStructure);

    /** @var Logger $logger */
    protected $logger;
    
    protected $domainId;

    private $groupManager;

    protected $groupsToImport = array();
    protected $usersToImport = array();
    protected $affectationsToImport = array();
    public $groupTypeCache = array();
    public $classroomStructure = array();
    public $schoolStructure = array();

    /** @var BNSFileSystemManager $filesystemManager */
    private $filesystemManager;
    private $newGroupManager;


    
    /**
     * 
     * @param \Monolog\Logger $logger
     * @param \BNS\App\ResourceBundle\FileSystem\BNSFileSystemManager $fsm
     * @param type $domainId
     */
    public function __construct(Logger $logger, BNSFileSystemManager $fsm, $domainId, BNSGroupManager $groupManager, BNSUserManager $userManager)
    {
        $this->logger = $logger;
        $this->filesystemManager = $fsm;
        $this->domainId = $domainId;
        $this->newGroupManager = $groupManager;
        $this->newUserManager = $userManager;
        $groupTypes = GroupTypeQuery::create()->find();
        foreach($groupTypes as $groupType)
        {
            $this->groupTypeCache[$groupType->getType()] = $groupType;
        }
        $this->classroomStructure = array();
    }

    public function buildClassroomStructure()
    {
        $values = GroupDataQuery::create()
            ->useGroupTypeDataQuery()
                ->filterByGroupTypeId(2)
                ->filterByGroupTypeDataTemplateUniqueName('STRUCTURE_ID')
            ->endUse()
            ->find();
        foreach($values as $value)
        {
            $this->classroomStructure[$value->getValue()]['Id'] = $value->getGroupId();
        }
    }

    public function buildSchoolStructure()
    {
        $values = GroupDataQuery::create()
            ->useGroupTypeDataQuery()
            ->filterByGroupTypeId(3)
            ->filterByGroupTypeDataTemplateUniqueName('UAI')
            ->endUse()
            ->find();
        foreach($values as $value)
        {
            $this->schoolStructure[$value->getValue()] = $value->getGroupId();
        }
    }

    /**
     * lance l'importation d'un fichier
     * 
     * @param \PhpAmqpLib\Message\AMQPMessage $msg
     * @return type
     * @throws \Exception
     */
    public function execute(AMQPMessage $msg)
    {
        ini_set('memory_limit','-1');
        Propel::disableInstancePooling();
        $this->logger->debug("ImportConsumer::execute[received import message]");
        $params = unserialize($msg->body);
        $this->logger->debug("MESSAGE " . $params['id']);
        //récupère l'importation
        $import = ImportQuery::create()->findOneById($params['id']);
        
        //si on ne trouve pas l'import en base
        if($import == null) {
            throw new \Exception("import data does not exists in Database");
        }
        
        $this->logger->debug("ImportConsumer::execute[import message id=(" . $import->getId() . ")]");
        
        if($import->getStatus() == "UNVALIDATE") {
            return;
        }
        //méthode d'initialisation de l'import, à surcharger en cas de besoin
        $this->onImport($import);

        //$this->buildClassroomStructure();
        //$this->buildSchoolStructure();

        //set de la date de lancement de l'importation
        $import->setDateLaunch(new \DateTime("NOW"));
        //On remet à zéro nbr de lignes traitées si plantage
        $import->setFileLineProcessedNbr(0);

        //recuperation du fichier d'import
        $filePath = "/imports/" . $import->getId() . ".csv";
        $fs = $this->filesystemManager->getFileSystem();
        
        //si le fichier n'existe pas 
        if (!$fs->has($filePath)) {
            throw new \Exception("File does not exists");
        }
        
        //ouverture d'un fichier temporaire en mémoire pour lecture ligne par ligne
        $adapter = new InMemoryAdapter(array($import->getId().'.csv' => $fs->read($filePath)));
        $filesystem = new Filesystem($adapter);

        $map = StreamWrapper::getFilesystemMap();
        $map->set('import', $filesystem);

        StreamWrapper::register();
        
        $handle = fopen("gaufrette://import/".$import->getId().".csv", 'r');

        //initialisation du nombre de ligne
        $rowNbr = 0;

        $import->setProjectName($this->newGroupManager->setGroup($import->getGroupRelatedByGroupId())->getProjectInfo('name'));
        
        //lecture ligne par ligne
        while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
            //on saute la premiere ligne
            if($rowNbr > 0) {
                //appel de la methode surchargee
                $this->onLineRead($import, $line, $this->classroomStructure, $this->schoolStructure);
                $import->setFileLineProcessedNbr($import->getFileLineProcessedNbr() + 1);
                $import->save();
                if($rowNbr % 100 == 0)
                {
                    echo $rowNbr . ' ';
                }
            }
            $rowNbr++;
        }
        //On vide la pile
        if(in_array($import->getType(),array('CLASSROOM','SCHOOL')))
        {
            $this->newGroupManager->createGroups($this->groupsToImport);
            $this->groupsToImport = array();
        }elseif(in_array($import->getType(),array('ADULT','PUPIL'))){
            $this->newUserManager->createUsers($this->usersToImport);
            $this->usersToImport = array();
            $this->newUserManager->createAffectations($this->affectationsToImport);
            $this->affectationsToImport = array();
        }
        //sauvegarde de l'objet import
        $import->setStatus("FINISH");
        $import->setDateEnd(new \DateTime("NOW"));
        $import->save();
        Propel::disableInstancePooling();
        //unlink("gaufrette://import/".$import->getId().".csv");
    }

    /**
     * Permet de lister les groupes à créer
     * @param $params
     */
    public function addGroupToImport($params)
    {
        $this->groupsToImport[] = $params;
        if(count($this->groupsToImport) == 100)
        {
            $this->newGroupManager->createGroups($this->groupsToImport);
            $this->groupsToImport = array();
        }
    }

    /**
     * Permet de lister les utilisateurs à créer
     * @param $params
     * @param bool $canLaunch Permet d'empêcher l'envoi dans le cas d'utilisateurs liés (parents / enfants par exemple)
     */
    public function addUserToImport($params, $canLaunch = true)
    {
        $this->usersToImport[] = $params;
        if(count($this->usersToImport) >= 200 && $canLaunch)
        {
            $this->newUserManager->createUsers($this->usersToImport, false);
            $this->usersToImport = array();
        }
    }

    /**
     * Permet de lister les affectations à créer
     * @param $params
     */
    public function addAffectationsToImport($params)
    {
        $this->affectationsToImport[] = $params;
        if(count($this->affectationsToImport) == 200)
        {
            $this->newUserManager->createAffectations($this->affectationsToImport);
            $this->affectationsToImport = array();
        }
    }

}

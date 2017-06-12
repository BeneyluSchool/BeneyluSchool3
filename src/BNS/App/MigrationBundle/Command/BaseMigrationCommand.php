<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplate;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\MigrationBundle\Model\MigrationIconito;
use BNS\App\MigrationBundle\Model\MigrationIconitoQuery;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\ResourceBundle\Model\ResourceJoinObject;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
abstract class BaseMigrationCommand extends ContainerAwareCommand
{
    /**
     * @var $input InputInterface
     */
    protected $input;

    /**
     * @var $output OutputInterface
     */
    protected $output;

    protected $environment;

    protected $environmentName;

    protected $groupTypes = array();

    protected $groupClub;

    protected $start;

    protected $oldFileSystem = array();

    protected $filterPupilIds;

    protected $filterParentIds;

    protected $filterUserIds;

    protected function configure()
    {
        $this->addArgument('step', InputArgument::OPTIONAL, "L'etape de depart")
            ->addOption('bnsEnv', null, InputOption::VALUE_OPTIONAL, "nom de l'environnement (www.beneyluschool.net)")
            ->addOption('domainId', null, InputOption::VALUE_OPTIONAL, "id du domaine (1)", 1)
            ->addOption('end', null, InputOption::VALUE_NONE, "Arrêt à chaque tache")
            ->addOption('albumFolder', null, InputOption::VALUE_OPTIONAL, 'dossier ou se trouve les album photo', 'app/data/iconito/album')
            ->addOption('classeurFolder', null, InputOption::VALUE_OPTIONAL, 'dossier ou se trouve les fichiers des classeurs', 'app/data/iconito/classeur')
            ->addOption('blogFolder', null, InputOption::VALUE_OPTIONAL, 'dossier ou se trouve les logo du blog', 'app/data/iconito/blog')
            ->addOption('minimailFolder', null, InputOption::VALUE_OPTIONAL, 'dossier ou se trouve les pieces jointes email', 'app/data/iconito/minimail')
            ->addOption('miniSiteFolder', null, InputOption::VALUE_OPTIONAL, 'dossier ou se trouve le logo des fiches ecoles', 'app/data/iconito/fichesecoles')
            ->addOption('pupils_file', null, InputOption::VALUE_OPTIONAL, "fichier qui contient la reprise des eleves", 'app/data/temp/export_pupils.csv')
            ->addOption('users_file', null, InputOption::VALUE_OPTIONAL, "fichier qui contient la reprise des utilisateurs", 'app/data/temp/export_users.csv')
            ->addOption('filterRNE', null, InputOption::VALUE_OPTIONAL, "liste des RNE à reimporter separe par des vigules ou true pour valeur par default")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->start = microtime(true);
        $this->input = $input;
        $this->output = $output;

        $buzz = $this->get('buzz');
        $buzz->setMaxTries(10);
        $curl = $buzz->getClient();
        $curl->setTimeout(60);
        ini_set("memory_limit", "-1");

        BNSAccess::setContainer($this->getContainer());
    }

    protected function end()
    {
        $this->output->writeln(sprintf('<info>Fin</info> de la migration %s', date('d/m/Y H:i:s')));
        $this->output->writeln(sprintf('duree de la migration : <info>%s sec</info>',  (microtime(true) - $this->start)));
        if ($this->input->getOption('end')) {
            exit(0);
        }
    }

    protected function displayResult($infos)
    {
        if (isset($infos['duplicate'])) {
            $this->output->writeln(sprintf('import des <info>%s</info> : <info>%s</info> ok, <comment>%s</comment> duplique %s ignore, <error>%s</error> erreur. Total <info>%s</info>',
                    $infos['label'], $infos['success'], $infos['duplicate'], $infos['ignore'], $infos['error'], $infos['total']));
        } else {
            $this->output->writeln(sprintf('import des <info>%s</info> : <info>%s</info> ok, %s ignore, <error>%s</error> erreur. Total <info>%s</info>',
                    $infos['label'], $infos['success'], $infos['ignore'], $infos['error'], $infos['total']));
        }
    }

    /**
     * Check if the object was already imported
     *
     * @param int|string $key the original PK to test against import
     * @param string $class the original class
     * @param string $customKey the original custon PK
     *
     * @return boolean
     */
    protected function isImported($key, $class, $customKey = null)
    {
        return $this->getImportedQuery($key, $class, $customKey)->count() > 0;
    }

    /**
     *
     * @param int $key
     * @param string $class
     * @param string $customKey
     * @return MigrationIconito
     */
    protected function getImported($key, $class, $customKey = null)
    {
        return $this->getImportedQuery($key, $class, $customKey)->findOne();
    }

    protected function getImportedGroup($key, $buType)
    {
        switch (strtoupper($buType)) {
            case 'GVILLE':
                $originTable = 'kernel_bu_groupe_villes';
                break;
            case 'BU_VILLE':
            case 'VILLE':
                $originTable = 'kernel_bu_ville';
                break;
            case 'BU_ECOLE':
            case 'ECOLE':
                $originTable = 'kernel_bu_ecole';
                break;
            case 'BU_CLASSE':
            case 'CLASSE':
                $originTable = 'kernel_bu_ecole_classe';
                break;
            case 'CLUB':
                $originTable = 'module_groupe_groupe';
                break;
            default:
                return null;
        }

        return $this->getImported($key, $originTable);
    }

    /**
     *
     * @param int|string $key origin key
     * @param string $class origin class
     * @param string $customKey origin customKey
     *
     * @return MigrationIconitoQuery
     */
    protected function getImportedQuery($key, $class, $customKey = null)
    {
        return MigrationIconitoQuery::create()
            ->filterByOriginKey($key)
            ->filterByOriginClass($class)
            ->filterByEnvironment($this->getEnvironmentName())
            ->_if($customKey)
                ->filterByOriginCustomKey($customKey)
            ->_endif();
    }

    /**
     * @param $originKey
     * @param $originClass
     * @param $bnsKey
     * @param $bnsClass
     * @param null $data
     * @param null $originCustomKey
     * @param null $bnsCustomKey
     * @return int
     */
    protected function saveImported($originKey, $originClass, $bnsKey, $bnsClass, $data = null, $originCustomKey = null, $bnsCustomKey = null)
    {
        $migrationIconito = new MigrationIconito();
        $migrationIconito->setOriginKey($originKey);
        $migrationIconito->setOriginClass($originClass);
        $migrationIconito->setBnsKey($bnsKey);
        $migrationIconito->setBnsClass($bnsClass);
        $migrationIconito->setEnvironment($this->getEnvironmentName());
        if (null !== $data) {
            $migrationIconito->setData($data);
        }
        if (null !== $originCustomKey) {
            $migrationIconito->setOriginCustomKey($originCustomKey);
        }
        if (null !== $bnsCustomKey) {
            $migrationIconito->setBnsCustomKey($bnsCustomKey);
        }

        $res =  $migrationIconito->save();
        unset($migrationIconito);

        return $res;
    }

    protected function getEnvironmentName()
    {
        if (null === $this->environmentName) {
            $this->environmentName = $this->input->getOption('bnsEnv')?: $this->getContainer()->getParameter('application_environment');
        }

        return $this->environmentName;
    }

    protected function getEnvironment()
    {
        if (null === $this->environment) {
            $this->environment = GroupQuery::create()
                ->filterByLabel($this->getEnvironmentName())
                ->useGroupTypeQuery()
                    ->filterByType('ENVIRONMENT')
                ->endUse()
            ->findOne();
        }

        return $this->environment;
    }

    protected function getGroupClub()
    {
        if (null === $this->groupClub) {
            $groupManager = $this->get('bns.group_manager');
            $groupManager->setGroup($this->getEnvironment());
            $groupClub = $groupManager->getSubgroupsByGroupType($this->getGroupType('GROUP_CLUB')->getType());
            if (0 === count($groupClub)) {
                $this->groupClub = $groupManager->createSubgroupForGroup(array(
                    'label'            => 'Groupe de club',
                    'domain_id'        => $this->input->getOption('domainId'),
                    'validated'        => true,
                    'type'             => 'GROUP_CLUB',
                ), $this->getEnvironment()->getId(), false);
            } else {
                $this->groupClub = reset($groupClub);
            }
        }

        return $this->groupClub;
    }

    /**
     *
     * @param string $type
     * @return GroupType
     */
    protected function getGroupType($type)
    {
        if (!isset($this->groupTypes[$type])) {
            $groupType = GroupTypeQuery::create()->findOneByType($type);
            if (!$groupType) {
                $label = $type;
                $simulateRole = 0;
                switch ($type) {
                    case 'CITY_GROUP':
                        $label = 'Groupe de ville';
                        break;
                    case 'CITY':
                        $label = 'Ville';
                        break;
                    case 'GROUP_CLUB':
                        $label = 'Groupe de club';
                        break;
                    case 'CLUB':
                        $label = 'Club';
                        break;
                    case 'DIRECTOR':
                        $label = 'Directeur';
                        $simulateRole = 1;
                        break;
                    case 'SCHOOL_OFFICER':
                        $label = 'Administratif';
                        $simulateRole = 1;
                        break;
                    case 'CITY_OFFICER':
                        $label = 'Agent de ville';
                        $simulateRole = 1;
                        break;
                    case 'GROUP_CITY_OFFICER':
                        $label = 'Agent de groupe de villes';
                        $simulateRole = 1;
                        break;
                    case 'EXTERNAL':
                        $label = 'Extérieur';
                        $simulateRole = 1;
                        break;
                }

                $groupType = $this->get('bns.group_manager')->createGroupType(array(
                        'label'         => $label,
                        'type'          => $type,
                        'centralize'    => 0,
                        'domain_id'     => null,
                        'simulate_role' => $simulateRole,
                        'is_recursive'  => 0,
                ));

            }
            if ('CLUB' === $type) {
                $groupType->addGroupTypeDataByUniqueName('NAME');
                $groupType->addGroupTypeDataByUniqueName('DESCRIPTION');
            }

            $this->groupTypes[$type] = $groupType;
        }

        return $this->groupTypes[$type];
    }

    /*
     * Renvoie l'objet type de groupe à partir du type de User Iconito
     */
    protected function mapUserType($iconitoType)
    {
        $array = array(
            'USER_ENS' => 'TEACHER',
            'USER_RES' => 'PARENT',
            'USER_ELE' => 'PUPIL',
            'USER_EXT' => 'EXTERNAL',
            'USER_VIL' => 'CITY_OFFICER',
            'USER_ADM' => 'ADMIN',
        );
        return $this->getGroupType($array[$iconitoType]);
    }

    protected function log($message)
    {
        $this->get('logger')->err($message);
    }

    protected function convertUTF8($string)
    {
        return mb_convert_encoding(trim($string), 'UTF-8', 'ASCII, UTF-8, ISO-8859-1, CP1252');
    }

    /**
     * Gets a service.
     *
     * @param string $id              The service identifier
     *
     * @return object The associated service
     *
     * @throws InvalidArgumentException if the service is not defined
     */
    protected function get($id)
    {
        return $this->getContainer()->get($id);
    }

    protected function iniGroupTypeData($uniqueName, array $labels, $defaultValue = null)
    {
        if (0 === GroupTypeDataTemplateQuery::create()->filterByUniqueName($uniqueName)->count()) {
            $groupTypeDataTemplate = new GroupTypeDataTemplate();
            $groupTypeDataTemplate->setUniqueName($uniqueName);
            $groupTypeDataTemplate->setType(GroupTypeDataTemplatePeer::TYPE_SINGLE);
            if (null !== $defaultValue) {
                $groupTypeDataTemplate->setDefaultValue($defaultValue);
            }
            $groupTypeDataTemplate->save();
        }
    }

    protected function purifyHtml($html)
    {
        return $this->get('exercise_html_purifier.import')->purify($html);
    }

    protected function getOldFileSystem($type)
    {
        if (!isset($this->oldFileSystem[$type])) {
            switch ($type) {
                case 'MessagingMessage':
                    $oldFileSystemAdapter = new Local($this->input->getOption('minimailFolder'));
                    $this->oldFileSystem[$type] = new Filesystem($oldFileSystemAdapter);
                    break;
                case 'Blog':
                    $oldFileSystemAdapter = new Local($this->input->getOption('blogFolder'));
                    $this->oldFileSystem[$type] = new Filesystem($oldFileSystemAdapter);
                    break;
                case 'MiniSite':
                    $oldFileSystemAdapter = new Local($this->input->getOption('miniSiteFolder'));
                    $this->oldFileSystem[$type] = new Filesystem($oldFileSystemAdapter);
                    break;
            }
        }

        return $this->oldFileSystem[$type];
    }

    protected function createJoinResource($objectId, $objectClass, $customKey, $bnsUserId, $fileName, $isPrivate = false)
    {
        $rc = $this->get('bns.resource_creator');
        $oldFileSystem = $this->getOldFileSystem($objectClass);

        $userLabel = ResourceLabelUserQuery::create()->filterByUserId($bnsUserId)->filterByTreeLevel(0)->findOne();

        if (!$userLabel || !$oldFileSystem) {
            return false;
        }

        $resource = new Resource();
        $resource->setLabel($fileName);
        $resource->setLang('fr');
        $resource->setFilename($fileName);

        $ext = substr(strrchr($fileName, '.'), 1);

        try {
            $mimeType = $rc->extensionToContentType($ext);
            $modelType = $rc->getModelTypeFromMimeType($mimeType);

            $resource->setTypeUniqueName($modelType);
            $resource->setStatusCreation(1);
            $resource->setStatusDeletion(1);
            $resource->setFileMimeType($mimeType);
            $resource->setUserId($bnsUserId);

            $resource->setIsPrivate($isPrivate);
            $resource->save();

            //Rappatriement
            $importedFile = 0;
            if ($oldFileSystem->has($fileName)) {
                // TODO check file dirty char
                $fileContent = $oldFileSystem->read($fileName);
                $rc->setObject($resource);
                $rc->writeFile($resource->getFilePath(), $fileContent);
                $importedFile = 1;
            }

            if ($resource) {
                $resource->linkLabel('user', $userLabel->getId(), true);

                if ('Blog' == $objectClass) {
                    $blog = BlogQuery::create()->findPk($objectId);
                    if ($blog) {
                        $blog->setAvatarResourceId($resource->getId());
                        $blog->save();
                    }
                } elseif ('MiniSite' == $objectClass) {
                    $minisite = MiniSiteQuery::create()->findPk($objectId);
                    if ($minisite) {
                        $minisite->setBannerResourceId($resource->getId());
                        $minisite->save();
                    }
                } else {
                    $resourceJoinObject = new ResourceJoinObject();
                    $resourceJoinObject->setObjectId($objectId);
                    $resourceJoinObject->setObjectClass($objectClass);
                    $resourceJoinObject->setResource($resource);
                    $resourceJoinObject->save();
                }

                if ($this->saveImported($objectId, 'resource_' . $objectClass, $resource->getId(), 'Resource', array(
                        'file_key' => $fileName,
                        'imported' => $importedFile,
                        $customKey
                ))) {
                    $this->log('import resource : ' . $objectClass . ' ' . $objectId);

                    return true;
                } else {
                    return false;
                }
            } else {
                $this->log('Erreur import resource : ' . $objectClass . ' ' . $objectId . ' erreur lors de la creation');
            }
        } catch (\Exception $e) {
            $this->log('Erreur import resource : ' . $objectClass . ' ' . $objectId . ' erreur lors de la creation : ' . $e->getMessage() . ' -- ' . $e->getTraceAsString());
        }

        return false;
    }

    protected function getTeacherId($importedAlbum, $groupId)
    {
        $data = $importedAlbum->getData();
        if (!isset($data['teacher'])) {
            $teachers = $this->get('bns.group_manager')
                ->setGroupById($groupId)
                ->getUsersByRoleUniqueName('TEACHER');

            if (0 == count($teachers)) {
                $teachers = $this->get('bns.group_manager')
                    ->setGroupById($groupId)
                    ->getUsersByRoleUniqueName($this->getGroupType('DIRECTOR')->getType());
            }
            if (count($teachers) > 0) {
                $teacher = reset($teachers);
                $data['teacher'] = $teacher['id'];
                $importedAlbum->setData($data);
                $importedAlbum->save();
            } else {
                $data['teacher'] = false;
            }
        }

        return $data['teacher'];
    }

    protected function getFilterRne()
    {
        $res = false;
        if ($res = $this->input->getOption('filterRNE')) {
            if ("true" == $res) {
                $res = "'0660329Z','0660357E','0660793D','0660367R','0660276S','0110888F','0300702A'";
            }
        }

        return $res;
    }

    protected function getFilterPupil()
    {
        if (null === $this->filterPupilIds) {
            $con = \Propel::getMasterConnection('import');

            $sql = sprintf('SELECT b2u.bu_id
                    FROM kernel_bu_eleve_affectation a
                    INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = a.eleve AND b2u.bu_type = "USER_ELE"
                    WHERE a.current = 1 AND annee_scol = "2012" AND a.classe IN (
                        SELECT c.id FROM kernel_bu_ecole_classe c
                        WHERE annee_scol = 2012 AND is_supprimee = 0 AND c.ecole IN (
                            SELECT numero FROM `kernel_bu_ecole` WHERE RNE IN (%s)
                        )
                    )
                    ORDER BY classe ASC, user_id ASC', $this->getFilterRne());
            $stmt = $con->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->filterPupilIds[] = $row['bu_id'];
            }
        }

        return $this->filterPupilIds;
    }

    protected function getFilterParent()
    {
        if (null === $this->filterParentIds) {
            $con = \Propel::getMasterConnection('import');
            $sql = sprintf('SELECT distinct id_responsable FROM `kernel_bu_responsables` WHERE id_beneficiaire IN (
                    SELECT b2u.bu_id
                    FROM kernel_bu_eleve_affectation a
                    INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = a.eleve AND b2u.bu_type = "USER_ELE"
                    WHERE a.current = 1 AND annee_scol = "2012" AND a.classe IN (
                        SELECT c.id FROM kernel_bu_ecole_classe c
                        WHERE annee_scol = 2012 AND is_supprimee = 0 AND c.ecole IN (
                            SELECT numero FROM `kernel_bu_ecole` WHERE RNE IN (%s)
                        )
                    )
                )', $this->getFilterRne());
            $stmt = $con->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->filterParentIds[] = $row['id_responsable'];
            }
        }

        return $this->filterParentIds;
    }

    protected function getFilterUser()
    {
        if (null === $this->filterUserIds) {
            $con = \Propel::getMasterConnection('import');
            $sql = sprintf('SELECT distinct id_per
                    FROM `kernel_bu_personnel_entite` p
                    WHERE type_ref = "ECOLE" AND reference IN (
                        SELECT numero FROM `kernel_bu_ecole` WHERE RNE IN (%s)
                    )', $this->getFilterRne());
            $stmt = $con->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->filterUserIds[] = $row['id_per'];
            }
        }

        return $this->filterUserIds;
    }

    protected function parseDateTime($dateString, $timeString)
    {
        $date = substr($dateString, 0, 4) . '-' . substr($dateString, 4, 2) . '-' . substr($dateString, 6, 2);
        $timeString = trim($timeString);
        $time = empty($timeString)? '' : substr($timeString,0,2) . ':' . substr($timeString, 2, 2);

        if (!empty($time)) {
            $date .= ' ' . $time;
        }

        try {
            $date = str_replace('o', '0', $date);
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            $date = null;
        }

        return $date;
    }

}

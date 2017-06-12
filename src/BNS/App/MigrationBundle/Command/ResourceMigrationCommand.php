<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\ResourceBundle\Model\ResourcePeer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\Resource;
use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use BNS\App\MigrationBundle\Model\MigrationIconitoQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceQuery;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ResourceMigrationCommand extends BaseMigrationCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:resource')
            ->setDescription('Import des classeurs iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        \Propel::disableInstancePooling();

        switch ($this->input->getArgument('step')) {
            default:
            case 'album':
                $output->writeln('<info>Debut</info> migration des <info>albums</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'album'), $this->importAlbum()));
                $this->end();
            case 'albumFolder':
                $output->writeln('<info>Debut</info> migration des <info>album dossier</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'album dossier'), $this->importAlbumFolder()));
                $this->end();
            case 'albumPhoto':
                $output->writeln('<info>Debut</info> migration des <info>photos</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'photo'), $this->importAlbumPhoto()));
                $this->end();
            case 'classeur':
                $output->writeln('<info>Debut</info> migration des <info>classeur</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'classeur'), $this->importClasseur()));
                $this->end();
            case 'classeurFolder':
                $output->writeln('<info>Debut</info> migration des <info>dossier de classeur</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'classeur dossier'), $this->importClasseurFolder()));
                $this->end();
            case 'classeurFolderUser':
                $output->writeln('<info>Debut</info> migration des <info>dossier de classeur utilisateur</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'classeur dossier utilisateur'), $this->importClasseurFolderUser()));
                $this->end();
            case 'classeurFile':
                $output->writeln('<info>Debut</info> migration des <info>classeur fichier</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'classeur fichier'), $this->importClasseurFile()));
                $this->end();
                break;
            case 'fixClasseurFile':
                $output->writeln('<info>Debut</info> correction migration des <info>classeur fichier</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'fix classeur fichier'), $this->fixClasseurFile()));
                $this->end();
    }
    }


    protected function importAlbum()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = 'SELECT a.*, kme.node_type, kme.node_id
                FROM module_album_albums a
                INNER JOIN kernel_mod_enabled kme ON a.id = kme.module_id AND kme.module_type = "MOD_ALBUM"';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'album est déjà importé
            if ($this->isImported($row['id'], 'module_album_albums')) {
                $this->log('album deja importe : ' . $row['id'] . ' ' . $row['nom']);
                $ignore++;
                continue;
            }

            // group non importe
            if (!($importedGroup = $this->getImportedGroup($row['node_id'], $row['node_type'])) || !GroupQuery::create()->findPk($importedGroup->getBnsKey())) {
                $this->log('Erreur group non importe : ' . $row['node_type'] . ' ' . $row['node_id']);
                $error++;
                continue;
            }


            $groupLabel = ResourceLabelGroupQuery::create()->filterByGroupId($importedGroup->getBnsKey())->filterByTreeLevel(0)->findOne();
            if (!$groupLabel) {
                $group = GroupQuery::create()->findPk($importedGroup->getBnsKey());
                
                $groupLabel = new ResourceLabelGroup();
                $groupLabel->setGroupId($group->getId());
                $groupLabel->setLabel($group->getLabel());
                $groupLabel->setIsUserFolder(false);
                $groupLabel->makeRoot();
                $groupLabel->save();
            }

            $newFolder = new ResourceLabelGroup();
            $newFolder->setLabel('Album photo : ' . $row['nom']);
            $newFolder->insertAsLastChildOf($groupLabel);
            $newFolder->setGroupId($importedGroup->getBnsKey());
            $newFolder->setIsUserFolder(false);
            $newFolder->save();


            if ($newFolder) {
                if ($this->saveImported($row['id'], 'module_album_albums', $newFolder->getId(), 'ResourceLabelGroup', array('folder_key' => $row['id'] . '_' . $row['cle']))) {
                    $this->log('import album : ' . $row['nom']);

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'album ' . $row['id'] . ' ' .$row['nom']);
                }
            } else {
                $this->log('Erreur album non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importAlbumFolder()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = 'SELECT *
                FROM module_album_dossiers a
                ORDER BY id_parent ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'album est déjà importé
            if ($this->isImported($row['id'], 'module_album_dossiers')) {
                $this->log('dossier deja importe : ' . $row['id'] . ' ' . $row['nom']);
                $ignore++;
                continue;
            }

            if (0 == $row['id_parent']) {
                // group non importe
                if (!($importedFolder = $this->getImported($row['id_album'], 'module_album_albums'))) {
                    $this->log('Erreur Album non importe : ' . $row['id_album']);
                    $error++;
                    continue;
                }
            } else {
                // group non importe
                if (!($importedFolder = $this->getImported($row['id_parent'], 'module_album_dossiers'))) {
                    $this->log('Erreur Album dossier non importe : ' . $row['id_parent']);
                    $error++;
                    continue;
                }
            }

            $groupLabel = ResourceLabelGroupQuery::create()->findPk($importedFolder->getBnsKey());

            $newFolder = new ResourceLabelGroup();
            $newFolder->setLabel($row['nom']);
            $newFolder->insertAsLastChildOf($groupLabel);
            $newFolder->setGroupId($groupLabel->getGroupId());
            $newFolder->setIsUserFolder(false);
            $newFolder->save();


            if ($newFolder) {
                if ($this->saveImported($row['id'], 'module_album_dossiers', $newFolder->getId(), 'ResourceLabelGroup', array('folder_key' => $row['id'] . '_' . $row['cle']))) {
                    $this->log('import album dossier : ' . $row['nom']);

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'album dossier ' . $row['id'] . ' ' .$row['nom']);
                }
            } else {
                $this->log('album dossier non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importAlbumPhoto()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $rm = $this->get('bns.resource_manager');
        $rc = $this->get('bns.resource_creator');

        // read local file
        $oldFileSystemAdapter = new Local($this->input->getOption('albumFolder'));
        $oldFileSystem = new Filesystem($oldFileSystemAdapter);

        $albumIds = MigrationIconitoQuery::create()
                        ->filterByEnvironment($this->getEnvironmentName())
                        ->filterByOriginClass('module_album_albums')
                        ->select('OriginKey')
                        ->find();

        //query get school
        $sql = sprintf('SELECT *
                FROM module_album_photos
                WHERE id_album IN (%s)', implode(',', $albumIds->getArrayCopy()));
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si la photo est déjà importé
            if ($this->isImported($row['id'], 'module_album_photos')) {
                $this->log('photo deja importe : ' . $row['id'] . ' ' . $row['nom']);
                $ignore++;
                continue;
            }

            // group non importe
            if (!($importedAlbum = $this->getImported($row['id_album'], 'module_album_albums'))) {
                $this->log('Erreur Album non importe : ' . $row['id_album']);
                $error++;
                continue;
            }

            if (0 == $row['id_dossier']) {
                $importedFolder = $importedAlbum;
            } else {
                // group non importe
                if (!($importedFolder = $this->getImported($row['id_dossier'], 'module_album_dossiers'))) {
                    $this->log('Erreur Album dossier non importe : ' . $row['id_dossier']);
                    $error++;
                    continue;
                }
            }

            $groupLabel = ResourceLabelGroupQuery::create()->findPk($importedFolder->getBnsKey());

            if (!($teacherId = $this->getTeacherId($importedAlbum, $groupLabel->getGroupId()))) {
                $this->log('Erreur Album photo non importe : ' . $row['id_dossier'] . ' aucun enseignant ou directeur');
                $error++;
                continue;
            }

            $resource = new Resource();
            $resource->setLabel($row['nom']);
            $resource->setDescription($row['commentaire']);
            $resource->setLang('fr');
            $resource->setFilename($row['nom']);

            try {
                $mimeType = $rc->extensionToContentType($row['ext']);
                $modelType = $rc->getModelTypeFromMimeType($mimeType);

                $resource->setTypeUniqueName($modelType);
                $resource->setStatusCreation(1);
                $resource->setStatusDeletion(1);
                $resource->setFileMimeType($mimeType);
                $resource->setUserId($teacherId);

                $resource->setIsPrivate(false);
                $resource->save();

                //Rappatriement
                $data = $importedAlbum->getData();
                $key = $data['folder_key'] . '/' . $row['id'] . '_' . $row['cle'] . '.' . $row['ext'];

                $importedFile = 0;
                if ($oldFileSystem->has($key)) {
                    $fileContent = $oldFileSystem->read($key);
                    $rc->setObject($resource);
                    $rc->writeFile($resource->getFilePath(), $fileContent);
                    $importedFile = 1;
                }
                /*
                if ($rm->isThumbnailable($newFile)) {
                    $rc->createThumbs();
                }
                */
                $resource->linkLabel('group', $importedFolder->getBnsKey(), true);

                if ($resource) {
                    if ($this->saveImported($row['id'], 'module_album_photos', $resource->getId(), 'Resource', array(
                            'photo_key' => $row['id'] . '_' . $row['cle'],
                            'folder_key' => $data['folder_key']),
                            $importedFile
                            )) {
                        $this->log('import album photo : ' . $row['nom']);

                        $success++;
                    } else {
                        throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'album photo ' . $row['id'] . ' ' .$row['nom']);
                    }
                } else {
                    $this->log('album photo non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation');
                    $error++;
                }
            } catch (\Exception $e) {
                $this->log('album photo non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation : ' . $e->getMessage() . ' -- ' . $e->getTraceAsString());
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importClasseur()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $importedGroups = MigrationIconitoQuery::create()
            ->filterByEnvironment($this->getEnvironmentName())
            ->filterByBnsClass('Group')
            ->select('OriginKey')
            ->find()->getArrayCopy();

        //query get school
        $sql = sprintf('SELECT c.*, kme.node_type, kme.node_id
                FROM module_classeur c
                INNER JOIN kernel_mod_enabled kme ON c.id = kme.module_id
                    AND kme.module_type = "MOD_CLASSEUR"
                    AND kme.node_type IN ("BU_CLASSE", "BU_ECOLE", "BU_VILLE", "CLUB")
                WHERE node_id IN (%s)', implode(',', $importedGroups));
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si le classeur est déjà importé
            if ($this->isImported($row['id'], 'module_classeur')) {
                $this->log('classeur deja importe : ' . $row['id'] . ' ' . $row['titre']);
                $ignore++;
                continue;
            }

            // group non importe
            if (!($importedGroup = $this->getImportedGroup($row['node_id'], $row['node_type']))) {
                $this->log('Erreur groupe non importe : ' . $row['node_type'] . ' ' . $row['node_id']);
                $error++;
                continue;
            }

            $groupLabel = ResourceLabelGroupQuery::create()->filterByGroupId($importedGroup->getBnsKey())->filterByTreeLevel(0)->findOne();
            if (!$groupLabel) {
                $group = GroupQuery::create()->findPk($importedGroup->getBnsKey());
                $groupLabel = new ResourceLabelGroup();
                $groupLabel->setGroupId($group->getId());
                $groupLabel->setLabel($group->getLabel());
                $groupLabel->setIsUserFolder(false);
                $groupLabel->makeRoot();
                $groupLabel->save();
            }

            $newFolder = new ResourceLabelGroup();
            $newFolder->setLabel('classeur : ' . $row['titre']);
            $newFolder->insertAsLastChildOf($groupLabel);
            $newFolder->setGroupId($importedGroup->getBnsKey());
            $newFolder->setIsUserFolder(false);
            $newFolder->save();

            if ($newFolder) {
                if ($this->saveImported($row['id'], 'module_classeur', $newFolder->getId(), 'ResourceLabelGroup', array('folder_key' => $row['id'] . '_' . $row['cle']))) {
                    $this->log('import classeur : ' . $row['titre']);

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le classeur ' . $row['id'] . ' ' .$row['titre']);
                }
            } else {
                $this->log('Erreur classeur non importe : ' . $row['id'] . ' ' .$row['titre'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }


    protected function importClasseurFolder()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = 'SELECT d.*
                FROM module_classeur_dossier d
                INNER JOIN kernel_mod_enabled kme ON d.module_classeur_id = kme.module_id
                    AND kme.module_type = "MOD_CLASSEUR"
                    AND kme.node_type IN ("BU_CLASSE", "BU_ECOLE", "BU_VILLE", "CLUB")
                ORDER BY parent_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si le dossier du classeur est déjà importé
            if ($this->isImported($row['id'], 'module_classeur_dossier', 'group')) {
                $this->log('classeur dossier deja importe : ' . $row['id'] . ' ' . $row['nom']);
                $ignore++;
                continue;
            }

            // classeur non importe
            if (!($importedClasseur = $this->getImported($row['module_classeur_id'], 'module_classeur'))) {
                $this->log('Erreur classeur non importe : ' . $row['module_classeur_id']);
                $error++;
                continue;
            }
            if (0 == $row['parent_id']) {
                $importedFolder = $importedClasseur;
            } else {
                // groupe non importe
                if (!($importedFolder = $this->getImported($row['parent_id'], 'module_classeur_dossier'))) {
                    $this->log('Erreur classeur dossier non importe : ' . $row['parent_id']);
                    $error++;
                    continue;
                }
            }

            $groupLabel = ResourceLabelGroupQuery::create()->findPk($importedFolder->getBnsKey());

            $newFolder = new ResourceLabelGroup();
            $newFolder->setLabel($row['nom']);
            $newFolder->insertAsLastChildOf($groupLabel);
            $newFolder->setGroupId($groupLabel->getGroupId());
            $newFolder->setIsUserFolder(false);
            $newFolder->save();

            if ($newFolder) {
                if ($this->saveImported($row['id'], 'module_classeur_dossier', $newFolder->getId(), 'ResourceLabelGroup',
                        array(
                                'folder_key' => $row['id'] . '_' . $row['cle'],
                                'casier' => $row['casier'],
                        ),
                        'group'
                        )
                ) {
                    $this->log('import classeur dossier : ' . $row['nom']);

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le classeur dossier ' . $row['id'] . ' ' .$row['nom']);
                }
            } else {
                $this->log('Erreur classeur dossier non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importClasseurFolderUser()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        //query get classeur folder for user
        $sql = 'SELECT d.*, kme.*, u.user_id as "real_user_id", c.cle as "classeur_key"
                FROM module_classeur_dossier d
                INNER JOIN module_classeur c ON c.id = d.module_classeur_id
                INNER JOIN kernel_mod_enabled kme ON d.module_classeur_id = kme.module_id
                    AND kme.module_type = "MOD_CLASSEUR"
                    AND kme.node_type LIKE "USER_%"
                INNER JOIN kernel_link_bu2user u ON u.bu_id = kme.node_id AND u.bu_type = kme.node_type
                ORDER BY parent_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si le dossier du classeur est déjà importé
            if ($this->isImported($row['id'], 'module_classeur_dossier', 'user')) {
                $this->log('classeur dossier user deja importe : ' . $row['id'] . ' ' . $row['nom']);
                $ignore++;
                continue;
            }

            if (!($importedUser = $this->getImported($row['real_user_id'], 'dbuser'))) {
                $this->log('Erreur : classeur dossier utilisateur non importe, car user non importe : ' . $row['real_user_id']);
                $error++;
                continue;
            }

            if (0 == $row['parent_id']) {
                $importedFolder = null;
            } else {
                // groupe non importe
                if (!($importedFolder = $this->getImported($row['parent_id'], 'module_classeur_dossier', 'user'))) {
                    $this->log('Erreur classeur dossier non importe : ' . $row['parent_id']);
                    $error++;
                    continue;
                }
            }

            if ($importedFolder) {
                $userLabel = ResourceLabelUserQuery::create()->findPk($importedFolder->getBnsKey());
            } else {
                $userLabel = ResourceLabelUserQuery::create()->filterByUserId($importedUser->getBnsKey())->filterByTreeLevel(0)->findOne();
                if (!$userLabel) {
                    $user = UserQuery::create()->findPk($importedUser->getBnsKey());
                    $userLabel = new ResourceLabelUser();
                    $userLabel->setLabel($user->getFullName());
                    $userLabel->setUserId($user->getId());
                    $userLabel->makeRoot();
                    $userLabel->save();
                }
            }

            $newFolder = new ResourceLabelUser();
            $newFolder->setLabel($row['nom']);
            $newFolder->insertAsLastChildOf($userLabel);
            $newFolder->setUserId($userLabel->getUserId());
            $newFolder->save();

            if ($newFolder) {
                if ($this->saveImported($row['id'], 'module_classeur_dossier', $newFolder->getId(), 'ResourceLabelUser',
                        array(
                                'folder_key' => $row['id'] . '_' . $row['cle'],
                                'classeur_key' => $row['module_classeur_id'] . '_' . $row['classeur_key'],
                                'casier' => $row['casier'],
                        ),
                        'user'
                        )) {
                    $this->log('import classeur dossier user : ' . $row['nom']);

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le classeur dossier utilisateur' . $row['id'] . ' ' .$row['nom']);
                }
            } else {
                $this->log('classeur dossier utilisateur non importe : ' . $row['id'] . ' ' .$row['nom'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importClasseurFile()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $rm = $this->get('bns.resource_manager');
        $rc = $this->get('bns.resource_creator');

        // read local file
        $oldFileSystemAdapter = new Local($this->input->getOption('classeurFolder'));
        $oldFileSystem = new Filesystem($oldFileSystemAdapter);

        //query get school
        $sql = 'SELECT f.*, c.cle as "classeur_key", u.user_id as "real_user_id", kme.node_type as "classeur_node_type", kme.node_id as "node_user_id"
                FROM module_classeur_fichier f
                INNER JOIN module_classeur c ON c.id = f.module_classeur_id
                INNER JOIN kernel_mod_enabled kme ON f.module_classeur_id = kme.module_id AND kme.module_type = "MOD_CLASSEUR"
                LEFT JOIN kernel_link_bu2user u ON u.bu_id = f.user_id AND u.bu_type = f.user_type';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si le fichier est déjà importé
            if ($this->isImported($row['id'], 'module_classeur_fichier')) {
                $this->log('fichier deja importe : ' . $row['id'] . ' ' . $row['titre']);
                $ignore++;
                continue;
            }

            $isUser = (false !== strpos($row['classeur_node_type'], 'USER_'));

            // classeur non importe
            if (!($importedClasseur = $this->getImported($row['module_classeur_id'], 'module_classeur')) && !$isUser) {
                $this->log('Erreur classeur non importe : ' . $row['module_classeur_id']);
                $error++;
                continue;
            }

            if (0 == $row['module_classeur_dossier_id']) {
                $importedFolder = $importedClasseur;
            } else {
                // group non importe
                if (!($importedFolder = $this->getImported($row['module_classeur_dossier_id'], 'module_classeur_dossier'))) {
                    $this->log('Erreur classeur dossier non importe : ' . $row['module_classeur_dossier_id']);
                    $error++;
                    continue;
                }
            }

            $type = 'user';
            $importedFolderId = null;
            if ($isUser) {
                $importedUser = $this->getImported($row['real_user_id'], 'dbuser');
            } else {
                // can't work node_user_id is the group_id
                //$importedUser = $this->getImported($row['node_user_id'], 'dbuser');
                $importedUser = null;
            }
            $userId = $importedUser? $importedUser->getBnsKey(): null;
            if ($importedFolder && 'user' != $importedFolder->getOriginCustomKey()) {
                $type = 'group';
                $importedFolderId = $importedFolder->getBnsKey();
                $groupLabel = ResourceLabelGroupQuery::create()->findPk($importedFolderId);

                if (!$userId && !($userId = $this->getTeacherId($importedFolder, $groupLabel->getGroupId()))) {
                    $this->log('Erreur classeur fichier non importe : ' . $groupLabel->getGroupId() . ' aucun enseignant ou directeur');
                    $error++;
                    continue;
                }
            } else {
                if (!$userId) {
                    $this->log('Erreur classeur fichier non importe : (node_user_id: ' . $row['node_user_id'] . ', real_user_id: ' . $row['real_user_id'] . ') aucun utilisateur');
                    $error++;
                    continue;
                }
                if (null === $importedFolder) {
                    $userLabel = ResourceLabelUserQuery::create()->filterByUserId($userId)->filterByTreeLevel(0)->findOne();
                    if (!$userLabel) {
                        $user = UserQuery::create()->findPk($userId);
                        $userLabel = new ResourceLabelUser();
                        $userLabel->setLabel($user->getFullName());
                        $userLabel->setUserId($user->getId());
                        $userLabel->makeRoot();
                        $userLabel->save();
                    }
                    $importedFolderId = $userLabel->getId();
                }
            }

            if (!$importedFolderId) {
                $this->log('Erreur classeur fichier non importe, dossier utilisateur manquant :' . var_export($row, true));
                $error++;
                continue;
            }

            $resource = new Resource();
            $resource->setLabel($row['titre']);
            $resource->setDescription($row['commentaire']);
            $resource->setLang('fr');
            $resource->setCreatedAt($row['date_upload']);
            $resource->setStatusCreation(1);
            $resource->setStatusDeletion(1);
            $resource->setUserId($userId);
            $resource->setIsPrivate(true);

            // clé du fichier sur l'environnement iconito
            try {
                $key = $row['module_classeur_id'] . '-' . $row['classeur_key'] . '/' . $row['id'] . '-' . $row['cle'] . '.';
                if (in_array(strtolower($row['type']), array('web', 'favori'))) {
                    $key .= 'web';
                    $resource->setFileMimeType('NONE');
                    $resource->setTypeUniqueName(ResourcePeer::TYPE_UNIQUE_NAME_LINK);
                } else {
                    $key .= strtolower($row['type']);
                    $mimeType = $rc->extensionToContentType(strtolower($row['type']));
                    $modelType = $rc->getModelTypeFromMimeType($mimeType);

                    $resource->setTypeUniqueName($modelType);
                    $resource->setFilename($row['fichier']);
                    $resource->setSize($row['taille']);
                    $resource->setFileMimeType($mimeType);
                }
                $resource->save();

                $importedFile = 0;
                if ($oldFileSystem->has($key)) {
                    $fileContent = $oldFileSystem->read($key);
                    if (in_array(strtolower($row['type']), array('web', 'favori'))) {
                        $url = '';
                        $datas = explode("\n", $fileContent);
                        foreach ($datas as $data) {
                            if (false !== strpos($data, 'BASEURL=')) {
                                $url = trim(substr($data, strpos($data, '=') + 1));
                                break;
                            }
                        }
                        if (!empty($url)) {
                            if ($rc->isValidUrl($url)) {
                                $resource->setFileName($resource->getSlug() . '.jpg');
                                try {
                                    $imageName = $rc->getImageUrlFromLinkUrl($url);
                                    $rc->writeFile($resource->getFilePath(),  file_get_contents($rc->getUploadFileDir . '/../' . $imageName));
                                } catch(\Exception $exception) {
                                    // On ne fait rien
                                }

                                $resource->setValue($url);
                                $resource->save();
                            }
                        }

                        $importedFile = 1;
                    } else {
                        $rc->setObject($resource);
                        $rc->writeFile($resource->getFilePath(), $fileContent);
                        $importedFile = 1;
                    }
                } else {
                    $this->log('Import resource sans fichier, non present : ' . $key);
                }
                /*
                 if ($rm->isThumbnailable($newFile)) {
                $rc->createThumbs();
                }
                */

                $resource->linkLabel($type, $importedFolderId, true);

                if ($resource) {
                    if ($this->saveImported($row['id'], 'module_classeur_fichier', $resource->getId(), 'Resource',
                            array(
                                'file_key' => $row['id'] . '-' . $row['cle'] . '.' . strtolower($row['type']),
                                'folder_key' => $row['module_classeur_id'] . '-' . $row['classeur_key']
                            ),
                            $importedFile
                    )) {
                        $this->log('import classeur fichier : ' . $row['id']);

                        $success++;
                    } else {
                        throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le fichier ' . $row['id']);
                    }
                } else {
                    $this->log('Erreur classeur fichier non importe : ' . $row['id'] . ' erreur lors de la creation');
                    $error++;
                }
            } catch (\Exception $e) {
                $this->log('Erreur classeur fichier non importe : ' . $row['id'] . ' erreur lors de la creation : ' . $e->getMessage() . ' -- ' . $e->getTraceAsString());
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function fixClasseurFile()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $rc = $this->get('bns.resource_creator');

        // read local file
        $oldFileSystemAdapter = new Local($this->input->getOption('classeurFolder'));
        $oldFileSystem = new Filesystem($oldFileSystemAdapter);

        $importResources = MigrationIconitoQuery::create()
            ->filterByBnsClass('Resource')
            ->filterByOriginCustomKey(0)
            ->filterByEnvironment($this->getEnvironmentName())
            ->find();

        foreach ($importResources as $importedResource) {
            $total++;

            $resource = ResourceQuery::create()->findPk($importedResource->getBnsKey());
            if ($resource) {
                //Rappatriement
                $data = $importedResource->getData();
                $folder = $data['folder_key'];
                $file = explode('.', $data['file_key']);

                $key = $folder . '/' . $file[0] . '.' . strtolower($file[1]);

                if ($oldFileSystem->has($key)) {
                    $fileContent = $oldFileSystem->read($key);
                    $rc->setObject($resource);
                    $rc->writeFile($resource->getFilePath(), $fileContent);

                    $importedResource->setOriginCustomKey(1);
                    $importedResource->save();

                    $success++;
                } else {
                    $this->log('Import resource sans fichier, non present : ' . $key);
                    $error++;
                }
            }
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

}

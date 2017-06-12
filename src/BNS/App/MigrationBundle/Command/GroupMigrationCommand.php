<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class GroupMigrationCommand extends BaseMigrationCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:group')
            ->setDescription('Import des groupes iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        switch ($input->getArgument('step')) {
            default:
//            case 'cityGroup':
//                $output->writeln('<info>Debut</info> migration des <info>groupes</info> : ' . date('d/m/Y H:i:s'));
//                $this->displayResult(array_merge(array('label' => 'groupe de ville'), $this->importCityGroup()));
//                $this->end();
//
//            case 'city':
//                $output->writeln('<info>Debut</info> migration des <info>villes</info> : ' . date('d/m/Y H:i:s'));
//                $this->displayResult(array_merge(array('label' => 'ville'), $this->importCity()));
//                $this->end();

            case 'school':
                $output->writeln('<info>Debut</info> migration des <info>ecoles</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'ecole'), $this->importSchool()));
                $this->end();

            case 'classroom':
                $output->writeln('<info>Debut</info> migration des <info>classes</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'classe'), $this->importClassroom()));
                $this->end();

            case 'group':
                $output->writeln('<info>Debut</info> migration des <info>groupes</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'groupe'), $this->importGroup()));
                $this->end();
        }
    }

    protected function importCityGroup()
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;

        $groupManager = $this->getContainer()->get('bns.group_manager');
        $domaineId = $this->input->getOption('domainId');
        $environmentId = $this->getEnvironment()? $this->getEnvironment()->getId() : null;

        if (!$environmentId) {
            throw new \Exception('environnement invalide : ' . $this->getEnvironmentName());
        }

        $con = \Propel::getMasterConnection('import');

        //query get city group
        $sql = 'SELECT * FROM kernel_bu_groupe_villes';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //check if it was already loaded
            if (!$this->isImported($row['id_grv'], 'kernel_bu_groupe_villes')) {
                // import it
                $group = $groupManager->createSubgroupForGroup(array(
                        'label'     => $row['nom_groupe'],
                        'domain_id' => $domaineId,
                        'validated' => true,
                        'type'      => $this->getGroupType('CITY_GROUP')? $this->getGroupType('CITY_GROUP')->getType() : null,
                ), $environmentId, true);

                if ($group) {
                    if ($this->saveImported($row['id_grv'], 'kernel_bu_groupe_villes', $group->getId(), 'Group')) {
                        $this->log('import groupe : ' . $row['nom_groupe']);
                        $success++;
                    } else {
                        throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le groupe ' . $row['nom_groupe']);
                    }
                } else {
                    $this->log('groupe non importe : ' . $row['nom_groupe'] . ' erreur lors de la creation');
                    $error++;
                }

                // join to environement
            } else {
                $this->log('groupe deja importe : ' . $row['nom_groupe']);
                $ignore++;
            }

            $total++;
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importCity()
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;

        $groupManager = $this->getContainer()->get('bns.group_manager');
        $domaineId = $this->input->getOption('domainId');

        $con = \Propel::getMasterConnection('import');

        //query get city
        $sql = 'SELECT * FROM kernel_bu_ville';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!($importedGroup = $this->getImported($row['id_grville'], 'kernel_bu_groupe_villes'))) {
                $this->log('ville non importe : ' . $row['nom'] . ' groupe de ville non importe :' . $row['id_grville']);
                $error++;
                $total++;
                continue;
            }

            //check if it was already loaded
            if (!$this->isImported($row['id_vi'], 'kernel_bu_ville')) {
                // import it
                $group = $groupManager->createSubgroupForGroup(array(
                        'label'            => $row['nom'],
                        'domain_id'        => $domaineId,
                        'validated'        => true,
                        'type'             => $this->getGroupType('CITY')? $this->getGroupType('CITY')->getType() : null,
                ), $importedGroup->getBnsKey(), true);

                if ($group) {
                    if ($this->saveImported($row['id_vi'], 'kernel_bu_ville', $group->getId(), 'Group')) {
                        $this->log('import ville : ' . $row['nom']);
                        $success++;
                    } else {
                        throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour la ville ' . $row['nom']);
                    }
                } else {
                    $this->log('ville non importe : ' . $row['nom'] . ' erreur lors de la creation');
                    $error++;
                }
                // join to environement
            } else {
                $this->log('ville deja importe : ' . $row['nom']);
                $ignore++;
            }
            $total++;
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importSchool()
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;

        $groupTypeSchool = $this->getGroupType('SCHOOL');
        $this->iniGroupTypeData('PHONE', array('fr' => 'Téléphone', 'en' => 'Phone'));
        $this->iniGroupTypeData('SCHOOL_TYPE', array('fr' => "Type d'école", 'en' => 'School type'));
        $groupTypeSchool->addGroupTypeDataByUniqueName('PHONE');
        $groupTypeSchool->addGroupTypeDataByUniqueName('SCHOOL_TYPE');

        $groupManager = $this->getContainer()->get('bns.group_manager');
        $domaineId = $this->input->getOption('domainId');

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = 'SELECT * FROM kernel_bu_ecole';
        if ($rne = $this->getFilterRne()) {
            $sql .= " WHERE RNE IN (" . $rne . ")";
        }

        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
         /*   if (!($importedCity = $this->getImported($row['id_ville'], 'kernel_bu_ville'))) {
                $this->log('Ecole non importe : ' . $row['nom'] . ' car ville non importe :' . $row['id_ville']);
                $error++;
                continue;
            }
        */
            // vérif si l'école est déjà importé
            if ($this->isImported($row['numero'], 'kernel_bu_ecole')) {
                $this->log('Ecole deja importe : ' . $row['nom']);
                $ignore++;
                continue;
            }
        /*
            // on créé l'école
            $group = $groupManager->createSubgroupForGroup(array(
                    'label'            => $row['nom'],
                    'domain_id'        => $domaineId,
                    'validated'        => true,
                    'type'             => 'SCHOOL',
            ), $importedCity->getBnsKey(), true);
        */


        //POUR Récupération
        $group = GroupQuery::create()->filterBySingleAttribute('UAI',$row['RNE'])->findOne();

            if ($group) {
                if ($this->saveImported($row['numero'], 'kernel_bu_ecole', $group->getId(), 'Group')) {
                    $this->log('Récupération ecole : ' . $row['nom']);
/*
                    $group->setAttribute('NAME', $row['nom']);
                    // ajout des attributs
                    if ($row['adresse1']) {
                        $group->setAttribute('ADDRESS', $row['adresse1'] . ($row['adresse2']? ' ' . $row['adresse2'] :''));
                    }
                    if ($row['code_postal']) {
                        $group->setAttribute('ZIPCODE', $row['code_postal']);
                    }
                    if ($row['commune']) {
                        $group->setAttribute('CITY', $row['commune']);
                    }
                    if ($row['tel']) {
                        $group->setAttribute('PHONE', $row['tel']);
                    }
                    if ($row['mail']) {
                        $group->setAttribute('EMAIL', $row['mail']);
                    }
                    if ($row['type']) {
                        $group->setAttribute('SCHOOL_TYPE', $row['type']);
                    }
                    if ($row['RNE']) {
                        $group->setAttribute('UAI', $row['RNE']);
                    }
*/
                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'ecole ' . $row['nom']);
                }
            } else {
                $this->log('ecole non importe : ' . $row['nom'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importClassroom()
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;

        $groupManager = $this->getContainer()->get('bns.group_manager');
        $domaineId = $this->input->getOption('domainId');

        $groupTypeSchool = $this->getGroupType('CLASSROOM');
        $this->iniGroupTypeData('CLASSROOM_TYPE', array('fr' => 'Type de classe', 'en' => 'Classroom type'));
        $groupTypeSchool->addGroupTypeDataByUniqueName('CLASSROOM_TYPE');

        $con = \Propel::getMasterConnection('import');

        // query LEVEL/TYPE
        $sql = 'SELECT ec.id, cn.niveau_court, ct.type_classe FROM kernel_bu_ecole_classe_niveau ecn
                INNER JOIN kernel_bu_classe_niveau cn ON cn.id_n = ecn.niveau
                INNER JOIN kernel_bu_ecole_classe ec ON ec.id = ecn.classe AND ec.annee_scol = "2012" AND ec.is_supprimee = 0
                INNER JOIN kernel_bu_classe_type ct ON ecn.type = ct.id_tycla';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        $classroomLevel = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($classroomLevel[$row['id']])) {
                $classroomLevel[$row['id']] = array();
            }
            $classroomLevel[$row['id']]['type'][$row['type_classe']] = $row['type_classe'];
            $classroomLevel[$row['id']]['level'][$row['niveau_court']] = $row['niveau_court'];
        }

        // query get classroom
        $sql = 'SELECT * FROM kernel_bu_ecole_classe WHERE annee_scol = "2013" AND is_supprimee = 0';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $i = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            if (!($importedSchool = $this->getImported($row['ecole'], 'kernel_bu_ecole'))) {
                $this->log('Classe non importe : ' . $row['nom'] . ' car ecole non importe :' . $row['ecole']);
                $error++;
                continue;
            }

            // vérif si la classe est déjà importé
            if ($this->isImported($row['id'], 'kernel_bu_ecole_classe')) {
                $this->log('Classe deja importe : ' . $row['nom']);
                $ignore++;
                continue;
            }
/*
            // on créé la classe
            $group = $groupManager->createSubgroupForGroup(array(
                    'label'            => $row['nom'],
                    'domain_id'        => $domaineId,
                    'validated'        => true,
                    'type'             => 'CLASSROOM',
            ), $importedSchool->getBnsKey(), true);
*/
//            if ($group) {
//                if ($this->saveImported($row['id'], 'kernel_bu_ecole_classe', $group->getId(), 'Group')) {
//                    $this->log('importe classe : ' . $row['nom']);
//
//                    $group->setAttribute('NAME', $row['nom']);
//
//                    // ajout des attributs
//                    if (isset($classroomLevel[$row['id']])) {
//                        if (isset($classroomLevel[$row['id']]['level'])) {
//                            $group->setAttribute('LEVEL', $classroomLevel[$row['id']]['level']);
//                        }
//                        if (isset($classroomLevel[$row['id']]['type'])) {
//                            $group->setAttribute('CLASSROOM_TYPE', reset($classroomLevel[$row['id']]['type']));
//                        }
//                    }
//
//                    $success++;
//                } else {
//                    throw new \Exception("erreur lors de la sauvegarde de migrationIconito pour l'ecole {$row['nom']}");
//                }
//            } else {
//                $this->log('ecole non importe : ' . $row['nom'] . ' erreur lors de la creation');
//                $error++;
//            }
        }
        $stmt->closeCursor();



        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importGroup()
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;

        if ($this->getFilterRne()) {
            $this->output->writeln('<comment>skip group</comment>');
            return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
        }

        $groupManager = $this->getContainer()->get('bns.group_manager');
        $domaineId = $this->input->getOption('domainId');

        if ($this->getGroupType('CLUB') && ($groupClub = $this->getGroupClub())) {
            $groupClubId = $groupClub->getId();
        } else {
            throw new \Exception('Le groupe de clubs n\'existe pas');
        }

        $con = \Propel::getMasterConnection('import');

        // query get classroom
        $sql = 'SELECT * FROM module_groupe_groupe g';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // vérif si le groupe est déjà importé
            if ($this->isImported($row['id'], 'module_groupe_groupe')) {
                $this->log('Groupe deja importe : ' . $row['titre']);
                $ignore++;
                continue;
            }

            // on créé le groupe
            $group = $groupManager->createSubgroupForGroup(array(
                    'label'            => $row['titre'],
                    'domain_id'        => $domaineId,
                    'validated'        => true,
                    'type'             => 'CLUB',
            ), $groupClubId, true);

            if ($group) {
                if ($this->saveImported($row['id'], 'module_groupe_groupe', $group->getId(), 'Group')) {
                    $this->log('import groupe : ' . $row['titre']);

                    $group->setAttribute('NAME', $row['titre']);
                    $group->setAttribute('DESCRIPTION', $row['description']);

                    $success++;
                } else {
                    throw new \Exception("erreur lors de la sauvegarde de migrationIconito pour le groupe {$row['titre']}");
                }
            } else {
                $this->log('Groupe non importe : ' . $row['titre'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

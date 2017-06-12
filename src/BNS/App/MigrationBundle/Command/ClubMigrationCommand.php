<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 *
 * @author JÃ©rÃ©mie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ClubMigrationCommand extends BaseMigrationCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:club')
            ->setDescription('Import des club iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        switch ($input->getArgument('step')) {
            default:
            case 'club':
                $output->writeln('<info>Debut</info> migration des <info>groupes</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'Club'), $this->importClub($output)));
                $this->end();
        }
    }

    protected function importClub($output)
    {
        $success = 0;
        $ignore  = 0;
        $error   = 0;
        $total   = 0;
        $envId = 57618;

        /*$teamManager = $this->getContainer()->get('bns.team_manager');
        $partnershipManager = $this->getContainer()->get('bns.partnership_manager');*/
        $groupManager = $this->get('bns.group_manager');
        $roleManager = $this->get('bns.role_manager');
        $domaineId = $this->input->getOption('domainId');

        if (!$groupPartnership = $this->getGroupType('CLUB')) {
            throw new \Exception('Le groupe CLUB n\'existe pas');
        }

        $con = \Propel::getMasterConnection('import');

        // get teacher from club

        //On ne s'occupe plus de Ã§a
        /*
        $clubTeachers = array();
//        $sql = 'SELECT user_id, node_id FROM kernel_link_user2node WHERE node_type="CLUB" AND user_type="USER_ENS"';

        $sql = 'SELECT b2u.user_id, p.reference, p.type_ref, p.role, u2n.node_id as club_id
                FROM kernel_bu_personnel_entite p
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = p.id_per AND b2u.bu_type IN ("USER_ENS", "USER_VIL")
                INNER JOIN kernel_link_user2node u2n ON u2n.user_id = b2u.user_id AND u2n.node_type="CLUB" AND u2n.user_type=b2u.bu_type
                ORDER BY user_id ASC';

        $stmt = $con->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            switch ($row['type_ref']) {
                case 'ECOLE':
                    $originTable = 'kernel_bu_ecole';
                    break;
                case 'CLASSE':
                    $originTable = 'kernel_bu_ecole_classe';
                    break;
                default:
                    continue 2;
            }
            if (!($importedGroup = $this->getImported($row['reference'], $originTable))) {
                $this->log('Groupe non importe de ' . $originTable . ' : ' . $row['reference']);
                continue;
            }

            $clubTeachers[$row['club_id']][$row['user_id']][] = $importedGroup;
        }
        */
        // query get classroom
        $sql = 'SELECT * FROM module_groupe_groupe g';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si le groupe est déjà importé
            if ($this->isImported($row['id'], 'module_groupe_groupe')) {
                $this->log('Groupe deja importe : ' . $row['titre']);
                $clubId = $this->getImportedQuery($row['id'], 'module_groupe_groupe')->findOne()->getBnsKey();
            }else{
                //On créé dans tous les cas le group
                $club = $groupManager->createGroup(
                    array(
                        'domain_id' => $domaineId,
                        'type' => $groupPartnership->getType(),
                        'label' => $row['titre']
                    ),
                true);
                $groupManager->addParent($club->getId(),$envId);
                $output->writeln('<info>Club cree : </info>' . $row['titre']);
                $clubId = $club->getId();
            }
            /*
            if (!isset($clubTeachers[$row['id']])) {
                $this->log(sprintf('Aucun enseignant dans ce club %s %s', $row['id'], $row['titre']));
                $error++;
                continue;
            }
            */
            
            
            //Rappatriement des utilisateurs
            $sqlUser = sprintf('SELECT * FROM kernel_link_user2node WHERE node_type="CLUB" AND node_id = %s', $row['id']);
            $stmtUser = $con->prepare($sqlUser);
            $stmtUser->execute();
            while ($rowUser = $stmtUser->fetch(\PDO::FETCH_ASSOC)) {
                $idDbUser = $rowUser['user_id'];
                $sqlTrueUser = sprintf("SELECT * FROM kernel_link_bu2user WHERE bu_type='" . $rowUser['user_type'] . "' AND bu_id = %s", $idDbUser);
                $stmtTrueUser = $con->prepare($sqlTrueUser);
                $stmtTrueUser->execute();
                $trueId = false;
                while ($rowTrueUser = $stmtTrueUser->fetch(\PDO::FETCH_ASSOC)) {
                    $trueId = $rowTrueUser['user_id'];
                }
                if ($trueId) {
                    $importedUser = $this->getImported($trueId, 'dbuser');
                    if($importedUser)
                    {
                        $user = UserQuery::create()->findPk($importedUser->getBnsKey());
                        //On fait le map entre type Iconito et type BNS
                        $userRole = $this->mapUserType($rowUser['user_type']);
                        $roleManager->setGroupTypeRole($userRole);
                        $roleManager->assignRole($user,  $clubId );
                        $output->writeln('<info>User Lié : </info>' . $user->getFullName());
                    }
                }
            }
            $stmtUser->closeCursor();
            
            
            /*
            $group = null;
            if ($this->isPartnership($row['id'], $clubTeachers[$row['id']])) {
                $teacherGroup = null;
                $groupIds = array();
                foreach ($clubTeachers[$row['id']] as $importedGroups) {
                    foreach ($importedGroups as $importedGroup) {
                        if (!$group) {
                            // Handle Partnership
                            $group = $partnershipManager->createPartnership(array(
                                'label'            => $row['titre'],
                                'validated'        => true,
                                'group_creator_id' => $importedGroup->getBnsKey(),
                                'attributes'       => array(
                                    'DESCRIPTION'  => $row['description'],
                                    'HOME_MESSAGE' => 'Bienvenue dans le partenariat',
                                )
                            ));
                            $groupIds[] = $importedGroup->getBnsKey();
                        } else {
                            if (!in_array($importedGroup->getBnsKey(), $groupIds)) {
                                $centralGroup = $partnershipManager->getGroupFromCentral($group->getId());
                                $partnershipManager->joinPartnership($centralGroup['uid'], $importedGroup->getBnsKey());
                                $groupIds[] = $importedGroup->getBnsKey();
                            }
                        }
                    }
                }
            } else {
                // Handle Team
                foreach ($clubTeachers[$row['id']] as $teacherId => $importedGroups) {
                    foreach ($importedGroups as $importedGroup) {
                        if (!$group) {
                            $group = $teamManager->createTeam(array(
                                'label'           => $row['titre'],
                                'group_parent_id' => $importedGroup->getBnsKey(),
                            ));
                            break 2;
                        }
                    }
                }

                if ($group) {
                    $sqlUser = sprintf('SELECT user_id FROM kernel_link_user2node WHERE node_type="CLUB" AND node_id = %s', $row['id']);
                    $stmtUser = $con->prepare($sqlUser);
                    $stmtUser->execute();
                    while ($rowUser = $stmtUser->fetch(\PDO::FETCH_ASSOC)) {
                        if ($importedUser = $this->getImported($rowUser['user_id'], 'dbuser')) {
                            $user = UserQuery::create()->findPk($importedUser->getBnsKey());
                            if (!$user->getHighRoleId()) {
                                $teamManager->addUser($user, $this->getGroupType('EXTERNAL'));
                            } else {
                                $teamManager->addUser($user);
                            }
                        }
                    }
                    $stmtUser->closeCursor();
                }
            }
            */
            if ($clubId) {
                if ($this->saveImported($row['id'], 'module_groupe_groupe', $clubId, 'Group')) {
                    $this->log('Club importe : ' . $row['titre']);
                    $success++;
                    continue;
                }
            }

            $this->log('Groupe non importe : ' . $row['titre'] . ' erreur lors de la creation');
            $error++;
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function isPartnership($clubId, array $clubTeachersIds)
    {
        if (count($clubTeachersIds) < 2) {
            return false;
        } else {
            // TODO detect if all teachers are in the same classroom
            return true;
        }
    }
}

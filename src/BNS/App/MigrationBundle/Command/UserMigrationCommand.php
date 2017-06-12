<?php
namespace BNS\App\MigrationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class UserMigrationCommand extends BaseMigrationCommand
{
    const TEACHER  = 1;
    const DIRECTOR = 2;
    const SCHOOL_OFFICER = 3;
    const CITY_OFFICER = 4;
    const GROUP_CITY_OFFICER = 5;

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:user')
            ->setDescription('Import des groupes iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        \Propel::disableInstancePooling();

        switch ($this->input->getArgument('step')) {
            default:
            case 'user':
                $output->writeln('<info>Debut</info> migration des <info>utilisateur</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'utilisateur'), $this->importUser()));
                $this->end();

            case 'pupil':
                $output->writeln('<info>Debut</info> migration des <info>eleves</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'eleve'), $this->importPupil()));
                $this->end();

          /*  case 'parent':
                $output->writeln('<info>Debut</info> migration des <info>parents</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'parents'), $this->importUser(true)));
                $this->end();

            case 'external':
                $output->writeln('<info>Debut</info> migration des <info>utilisateurs externes</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'utilisateur externe'), $this->importExternalUser()));
                $this->end();

            case 'linkPupil':
                $output->writeln('<info>Debut</info> migration des <info>liaisons eleve classe</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'liaison eleve classe'), $this->linkedPupilToGroup()));
                $this->end();

            case 'linkParent':
                $output->writeln('<info>Debut</info> migration des <info>liaisons parent eleve</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'liaison parent eleve'), $this->linkedParentToGroup()));
                $this->end();

            case 'linkUser':
                $output->writeln('<info>Debut</info> migration des <info>liaisons utilisateur groupe</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'liaison utilisateur groupe'), $this->linkedUserToGroup()));
                $this->end();

            case 'linkExternal':
                $output->writeln('<info>Debut</info> migration des <info>liaisons utilisateur externe groupe</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'liaison utilisateur externe groupe'), $this->linkedExternalToGroup()));
                $this->end();
            
            case 'repareParent':
                $output->writeln('<info>Debut</info> reparation des comptes parents : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'Reparation des comptes parents'), $this->repareParentAccounts()));
                $this->end();*/
        }
    }


    protected function importPupil()
    {
        $success   = 0;
        $ignore    = 0;
        $duplicate = 0;
        $error     = 0;
        $total     = 0;

        $userIds = array();

        if ($this->getFilterRne()) {
            $userIds = $this->getFilterPupil();
        } else {
            if (!($handle = fopen($this->input->getOption('pupils_file'), 'r')) !== false) {
                throw new \Exception('invalid file' . $this->input->getOption('pupils_file'));
            }
            // skip header
            fgetcsv($handle, 500, ';');
            while (($data = fgetcsv($handle, 500, ';')) !== false) {
                $userIds[] = $data[1];
            }
        }
        /* @var BNSUserManager $userManager */
        $userManager = $this->get('bns.user_manager');

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = sprintf('SELECT * FROM kernel_bu_eleve e
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = e.idEleve AND b2u.bu_type = "USER_ELE"
                INNER JOIN dbuser u ON u.id_dbuser = b2u.user_id
                WHERE u.enabled_dbuser = 1 AND e.idEleve IN (%s)
                ', implode(',', $userIds));
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'élève est déjà importé
            if ($this->isImported($row['user_id'], 'dbuser')) {
                $this->log('eleve deja importe : ' . $row['login_dbuser']);
                $ignore++;
                continue;
            }
            $regenerateUsername = false;
            /*try {
                if ($userManager->getLoginExists($row['login_dbuser'])) {
                    $this->log('Utilisateur avec un login duplique : ' . $row['login_dbuser']);
                    $regenerateUsername = true;
                    $duplicate++;
                }
            } catch (\Exception $e) {
                $this->log('Login exist issue : ' . $e->getMessage());
                $error++;
                continue;
            }*/

            // on créé le user
            /*
            $user = $userManager->createUser(array(
                    'first_name' => $row['prenom1'],
                    'last_name' => $row['nom'],
                    'gender' => (1 == $row['id_sexe'] ? UserPeer::GENDER_M : UserPeer::GENDER_F),
                    'lang' => 'fr',
                    'email' => null,
                    'salt' => 'migration_from_md5',
                    'password' => $row['password_dbuser'],
                    'birthday' => isset($row['date_nais']) ? $row['date_nais'] : null,
                    'username' => $regenerateUsername? 'temporary' : $row['login_dbuser'],
                    'created_at' => $row['created_at']
            ), false);
            */

            //POUR RECUPERATION
            $user = UserQuery::create()->filterByFirstName($row['prenom1'])->filterByLastName($row['nom'])->filterByBirthday($row['date_nais'])->findOne();

            if ($user) {
                if ($this->saveImported($row['user_id'], 'dbuser', $user->getId(), 'User', $regenerateUsername ? array('username' => $user->getUsername()) : null)) {
                    $this->log('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    if ($regenerateUsername) {
                        $this->output->writeln('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    }
                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'eleve ' . $row['login_dbuser']);
                }
            } else {
                echo $row['prenom1'] . ' ' . $row['nom'] . ' ' . $row['date_nais'] . ' ';
                $this->log('eleve non importe : ' . $row['login_dbuser'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();
        unset($userIds);

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total, 'duplicate' => $duplicate);
    }

    protected function importUser($parent = false)
    {
        $success   = 0;
        $ignore    = 0;
        $duplicate = 0;
        $error     = 0;
        $total     = 0;

        $userIds   = array();
        $parentIds = array();

        if ($this->getFilterRne()) {
            $parentIds = $this->getFilterParent();
            $userIds = $this->getFilterUser();
        } else {
            if (!($handle = fopen($this->input->getOption('users_file'), 'r')) !== false) {
                throw new \Exception('invalid file' . $this->input->getOption('users_file'));
            }

            // skip header
            fgetcsv($handle, 500, ';');
            while (($data = fgetcsv($handle, 500, ';')) !== false) {
                if (strlen($data[1]) > 5 && 100 == substr($data[1], 0, 3)) {
                    $parentIds[] = ltrim(substr($data[1], 3), '0');
                } else {
                    $userIds[] = $data[1];
                }
            }
        }

        $userManager = $this->get('bns.user_manager');
        $con = \Propel::getMasterConnection('import');

        //query get users
        if (!$parent) {
            $sql = sprintf('SELECT * FROM kernel_bu_personnel p
                    INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = p.numero AND b2u.bu_type IN ("USER_ENS", "USER_VIL")
                    INNER JOIN dbuser u ON u.id_dbuser = b2u.user_id
                    WHERE u.enabled_dbuser = 1 AND p.numero IN (%s)
                    ', implode(',', $userIds));
        } else {
            $sql = sprintf('SELECT * FROM kernel_bu_responsable r
                    INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = r.numero AND b2u.bu_type = "USER_RES"
                    INNER JOIN dbuser u ON u.id_dbuser = b2u.user_id
                    WHERE u.enabled_dbuser = 1 AND r.numero IN (%s)
                    ', implode(',', $parentIds));
        }
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'utilisateur est déjà importé
            if ($this->isImported($row['user_id'], 'dbuser')) {
                $this->log('utilisateur deja importe : ' . $row['login_dbuser']);
                $ignore++;
                continue;
            }
            $regenerateUsername = false;
            /*if ($userManager->getLoginExists($row['login_dbuser'])) {
                $this->log('Utilisateur avec un login duplique : ' . $row['login_dbuser']);
                $regenerateUsername = true;
                $duplicate++;
            }*/

            // on créé l'utilisateur
            /*$user = $userManager->createUser(array(
                    'first_name' => $row['prenom1'],
                    'last_name' => $row['nom'],
                    'gender' => (1 == $row['id_sexe'] ? UserPeer::GENDER_M : UserPeer::GENDER_F),
                    'lang' => 'fr',
                    'email' => $row['mel']?: null,
                    'salt' => 'migration_from_md5',
                    'password' => $row['password_dbuser'],
                    'birthday' => isset($row['date_nais']) && '0000-00-00' != $row['date_nais'] ? $row['date_nais'] : null,
                    'username' => $regenerateUsername? 'temporary' : $row['login_dbuser'],
                    'created_at' =>  ('0000-00-00 00:00:00' != $row['created_at']) ? $row['created_at'] : null
            ), false);
*/

            //echo var_dump($row);
            //POUR RECUPERATION - à partir de l'email
            $user = UserQuery::create()->filterByFirstName($row['prenom1'])->filterByLastName($row['nom'])->findOne();

            if ($user) {
                if ($this->saveImported($row['user_id'], 'dbuser', $user->getId(), 'User', $regenerateUsername ? array('username' => $user->getUsername(), 'old_username' => $row['login_dbuser']) : null)) {
                    $this->log('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    if ($regenerateUsername) {
                        $this->output->writeln('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    }

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'utilisateur ' . $row['login_dbuser']);
                }
            } else {
                $this->log('utilisateur non importe : ' . $row['login_dbuser'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();
        unset($userIds, $parentIds);

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total, 'duplicate' => $duplicate);
    }

    protected function importExternalUser()
    {
        $success   = 0;
        $ignore    = 0;
        $duplicate = 0;
        $error     = 0;
        $total     = 0;

        $userManager = $this->get('bns.user_manager');

        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT * FROM kernel_ext_user ext
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = ext.id AND b2u.bu_type = "USER_EXT"
                INNER JOIN dbuser u ON u.id_dbuser = b2u.user_id
                WHERE u.enabled_dbuser = 1';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'utilisateur est déjà importé
            if ($this->isImported($row['user_id'], 'dbuser')) {
                $this->log('utilisateur deja importe : ' . $row['login_dbuser']);
                $ignore++;
                continue;
            }
            $regenerateUsername = false;
            if ($userManager->getLoginExists($row['login_dbuser'])) {
                $this->log('Utilisateur avec un login duplique : ' . $row['login_dbuser']);
                $regenerateUsername = true;
                $duplicate++;
            }

            // on créé l'utilisateur
            $user = $userManager->createUser(array(
                    'first_name' => $row['prenom'],
                    'last_name' => $row['nom'],
                    'gender' => UserPeer::GENDER_M,
                    'lang' => 'fr',
                    'email' => null,
                    'salt' => 'migration_from_md5',
                    'password' => $row['password_dbuser'],
                    'birthday' => null,
                    'username' => $regenerateUsername? 'temporary' : $row['login_dbuser'],
            ), false);

            if ($user) {
                if ($this->saveImported($row['user_id'], 'dbuser', $user->getId(), 'User', $regenerateUsername ? array('username' => $user->getUsername(), 'old_username' => $row['login_dbuser']) : null)) {
                    $this->log('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    if ($regenerateUsername) {
                        $this->output->writeln('import user : ' . $row['login_dbuser'] . ($regenerateUsername ? ' nouveau login : ' . $user->getUsername(): null));
                    }

                    $success++;
                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour l\'utilisateur ' . $row['login_dbuser']);
                }
            } else {
                $this->log('utilisateur non importe : ' . $row['login_dbuser'] . ' erreur lors de la creation');
                $error++;
            }
            unset($user);
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total, 'duplicate' => $duplicate);
    }

    protected function linkedPupilToGroup()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $roleManager = $this->get('bns.role_manager');
        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT b2u.user_id, a.classe
                FROM kernel_bu_eleve_affectation a
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = a.eleve AND b2u.bu_type = "USER_ELE"
                WHERE a.current = 1 AND annee_scol = "2013"
                ORDER BY classe ASC, user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur est déjà importé
            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            //On recherche la classe
            /* @var BNSUserManager $userManager */
            $userManager = $this->get('bns.user_manager');
            $userManager->setUser($importedUser);
            $userClassrooms = $userManager->getClassroomUserBelong();

            foreach($userClassrooms as $userClassroom)
            {
                $this->saveImported($row['classe'], 'kernel_bu_ecole_classe', $userClassroom->getId(), 'Group');
            }

/*
            if (!($importedClass = $this->getImported($row['classe'], 'kernel_bu_ecole_classe'))) {
                $this->log('classe non importe : ' . $row['classe']);
                $error++;
                continue;
            }
            */

            /*if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedClass->getBnsKey())) {
                $ignore++;
                continue;
            }*/
            /*
            $roleManager
                ->setGroupTypeRoleFromType('PUPIL')
                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedClass->getBnsKey());
            */
            //$this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedClass->getBnsKey());
            $success++;
        }

        //query get users
        $sql = 'SELECT user_id, node_id as group_id
                FROM kernel_link_user2node u2n
                WHERE user_type = "USER_ELE" AND node_type = "CLUB"
                ORDER BY group_id ASC, user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur a été importé
            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            if (!($importedGroup = $this->getImported($row['group_id'], 'module_groupe_groupe'))) {
                $this->log('groupe non importe : ' . $row['group_id']);
                $error++;
                continue;
            }

            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedGroup->getBnsKey())) {
                $ignore++;
                continue;
            }

            $roleManager
                ->setGroupTypeRoleFromType('PUPIL')
                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedGroup->getBnsKey());

            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedGroup->getBnsKey());
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function linkedParentToGroup()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $roleManager = $this->get('bns.role_manager');
        $roleManager->setGroupTypeRoleFromType('PARENT');
        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT b2p.user_id, a.classe, b2u.user_id as pupil_id
                FROM kernel_bu_responsables r
                INNER JOIN kernel_bu_eleve_affectation a ON a.eleve = r.id_beneficiaire
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = a.eleve AND b2u.bu_type = "USER_ELE"
                INNER JOIN kernel_link_bu2user b2p ON b2p.bu_id = r.id_responsable AND b2p.bu_type = "USER_RES"
                WHERE a.current = 1 AND annee_scol = "2012"
                ORDER BY classe ASC, user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur est déjà importé
            if (!($importedParent = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            if (!($importedPupil = $this->getImported($row['pupil_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['pupil_id']);
                $error++;
                continue;
            }

            if (!($importedClass = $this->getImported($row['classe'], 'kernel_bu_ecole_classe'))) {
                $this->log('classe non importe : ' . $row['classe']);
                $error++;
                continue;
            }

            if ($this->isImported($importedParent->getBnsKey(), 'bns_user_group', $importedClass->getBnsKey())) {
                $ignore++;
                continue;
            }

            $roleManager->assignRole(UserQuery::create()->findPk($importedParent->getBnsKey()), $importedClass->getBnsKey());

            $this->saveImported($importedParent->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedClass->getBnsKey());

            $userManager = $this->get('bns.user_manager');
            $userManager->addParent($importedPupil->getBnsKey(), $importedParent->getBnsKey());

            $success++;
        }
        
        
        

//        //query get users
//        $sql = 'SELECT user_id, node_id as group_id
//                FROM kernel_link_user2node u2n
//                WHERE user_type = "USER_RES" AND node_type = "CLUB"
//                ORDER BY group_id ASC, user_id ASC';
//        /* @var $stmt PDOStatement*/
//        $stmt = $con->prepare($sql);
//        $stmt->execute();
//
//        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
//            $total++;
//            // vérif si l'utilisateur a été importé
//            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
//                $this->log('utilisateur non importe : ' . $row['user_id']);
//                $error++;
//                continue;
//            }
//
//            if (!($importedGroup = $this->getImported($row['group_id'], 'module_groupe_groupe'))) {
//                $this->log('groupe non importe : ' . $row['group_id']);
//                $error++;
//                continue;
//            }
//
//            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedGroup->getBnsKey())) {
//                $ignore++;
//                continue;
//            }
//
//            $roleManager
//                ->setGroupTypeRoleFromType('PARENT')
//                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedGroup->getBnsKey());
//
//            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedGroup->getBnsKey());
//            $success++;
//        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
    
    
    
    protected function repareParentAccounts()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $roleManager = $this->get('bns.role_manager');
        $roleManager->setGroupTypeRoleFromType('PARENT');
        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT b2p.user_id, a.classe, b2u.user_id as pupil_id
                FROM kernel_bu_responsables r
                INNER JOIN kernel_bu_eleve_affectation a ON a.eleve = r.id_beneficiaire
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = a.eleve AND b2u.bu_type = "USER_ELE"
                INNER JOIN kernel_link_bu2user b2p ON b2p.bu_id = r.id_responsable AND b2p.bu_type = "USER_RES"
                WHERE a.current = 1 AND annee_scol = "2012"
                ORDER BY classe DESC, user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();
echo '1';
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            echo '1';
            // vérif si l'utilisateur est déjà importé
            if (!($importedParent = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur parent non importe : ' . $row['user_id']);
                $error++;
                continue;
            }
echo '2';
            if (!($importedPupil = $this->getImported($row['pupil_id'], 'dbuser'))) {
                $this->log('utilisateur eleve non importe : ' . $row['pupil_id']);
                $error++;
                continue;
            }
echo '3';
            if (!($importedClass = $this->getImported($row['classe'], 'kernel_bu_ecole_classe'))) {
                $this->log('classe non importe : ' . $row['classe']);
                $error++;
                continue;
            }
echo '4';
            if ($this->isImported($importedParent->getBnsKey(), 'repare_parent', $importedClass->getBnsKey())) {
                $this->log('Reparation deja faite');
                $ignore++;
                continue;
            }
echo '5';
            
            $roleManager = $this->get('bns.role_manager');
            $roleManager->setGroupTypeRoleFromType('PARENT');
            
            $user = UserQuery::create()->findPk($importedParent->getBnsKey());
            $um = $this->get('bns.user_manager');
            $um->setUser($user);
echo '6';
            $roleManager->unassignRole($user->getId(), 57618, 'TEACHER');
     echo '6bis';       
            $roleManager->unassignRole($user->getId(), 57618, 'PUPIL');
echo '7';
            
            $roleManager = $this->get('bns.role_manager');
            $roleManager->setGroupTypeRoleFromType('PARENT');
            
            $roleManager->assignRole($user, $importedClass->getBnsKey());
echo '8';
            $this->saveImported($importedParent->getBnsKey(), 'repare_parent', 0, 'GroupLinked', null, $importedClass->getBnsKey());
echo '9';
            $userManager = $this->get('bns.user_manager');
            $userManager->addParent($importedPupil->getBnsKey(), $importedParent->getBnsKey());

echo '10';
   echo ' - ' . $user->getId() . ' - ';         


            $success++;
        }
        
        
        

//        //query get users
//        $sql = 'SELECT user_id, node_id as group_id
//                FROM kernel_link_user2node u2n
//                WHERE user_type = "USER_RES" AND node_type = "CLUB"
//                ORDER BY group_id ASC, user_id ASC';
//        /* @var $stmt PDOStatement*/
//        $stmt = $con->prepare($sql);
//        $stmt->execute();
//
//        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
//            $total++;
//            // vérif si l'utilisateur a été importé
//            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
//                $this->log('utilisateur non importe : ' . $row['user_id']);
//                $error++;
//                continue;
//            }
//
//            if (!($importedGroup = $this->getImported($row['group_id'], 'module_groupe_groupe'))) {
//                $this->log('groupe non importe : ' . $row['group_id']);
//                $error++;
//                continue;
//            }
//
//            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedGroup->getBnsKey())) {
//                $ignore++;
//                continue;
//            }
//
//            $roleManager
//                ->setGroupTypeRoleFromType('PARENT')
//                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedGroup->getBnsKey());
//
//            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedGroup->getBnsKey());
//            $success++;
//        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function linkedUserToGroup()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $roleManager = $this->get('bns.role_manager');
        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT b2u.user_id, p.reference, p.type_ref, p.role
                FROM kernel_bu_personnel_entite p
                INNER JOIN kernel_link_bu2user b2u ON b2u.bu_id = p.id_per AND b2u.bu_type IN ("USER_ENS", "USER_VIL")
                ORDER BY user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur est déjà importé
            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            switch ($row['type_ref']) {
                case 'GVILLE':
                    $originTable = 'kernel_bu_groupe_villes';
                    break;
                case 'VILLE':
                    $originTable = 'kernel_bu_ville';
                    break;
                case 'ECOLE':
                    $originTable = 'kernel_bu_ecole';
                    break;
                default:
                case 'CLASSE':
                    $originTable = 'kernel_bu_ecole_classe';
                    break;
            }

            if (!($importedGroup = $this->getImported($row['reference'], $originTable))) {
                $this->log('Groupe non importe de ' . $originTable . ' : ' . $row['reference']);
                $error++;
                continue;
            }

            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $row['role'] . '_' . $importedGroup->getBnsKey())) {
                $ignore++;
                continue;
            }

            switch ($row['role']) {
                case self::TEACHER:
                    $roleManager->setGroupTypeRoleFromType('TEACHER');
                    break;
                case self::DIRECTOR:
                    $roleManager->setGroupTypeRoleFromType($this->getGroupType('DIRECTOR')->getType());
                    break;
                case self::SCHOOL_OFFICER:
                    $roleManager->setGroupTypeRoleFromType($this->getGroupType('SCHOOL_OFFICER')->getType());
                    break;
                case self::CITY_OFFICER:
                    $roleManager->setGroupTypeRoleFromType($this->getGroupType('CITY_OFFICER')->getType());
                    break;
                case self::GROUP_CITY_OFFICER:
                    $roleManager->setGroupTypeRoleFromType($this->getGroupType('GROUP_CITY_OFFICER')->getType());
                    break;
                default:
                    $ignore++;
                    continue;
            }

            $roleManager
                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedGroup->getBnsKey());

            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $row['role'] . '_' . $importedGroup->getBnsKey());
            $success++;
        }


        //query get users
        /*
        $sql = 'SELECT user_id, node_id as group_id
                FROM kernel_link_user2node u2n
                WHERE user_type = "USER_ENS" AND node_type = "CLUB"
                ORDER BY group_id ASC, user_id ASC';
        /* @var $stmt PDOStatement*/
        /*
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur a été importé
            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            if (!($importedGroup = $this->getImported($row['group_id'], 'module_groupe_groupe'))) {
                $this->log('groupe non importe : ' . $row['group_id']);
                $error++;
                continue;
            }
            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedGroup->getBnsKey())) {
                $ignore++;
                continue;
            }

            $roleManager
                ->setGroupTypeRoleFromType('TEACHER')
                ->assignRole(UserQuery::create()->findPk($importedUser->getBnsKey()), $importedGroup->getBnsKey());

            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedGroup->getBnsKey());
            $success++;
        }
        */

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function linkedExternalToGroup()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $roleManager = $this->get('bns.role_manager');
        $con = \Propel::getMasterConnection('import');

        //query get users
        $sql = 'SELECT user_id, node_id as group_id
                FROM kernel_link_user2node u2n
                WHERE user_type = "USER_EXT" AND node_type = "CLUB"
                ORDER BY group_id ASC, user_id ASC';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;
            // vérif si l'utilisateur a été importé
            if (!($importedUser = $this->getImported($row['user_id'], 'dbuser'))) {
                $this->log('utilisateur non importe : ' . $row['user_id']);
                $error++;
                continue;
            }

            if (!($importedGroup = $this->getImported($row['group_id'], 'module_groupe_groupe'))) {
                $this->log('groupe non importe : ' . $row['group_id']);
                $error++;
                continue;
            }

            if ($this->isImported($importedUser->getBnsKey(), 'bns_user_group', $importedGroup->getBnsKey())) {
                $ignore++;
                continue;
            }
            $user = UserQuery::create()->findPk($importedUser->getBnsKey());

            $roleManager
                ->setGroupTypeRoleFromType($this->getGroupType('EXTERNAL')->getType())
                ->assignRole($user, $importedGroup->getBnsKey());

            $this->saveImported($importedUser->getBnsKey(), 'bns_user_group', 0, 'GroupLinked', null, $importedGroup->getBnsKey());

            unset($user);
            unset($importedUser);
            unset($importedGroup);
            unset($row);
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

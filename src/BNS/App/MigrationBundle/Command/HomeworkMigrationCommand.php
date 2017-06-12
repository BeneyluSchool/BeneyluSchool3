<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\HomeworkBundle\Model\HomeworkGroup;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkTask;



use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\ResourceBundle\Model\ResourceJoinObject;

/**
 *
 * @author Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 */
class HomeworkMigrationCommand extends BaseMigrationCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:homework')
            ->setDescription('Import des cahier de textes iconito')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        switch ($input->getArgument('step')) {
            default:
            case 'lecon':
                $output->writeln('<info>Debut</info> migration des <info>cahier de textes : lecon</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'lecon'), $this->importLeconsFromAgenda()));
                $this->end();

            case 'subject':
                $output->writeln('<info>Debut</info> migration des <info>cahier de textes : matiere</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'matiere'), $this->importSubjects()));
                $this->end();

            case 'homework':
                $output->writeln('<info>Debut</info> migration des <info>cahier de textes : travaux</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'travaux'), $this->importHomeworks()));
                $this->end();

            case 'memo':
                $output->writeln('<info>Debut</info> migration des <info>cahier de textes : memo</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'memo'), $this->importMemos()));
                $this->end();
        }
    }

    protected function importLeconsFromAgenda()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT *
            FROM `module_agenda_lecon`
            ;";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_lecon'], 'module_agenda_lecon')) {
                $this->log('Lecon Agenda deja importe ' . $row['id_lecon']);
                $ignore++;
                continue;
            }

            if (!$importedAgenda = $this->isImported($row['id_agenda'], 'module_agenda_agenda')) {
                $this->log('Agenda non importe ' . $row['id_agenda']);
                $ignore++;
                continue;
            }

            $agenda = AgendaQuery::create()
                ->filterByGroupId($importedAgenda->getBnsKey())
                ->findOne();

            $group = GroupQuery::create()
                ->filterByGroupId($agenda->getGroupId())
                ->findOne();

            if($group){
                $matiereLecon = HomeworkSubjectQuery::create()
                    ->filterByGroupId($group->getId())
                    ->filterByName("Leçons")
                    ->findOne();

                if(!$matiereLecon){
                    $matiereRoot = HomeworkSubjectQuery::create()
                        ->filterByGroupId($group->getId())
                        ->filterByTreeLevel(0)
                        ->findOne();
                    if(!$matiereRoot){
                        $matiereRoot = new HomeworkSubject();
                        $matiereRoot->setName("subjects for group " . $group->getId());
                        $matiereRoot->setGroupId($group->getId());
                        $matiereRoot->makeRoot();
                        $matiereRoot->save();
                    }
                    $matiereLecon = new HomeworkSubject();
                    $matiereLecon->setName("Leçons");
                    $matiereLecon->setGroupId($group->getId());
                    $matiereLecon->insertAsFirstChildOf($matiereRoot);
                    $matiereLecon->save();
                }

                $lecon = new Homework();
                $lecon->setName($row['desc_lecon']);
                $lecon->setDate(substr($row['date_lecon'],0,4) . '-' . substr($row['date_lecon'],4,2) . '-' . substr($row['date_lecon'],6,2));
                $lecon->setSubjectId($matiereLecon->getId());
                $lecon->setRecurrenceType(0);
                $lecon->save();

                $this->saveImported($row['id_lecon'], 'module_agenda_lecon', $lecon->getId(), 'Homework');

                $homework_service = $this->get('bns.homework_manager');
                $homework_service->processHomework($lecon);

            }else{
                $this->log("Erreur : Le groupe n existe pas : " . $agenda->getId());
                $error++;
                continue;
            }
            $success++;
        }
        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importSubjects()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT *
            FROM `module_cahierdetextes_domaine`";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id'], 'module_cahierdetextes_domaine')) {
                $this->log('matiere cahier de textes deja importee ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!$importedClassroom = $this->getImported($row['kernel_bu_ecole_classe_id'], 'kernel_bu_ecole_classe')) {
                $this->log('Classe non importee ' . $row['kernel_bu_ecole_classe_id']);
                $ignore++;
                continue;
            }

            $group = GroupQuery::create()
                ->filterById($importedClassroom->getBnsKey())
                ->findOne();

            if($group){

                $matiereRoot = HomeworkSubjectQuery::create()
                    ->filterByGroupId($group->getId())
                    ->filterByTreeLevel(0)
                    ->findOne();
                if(!$matiereRoot){
                    $matiereRoot = new HomeworkSubject();
                    $matiereRoot->setName("subjects for group " . $group->getId());
                    $matiereRoot->setGroupId($group->getId());
                    $matiereRoot->makeRoot();
                    $matiereRoot->save();
                }

                $subject = new HomeworkSubject();
                $subject->setName($row['nom']);
                $subject->insertAsFirstChildOf($matiereRoot);
                $subject->save();

                $this->saveImported($row['id'], 'module_cahierdetextes_domaine', $subject->getId(), 'HomeworkSubject');

            }else{
                $this->log("Erreur : Le groupe n existe pas");
                $error++;
                continue;
            }
            $success++;
        }
        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importHomeworks()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT DISTINCT t.*
            FROM `module_cahierdetextes_travail` t";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id'], 'module_cahierdetextes_travail')) {
                $this->log('travail cahier de textes deja importee ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!$importedSubject = $this->getImported($row['module_cahierdetextes_domaine_id'], 'module_cahierdetextes_domaine')) {
                $this->log('Matiere non importee ' . $row['module_cahierdetextes_domaine_id']);
                $ignore++;
                continue;
            }

            $subject = HomeworkSubjectQuery::create()
                ->findOneById($importedSubject->getBnsKey());

            if($subject){

                $dateCreation = substr($row['date_creation'],0,4) . '-' . substr($row['date_creation'],4,2) . '-' . substr($row['date_creation'],6,2);
                if($row['date_realisation'] == null){
                    $date = $dateCreation;
                }else{
                    $date = substr($row['date_realisation'],0,4) . '-' . substr($row['date_realisation'],4,2) . '-' . substr($row['date_realisation'],6,2);
                }
                $homework = new Homework();
                $homework->setName("Travail du " . substr($row['date_realisation'],6,2) . '/' . substr($row['date_realisation'],4,2) . '/' . substr($row['date_realisation'],0,4));
                $homework->setDescription($row['description']);
                $homework->setCreatedAt($dateCreation . ' 00:00:00');
                $homework->setDate($date);
                $homework->setSubjectId($subject->getId());
                $homework->setRecurrenceType(0);
                $homework->save();

                $homework_service = $this->get('bns.homework_manager');
                $homework_service->processHomework($homework);

                $this->saveImported($row['id'], 'module_cahierdetextes_travail', $homework->getId(), 'Homework');

                $homework_group = new HomeworkGroup();
                $homework_group->setGroupId($subject->getGroupId());
                $homework_group->setHomeworkId($homework->getId());
                $homework_group->save();

                $sqlSign = "SELECT *
                    FROM `module_cahierdetextes_travail2eleve`
                    WHERE module_cahierdetextes_travail_id = " . $row['id'];

                $stmtSign = $con->prepare($sqlSign);
                $stmtSign->execute();

                while ($rowSign = $stmtSign->fetch(\PDO::FETCH_ASSOC)) {

                    if (!($importedAuthor = $this->getImported($rowSign['kernel_bu_eleve_idEleve'], 'dbuser'))) {
                        $this->log('Erreur import signature cahier de textes utilisateur non importe : ' . $rowSign['kernel_bu_eleve_idEleve']);
                        $error++;
                        continue;
                    }

                    $homework_due = HomeworkDueQuery::create()
                        ->filterByHomeworkId($homework->getId())
                        ->findOne();

                    $task = new HomeworkTask();
                    $task->setHomeworkDueId($homework_due->getId());
                    $task->setUserId($importedAuthor->getBnsKey());
                    $task->setDone(1);
                    $task->save();
                    $homework_due->updateNumberOfTasksDone();
                }

                // fichier join
                $sqlFile = 'SELECT * FROM module_cahierdetextes_travail2files
                        WHERE module_files_type = "MOD_CLASSEUR" AND module_cahierdetextes_travail_id = ' . $row['id'];
                $stmtFile = $con->prepare($sqlFile);
                $stmtFile->execute();
                while ($rowFile = $stmtFile->fetch(\PDO::FETCH_ASSOC)) {
                    if (!($importedFile = $this->getImported($rowFile['module_malle_files_id'], 'module_classeur_fichier'))) {
                        $this->log('Fichier non importe : ' . $rowFile['module_malle_files_id']);
                        $error++;
                        continue;
                    }

                    $resourceJoinObject = new ResourceJoinObject();
                    $resourceJoinObject->setObjectId($homework->getId());
                    $resourceJoinObject->setObjectClass('Homework');
                    $resourceJoinObject->setResourceId($importedFile->getBnsKey());
                    $resourceJoinObject->save();
                }

            }else{
                $this->log("Erreur : La matiere n existe pas");
                $error++;
                continue;
            }
            $success++;
        }
        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importMemos()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT *
            FROM `module_cahierdetextes_memo` order by id DESC
        ;";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id'], 'module_cahierdetextes_memo')) {
                $this->log('memo cahier de textes deja importee ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!$importedClassroom = $this->getImported($row['kernel_bu_ecole_classe_id'], 'kernel_bu_ecole_classe')) {
                $this->log('Classe non importee ' . $row['kernel_bu_ecole_classe_id']);
                $ignore++;
                continue;
            }

            $group = GroupQuery::create()
                ->findOneById($importedClassroom->getBnsKey());

            if($group){

                $matiereMemo = HomeworkSubjectQuery::create()
                ->filterByGroupId($group->getId())
                ->filterByName("Mémos")
                ->findOne();

                if(!$matiereMemo){
                    $matiereRoot = HomeworkSubjectQuery::create()
                        ->filterByGroupId($group->getId())
                        ->filterByTreeLevel(0)
                        ->findOne();
                    if(!$matiereRoot){
                        $matiereRoot = new HomeworkSubject();
                        $matiereRoot->setName("subjects for group " . $group->getId());
                        $matiereRoot->setGroupId($group->getId());
                        $matiereRoot->makeRoot();
                        $matiereRoot->save();
                    }
                    $matiereMemo = new HomeworkSubject();
                    $matiereMemo->setName("Mémos");
                    $matiereMemo->setGroupId($group->getId());
                    $matiereMemo->insertAsFirstChildOf($matiereRoot);
                    $matiereMemo->save();
                }

                $dateCreation = substr($row['date_creation'],0,4) . '-' . substr($row['date_creation'],4,2) . '-' . substr($row['date_creation'],6,2);

                if($row['date_validite'] == null){
                    $date = $dateCreation;
                }else{
                    $date = substr($row['date_validite'],0,4) . '-' . substr($row['date_validite'],4,2) . '-' . substr($row['date_validite'],6,2);
                }

                $homework = new Homework();
                $homework->setName("Mémo");
                $homework->setDescription($row['message']);
                $homework->setCreatedAt($dateCreation . ' 00:00:00');
                $homework->setDate($date);
                $homework->setSubjectId($matiereMemo->getId());
                $homework->setRecurrenceType(0);
                $homework->save();

                $homework_service = $this->get('bns.homework_manager');
                $homework_service->processHomework($homework);

                $this->saveImported($row['id'], 'module_cahierdetextes_memo', $homework->getId(), 'Homework');

                $homework_group = new HomeworkGroup();
                $homework_group->setGroupId($group->getId());
                $homework_group->setHomeworkId($homework->getId());
                $homework_group->save();

                $sqlSign = "SELECT *
                    FROM `module_cahierdetextes_memo2eleve`
                    WHERE module_cahierdetextes_memo_id = " . $row['id'] . "
                ;";

                 /* @var $stmt PDOStatement*/
                $stmtSign = $con->prepare($sqlSign);
                $stmtSign->execute();

                while ($rowSign = $stmtSign->fetch(\PDO::FETCH_ASSOC)) {

                    if (!($importedAuthor = $this->getImported($rowSign['kernel_bu_eleve_idEleve'], 'dbuser'))) {
                        $this->log('Erreur import signature cahier de textes utilisateur non importe : ' . $rowSign['kernel_bu_eleve_idEleve']);
                        $error++;
                        continue;
                    }

                    $homework_due = HomeworkDueQuery::create()
                        ->filterByHomeworkId($homework->getId())
                        ->findOne();

                    $task = new HomeworkTask();
                    $task->setHomeworkDueId($homework_due->getId());
                    $task->setUserId($importedAuthor->getBnsKey());
                    $task->setDone(1);
                    $task->save();
                    $homework_due->updateNumberOfTasksDone();
                }

            }else{
                $this->log("Erreur : Le groupe n existe pas : " . $agenda->getId());
                $error++;
                continue;
            }
            $success++;
        }
        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

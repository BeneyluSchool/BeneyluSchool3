<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\CoreBundle\Model\om\BaseAgendaEvent;


use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 *
 * @author Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 */
class AgendaMigrationCommand extends BaseMigrationCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:agenda')
            ->setDescription('Import des agendas iconito')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        switch ($input->getArgument('step')) {
            default:
            case 'agenda':
                $output->writeln('<info>Debut</info> migration des <info>agendas</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'agendas'), $this->importAgendas()));
                $this->end();

            case 'event':
                $output->writeln('<info>Debut</info> migration des <info>events</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'utilisateur'), $this->importEvents()));
                $this->end();
        }
    }

    protected function importAgendas()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT a.* , k.*
            FROM `module_agenda_agenda` a INNER JOIN `kernel_mod_enabled` k ON a.id_agenda = k.module_id
            WHERE k.module_type = 'MOD_AGENDA'
                AND
            k.node_type IN ('BU_CLASSE','BU_ECOLE','BU_VILLE','CLUB')
            ORDER BY k.node_id
            ;";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_agenda'], 'module_agenda_agenda')) {
                $this->log('Agenda deja importe ' . $row['id_agenda']);
                $ignore++;
                continue;
            }
            $continue = true;
            switch($row['node_type']){
                case "BU_CLASSE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ecole_classe'))) {
                        $this->log('Erreur import agenda du groupe ' . $row['id_agenda'] . ' classe non importee : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "BU_ECOLE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ecole'))) {
                        $this->log('Erreur import agenda du groupe ' . $row['id_agenda'] . ' ecole non importee : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "BU_VILLE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ville'))) {
                        $this->log('Erreur import agenda du groupe ' . $row['id_agenda'] . ' ville non importe : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "CLUB":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'module_groupe_groupe'))) {
                        $this->log('Erreur import agenda du groupe ' . $row['id_agenda'] . ' Club non importe : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                default:
                    $this->log('Erreur, Type de noeud inconnu : ' . $row['node_type'] );
                    $error++;
                    continue;
                break;
            }

            if(!$continue){
                continue;
            }

            $agenda = AgendaQuery::create()
                ->filterByGroupId($importedGroup->getBnsKey())
                ->findOne();

            if ($agenda) {
                $agenda->setTitle($row['title_agenda']);
                $agenda->save();
            } else {
                AgendaPeer::createAgenda(array(
                    'label' => $row['title_agenda'],
                    'group_id' => $importedGroup->getBnsKey()
                ));

                $agenda = AgendaQuery::create()
                    ->filterByGroupId($importedGroup->getBnsKey())
                    ->findOne();
            }

            if ($agenda) {
                $this->saveImported($row['id_agenda'], 'module_agenda_agenda', $agenda->getId(), 'Agenda');
            } else {
                $this->log("Erreur : pas d'agenda BNS pour le groupe d'ID : " . $importedGroup->getBnsKey());
                $error++;
                continue;
            }
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    /**
     * Importe les messages depuis le minimail d'Iconito
     */
    protected function importEvents()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = 'SELECT * FROM `module_agenda_event` ORDER BY id_event DESC';

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_event'], 'module_agenda_event')) {
                $this->log('Evenement deja importe ' . $row['id_event']);
                $ignore++;
                continue;
            }

            if (!($importedAgenda = $this->getImported($row['id_agenda'], 'module_agenda_agenda'))) {
                $this->log('Erreur import de l evenement  ' . $row['id_event'] . ' agenda non importe : ' . $row['id_agenda']);
                $error++;
                continue;
            }

            $calendar_service = $this->get('bns.calendar_manager');

            $start = substr($row['datedeb_event'],0,4) . '-' . substr($row['datedeb_event'],4,2) . '-' . substr($row['datedeb_event'],6,2);
            $end = substr($row['datefin_event'],0,4) . '-' . substr($row['datefin_event'],4,2) . '-' . substr($row['datefin_event'],6,2);

            $eventInfos = array(
                'summary'       => $row['title_event'],
                'description'   => $row['desc_event'],
                'location'      => $row['place_event'],
                'dtstart'       => $row['alldaylong_event'] ? strtotime($start) : strtotime($start . ' ' . substr($row['heuredeb_event'],0,2) . ':' . substr($row['heuredeb_event'],3,2). ':' . '00'),
                'dtend'         => $row['alldaylong_event'] ? strtotime($end) : strtotime($end . ' ' . substr($row['heurefin_event'],0,2) . ':' . substr($row['heurefin_event'],3,2). ':' . '00'),
                'allday'        => $row['alldaylong_event'],
                'rrule'         => '',
            );
            if (true == $row['everyday_event'] || $row['everyweek_event'] || $row['everymonth_event'] || $row['everyyear_event']) {
                $recurringInfos = array();
                if($row['everyday_event']){
                    $recurringInfos['FREQ'] = 'DAILY';
                }elseif($row['everyweek_event']){
                    $recurringInfos['FREQ'] = 'WEEKLY';
                }elseif($row['everymonth_event']){
                    $recurringInfos['FREQ'] = 'MONTHLY';
                }elseif($row['everyyear_event']){
                    $recurringInfos['FREQ'] = 'YEARLY';
                }

                if (null != $row['endrepeatdate_event'] && $row['endrepeatdate_event'] != "99999999") {
                    $recurringInfos['UNTIL'] = array('timestamp' => strtotime(substr($row['endrepeatdate_event'],0,4) . '-' . substr($row['endrepeatdate_event'],4,2) . '-' . substr($row['endrepeatdate_event'],6,2)));
                } else {
                    //Date de fin obligatoire par défaut fin de l'année scolaire
                    $recurringInfos['UNTIL'] = strtotime(date('Y') . '-07-15');
                }

                $eventInfos['rrule'] = $recurringInfos;
            }
            $event = $calendar_service->createEvent($importedAgenda->getBnsKey(),$eventInfos,true);
            $this->saveImported($row['id_event'], 'module_agenda_event', $event->getId(), 'AgendaEvent');
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

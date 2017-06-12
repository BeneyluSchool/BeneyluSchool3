<?php
namespace BNS\App\MigrationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\MigrationBundle\Model\MigrationIconitoQuery;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LiaisonbookMigrationCommand extends BaseMigrationCommand
{
    const MESSAGE_READ = 1;
    const MESSAGE_SENT = 4;
    const MESSAGE_DELETED = 0;
    const MESSAGE_NONE_READ = 2;

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:liaisonbook')
            ->setDescription('Import des carnet de liaison iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        \Propel::disableInstancePooling();

        switch ($input->getArgument('step')) {
            default:
            case 'liaisonbook':
            $output->writeln('<info>Debut</info> migration des <info>carnet de liaison</info> : ' . date('d/m/Y H:i:s'));
            $this->displayResult(array_merge(array('label' => 'carnet liaison'), $this->importLiaisonBook()));
            $this->end();

            case 'liaisonbookAnswer':
            $output->writeln('<info>Debut</info> migration des <info>carnet de liaison reponses</info> : ' . date('d/m/Y H:i:s'));
            $this->displayResult(array_merge(array('label' => 'carnet liaison reponse'), $this->importLiaisonBookAnswer()));
            $this->end();
        }


    }

    protected function importLiaisonBook()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $userIds = array();

        $userManager = $this->get('bns.user_manager');

        $con = \Propel::getMasterConnection('import');

        $classes = MigrationIconitoQuery::create()
            ->filterByEnvironment($this->getEnvironmentName())
            ->filterByOriginClass('kernel_bu_ecole_classe')
            ->select('OriginKey')
            ->find()->getArrayCopy();
        //query get school
        $sql = sprintf('SELECT * FROM module_carnet_topics WHERE classe IN (%s)', implode(',', $classes));
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si le carnet de liaison est déjà importé
            if ($this->isImported($row['id'], 'module_carnet_topics')) {
                $this->log('Carnet de liaison deja importe : ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['createur'], 'dbuser'))) {
                $this->log('Erreur user non  importe : ' . $row['createur']);
                $error++;
                continue;
            }

            if (!($importedClass = $this->getImportedGroup($row['classe'], 'CLASSE'))) {
                $this->log('Erreur classe non  importe : ' . $row['classe']);
                $error++;
                continue;
            }

            $newMessage = new MessagingMessage();
            $newMessage->setAuthorId($importedAuthor->getBnsKey());
            $newMessage->setSubject($row['titre']);
            $newMessage->setContent($this->purifyHtml($row['message']));
            $newMessage->setCreatedAt($row['date_creation']);
            $newMessage->setStatus(1);
            $newMessage->save();


            if ($newMessage) {
                if ($this->saveImported($row['id'], 'module_carnet_topics', $newMessage->getId(), 'MessagingMessage')) {
                    $this->log('import carnet de liaison : ' . $row['titre']);
                    $success++;


                    $sqlTo = "SELECT t.*, u.user_id FROM `module_carnet_topics_to` t
                            INNER JOIN kernel_bu_responsables r ON t.eleve = r.id_beneficiaire
                            INNER JOIN kernel_link_bu2user u ON r.id_responsable = u.bu_id AND u.bu_type = 'USER_RES'
                            WHERE t.topic = " . $row['id'];

                    $stmtTo = $con->prepare($sqlTo);
                    $stmtTo->execute();

                    while ($rowTo = $stmtTo->fetch(\PDO::FETCH_ASSOC)) {
                        if (!($importedReceiver = $this->getImported($rowTo['user_id'], 'dbuser'))) {
                            $this->log('Erreur import message du message ' . $row['id'] . ' utilisateur non importe : ' . $rowTo['user_id']);
                            $error++;
                            continue;
                        }

                        $conversation = new MessagingConversation();
                        $conversation->setUserId($importedReceiver->getBnsKey());
                        $conversation->setUserWithId($importedAuthor->getBnsKey());
                        $conversation->setMessageParentId($newMessage->getId());
                        $conversation->setCreatedAt($row['date_creation']);
                        $conversation->setStatus(self::MESSAGE_READ);
                        $conversation->save();
                        $conversation->link($newMessage);

                        $myConversation = new MessagingConversation();
                        $myConversation->setUserId($importedAuthor->getBnsKey());
                        $myConversation->setUserWithId($importedReceiver->getBnsKey());
                        $myConversation->setMessageParentId($newMessage->getId());
                        $myConversation->setStatus(self::MESSAGE_SENT);
                        $myConversation->setCreatedAt($row['date_creation']);
                        $myConversation->save();
                        $myConversation->link($newMessage);
                    }

                } else {
                    throw new \Exception('erreur lors de la sauvegarde de migrationIconito pour le carnet de liaison ' . $row['titre']);
                }
            } else {
                $this->log('le carnet de liaison ' . $row['titre'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importLiaisonBookAnswer()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');
        $groupManager = $this->get('bns.group_manager');

        $sql = 'SELECT m.*, a.classe, t.titre, t.createur FROM `module_carnet_messages` m
            INNER JOIN kernel_bu_eleve_affectation a ON m.eleve = a.eleve AND a.annee_scol = "2012" AND a.current = 1
            INNER JOIN module_carnet_topics t ON t.id = m.topic
            ORDER BY a.classe';

        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id'], 'module_carnet_messages')) {
                $this->log('Message du carnet deja importe ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!($importedClass = $this->getImported($row['classe'], 'kernel_bu_ecole_classe'))) {
                $this->log('Erreur import message du carnet ' . $row['id'] . ' classe non importe : ' . $row['classe']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['auteur'], 'dbuser'))) {
                $this->log('Erreur import message du carnet ' . $row['id'] . ' utilisateur non importe : ' . $row['auteur']);
                $error++;
                continue;
            }

            if (!($importedUser = $this->getImported($row['createur'], 'dbuser'))) {
                $this->log('Erreur import message du carnet ' . $row['id'] . ' utilisateur non importe : ' . $row['createur']);
                $error++;
                continue;
            }

            if (!($importedTopic = $this->getImported($row['topic'], 'module_carnet_topics'))) {
                $this->log('Erreur import message du carnet ' . $row['id'] . ' topic non importe : ' . $row['topic']);
                $error++;
                continue;
            }

            $newMessage = new MessagingMessage();
            $newMessage->setAuthorId($importedAuthor->getBnsKey());
            $newMessage->setSubject($row['titre']);
            $newMessage->setContent($this->purifyHtml($row['message']));
            $newMessage->setCreatedAt($row['date']);
            $newMessage->setStatus(1);
            $newMessage->save();

            $this->saveImported($row['id'], 'module_carnet_messages', $newMessage->getId(), 'MessagingMessage');

            $conversation = MessagingConversationQuery::create()
                ->filterByUserId($importedUser->getBnsKey())
                ->filterByUserWithId($importedAuthor->getBnsKey())
                ->filterByMessageParentId($importedTopic->getBnsKey())
                ->findOne();

            if ($conversation) {
                $conversation->link($newMessage);
                $myConversation = $conversation->getOpposite();
                if ($myConversation) {
                    $myConversation->link($newMessage);
                }
            }

            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

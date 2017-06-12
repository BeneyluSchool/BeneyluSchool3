<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;

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
class MessagingMigrationCommand extends BaseMigrationCommand
{
    const MESSAGE_READ = 1;
    const MESSAGE_SENT = 4;
    const MESSAGE_DELETED = 0;
    const MESSAGE_NONE_READ = 2;


    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:message')
            ->setDescription('Import des messages iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        \Propel::disableInstancePooling();

        switch ($input->getArgument('step')) {
            case 'message':
                $output->writeln('<info>Debut</info> migration des <info>messages</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'minimail'), $this->importMiniMailMessage()));
                $this->end();

        }

    }


    /**
     * Importe les messages depuis le minimail d'Iconito
     */
    protected function importMiniMailMessage()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = 'SELECT * FROM `module_minimail_from`
            ORDER BY id DESC';

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id'], 'module_minimail_from')) {
                $this->log('Message du minimail deja importe ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['from_id'], 'dbuser'))) {
                $this->log('Erreur import du message  ' . $row['id'] . ' utilisateur non importe : ' . $row['from_id']);
                $error++;
                continue;
            }

            $newMessage = new MessagingMessage();
            $newMessage->setAuthorId($importedAuthor->getBnsKey());
            $newMessage->setSubject($row['title']);
            $newMessage->setContent($this->purifyHtml($row['message']));
            $newMessage->setCreatedAt($row['date_send']);
            $newMessage->setStatus(BNSMessageManager::$messagesStatus['ACCEPTED']);
            $newMessage->setSlug('ancien-' . $row['id']);
            $newMessage->save();

            $this->saveImported($row['id'], 'module_minimail_from', $newMessage->getId(), 'MessagingMessage');

            //Gestion des pièces jointes
            for ($i = 1; $i < 4; $i++) {
                $key = 'attachment' . $i;
                if (isset($row[$key]) && !empty($row[$key])) {
                    $this->createJoinResource($newMessage->getId(), 'MessagingMessage', $key, $importedAuthor->getBnsKey(), $row[$key], true);
                }
            }


            //Gestion des destinataires

            $sql_conversation = 'SELECT * FROM `module_minimail_to`
                WHERE id_message = ' . $row['id'];

            $stmt_conversation = $con->prepare($sql_conversation);
            $stmt_conversation->execute();

            $is_deleted = true;

            while ($row_conversation = $stmt_conversation->fetch(\PDO::FETCH_ASSOC)) {



                if (!($importedReceiver = $this->getImported($row_conversation['to_id'], 'dbuser'))) {
                    $this->log('Erreur import message du message ' . $row['id'] . ' utilisateur non importe : ' . $row_conversation['to_id']);
                    $error++;
                    continue;
                }

                $conversation = new MessagingConversation();
                $conversation->setUserId($importedReceiver->getBnsKey());
                $conversation->setUserWithId($importedAuthor->getBnsKey());
                $conversation->setMessageParentId($newMessage->getId());
                $conversation->setCreatedAt($row['date_send']);

                if ($row_conversation['is_deleted']){
                    $conversation_statut = self::MESSAGE_DELETED;
                } else {
                    $is_deleted = false;
                    if ($row_conversation['is_read']) {
                        $conversation_statut = self::MESSAGE_READ;
                    } else {
                        $conversation_statut = self::MESSAGE_NONE_READ;
                    }
                }


                $conversation->setStatus($conversation_statut);
                $conversation->save();
                $conversation->link($newMessage);

                $myConversation = new MessagingConversation();
                $myConversation->setUserId($importedAuthor->getBnsKey());
                $myConversation->setUserWithId($importedReceiver->getBnsKey());
                $myConversation->setMessageParentId($newMessage->getId());
                $myConversation->setStatus(self::MESSAGE_SENT);
                $myConversation->setCreatedAt($row['date_send']);
                $myConversation->save();
                $myConversation->link($newMessage);

            }
            //Si le message a été supprimé par tous les destinataires on le supprime
            if (true == $is_deleted && $row['is_deleted']) {
                $newMessage->delete();
            }
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}

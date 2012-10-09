<?php

namespace BNS\App\MessagingBundle\Messaging;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Buzz\Message\Form\FormUpload;

/**
 * @author ROUAYS Pierre-Luc
 * Classe permettant la gestion des emails
 */
class BNSMailManager
{

    protected $api;
    protected $request;
    protected $resource_files_dir;

    public function __construct($api, $request, $resource_files_dir)
    {
        $this->api = $api;
        $this->request = $request;
        $this->resource_files_dir = $resource_files_dir;
    }

    /**
     * Liste des dossiers de l'utilisateur authentifié
     * @return type 
     */
    public function getMailboxFolder()
    {  
        $folderResponse = $this->api->send('get_folders');
        
        return $folderResponse;
    }

    /**
     * Retourne l'aperçu de tous les messages d'un dossier
     * @param type $folderFunctionalName : dossier courant
     * @param type $page : page en cours dans le dossier
     * @return type 
     */
    public function getMailsHeaderList($folderFunctionalName, $page = 1)
    {
        $params['FOLDER'] = $folderFunctionalName;
        $params['PAGE'] = $page;
        $params['SORTBY'] = 8;//Classé par état lu/non-lu
        $params['NBDISPLAYMSG'] = 10;//Messages à afficher
        
        
        $mailsHeaderList = $this->api->send('list_messages', array('form' => $params));
        
        $nb = ceil($mailsHeaderList->nbMails/$params['NBDISPLAYMSG']);
        if($nb == 0)
        {
            $nb++;
        }
        
        $mailsHeaderList->nbPages = $nb;
        
        return $mailsHeaderList;
    }
    
 
    /**
     * Retourne l'élement message qui a cet Id dans ce dossier
     * @param type $msgId
     * @param type $parentFolder
     * @return type 
     */
    public function getMail($msgId, $folderFunctionalName)
    {
        $params['IDMSG'] = $msgId;
        $params['FOLDER'] = $folderFunctionalName;
        
        $mail = $this->api->send('get_mail', array('form' => $params));
        
        return $mail;
    }
    
    /**
     * Envoie du message avec les informations nécessaires
     * @param type $message
     * @param type $subject
     * @param type $to
     * @param type $id 
     */
    public function sendMail($message, $subject, $to, $id = null, $user = null)
    {
        $params['msg_body'] = $message;
        $params['msg_subject'] = $subject;
        $params['msg_to'] = $to;
        $params['msg_savecopy'] = 1;//Sauvegarder en SF_OUTBOX
        
        if($id != null)
        {
            $params['DRAFT_ID'] = $id;
        }
        
        $reponse = $this->api->send('send_mail', array('form' => $params), $user);

        //Si des PJ ont été envoyées, libérer tous les espaces de composition
        $this->api->send('free_all_composition_space', array(), $user);

        return $reponse;
    }
    
        /**
     * Envoie du message avec les informations nécessaires
     * @param type $message
     * @param type $subject
     * @param type $to
     * @param type $id 
     */
    public function saveAsDraft($message, $subject, $to, $id = null)
    {
        $params['msg_body'] = $message;
        $params['msg_subject'] = $subject;
        $params['msg_to'] = $to;
        
        if($id != null)
        {
            $params['IDMSG'] = $id;
        }
        
        $reponse = $this->api->send('save_as_draft', array('form' => $params));
        
        return $reponse;
    }
    
    
    /**
     *
     * @param type $folder
     * @param type $msgUid
     * @return type 
     */
    public function deleteSingleMail($folder, $msgUid)
    {
        $params['FOLDER'] = $folder;
        $params['uids'] = $msgUid;
        $params['SAVE_INTO_TRASH'] = 1;
        
        $reponse = $this->api->send('delete_mails', array('form' => $params));
        
        return $reponse;
    }
    
    /**
     * Récupère une pièce jointe d'un email reçu et la stock temporairement sur le disque
     * @param type $url
     * @param type $name
     * @param type $tempDir
     * @return type 
     */
    public function getAttachment($url, $name, $tempDir)
    {
        $resultPath = $this->api->downloadFile($url, $name, $tempDir);

        return $resultPath;
    }
    
    /**
     *
     * @param type $url
     * @param type $name
     * @param type $tempDir
     * @return type 
     */
    public function addAttachment($filename, $filepath, $user = null)
    {
        $route['_nsyn'] = "x";
        $params['FILENAME'] = $filename;
        
        $formUpload = new FormUpload($this->resource_files_dir.DIRECTORY_SEPARATOR.$filepath);
        $formUpload->setFilename($filename);

        //Ajouter le flux binaire
        $params['filenameurl'] = $formUpload;

        //Faire la requête API addAttachment.json
        $response = $this->api->send('add_attachment', array('form' => $params, 'route' => $route), $user);

        $currentUpload = $this->api->send('check_attachment_upload', array(), $user);

        while ($currentUpload->uploadProgress < 100)
        {
            //Wait for attachment to be upload
            $currentUpload = $this->api->send('check_attachment_upload', array(), $user);
        }
        
        return $response;
    }
    
    
    /**
     * Retourne l'aperçu de tous les messages d'un dossier
     * @param type $folderFunctionalName : dossier courant
     * @param type $page : page en cours dans le dossier
     * @return type 
     */
    public function searchMailsList($query, $page = 1)
    {
        $params['object'] = $query;
        $params['body'] = $query;
        $params['sender'] = $query;
        $params['searchFor'] = $query;
        $params['recipients'] = $query;
        $params['allFolder'] = "selected";
        $params['displayPreview'] = true;
        
        $params['PAGE'] = $page;
        $params['SORTBY'] = 8;//Classé par état lu/non-lu
        $params['NBDISPLAYMSG'] = 10;//Messages à afficher
        
        
        $response = $this->api->send('search_mails', array('form' => $params));
        
        $nb = ceil($response->nbMails/$params['NBDISPLAYMSG']);
        if($nb == 0)
        {
            $nb++;
        }
        
        $response->nbPages = $nb;
        
        return $response;
    }
    
    /**
     * Pour la modération : se connecter en tant que 
     */
    public function invalidCurrentSession()
    {
        //Si on envoie en tant que : invalider la session
        $this->api->closeAuthenticateAs();
    }

}
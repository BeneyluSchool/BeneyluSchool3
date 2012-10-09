<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\MessagingBundle\Form\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\MessagingBundle\API\Model\Mail;
use BNS\App\MessagingBundle\Model\ModeratedMessage;
use BNS\App\MessagingBundle\Model\ModeratedMessageQuery;
use BNS\App\MessagingBundle\Model\MailAttachmentQuery;
use BNS\App\MessagingBundle\Model\MailAttachment;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkUser;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\CoreBundle\Model\UserQuery;

class FrontAjaxController extends Controller
{	
	 
        /**
        * Routing en annotation
        * @Route("/liste-emails/{folderFunctionalName}/{page}", name="BNSAppMessagingBundle_frontajax_list_emails", options={"expose"=true})
        * @Template()
        */
        public function listMailsAction($folderFunctionalName, $page)
        {
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                $mail_manager = $this->get('bns.mail_manager');

                //Récupérer la liste des headers emails
                $mailsHeaderList = $mail_manager->getMailsHeaderList($folderFunctionalName, $page);
                
                return array('emailsList' => $mailsHeaderList->mailHeader, 'page' => $page, 'nbPages' => $mailsHeaderList->nbPages);
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));

        }  
        
        /**
        * Routing en annotation
        * @Route("/recherche-emails/{query}/{page}", name="BNSAppMessagingBundle_frontajax_search_emails", options={"expose"=true})
        * @Template()
        */
        public function searchListMailsAction($query, $page)
        {
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                $mail_manager = $this->get('bns.mail_manager');

                //Récupérer la liste des headers emails
                $mailsHeaderList = $mail_manager->searchMailsList($query, $page);
                
                return array('emailsList' => $mailsHeaderList->mailHeader, 'page' => $page, 'nbPages' => $mailsHeaderList->nbPages, 'query' => $query);
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));

        } 
               
        /**
	 * Routing en annotation	
	 * @Route("/message/{messageId}/{folderFunctionalName}", name="BNSAppMessagingBundle_frontajax_message", options={"expose"=true})
	 * @Template()
	 */
	public function messageAction($messageId, $folderFunctionalName)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                $mail_manager = $this->get('bns.mail_manager');
                

                //Récupération de l'email 
                $mailResponse = $mail_manager->getMail($messageId, $folderFunctionalName);
                
                //Récupérer l'objet mail attachment pour voir les resources déjà téléchargées
                $attachement = MailAttachmentQuery::create()->filterByMailFolder($folderFunctionalName)
                        ->filterByMailId($messageId)
                        ->filterByUserId($right_manager->getUserSessionId())
                        ->findOne();
                
                if($attachement != null)
                {
                    $finalPjs = array();
                    
                    if($mailResponse->mail->attachment != null)
                    {
                        foreach($mailResponse->mail->attachment as $att)
                        {
                            $add = true;
                            foreach ($attachement->getResourceAttachments() as $resourceAttachment) {
                                $label = $resourceAttachment->getLabel();
                                if($label == $att->attachmentName)
                                {
                                    $add = false;
                                }
                            }
                            if($add == true)
                            {
                                $finalPjs[] = $att;
                            }

                        }
                    }
                    
                    $mailResponse->mail->attachment = $finalPjs;
                }
                
                $mail = $mailResponse->mail;
                $toEmails = explode(',', $mail->header->to);

                return array("email" => $mail, 'toEmails' => $toEmails, 'att' => $attachement);
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));
	}
        
    /**
    * @Route("/nouveau-message/{to}/{subject}/{msgId}", defaults={"to" = null, "subject" = null, "msgId" = null}, name="BNSAppMessagingBundle_frontajax_new_message", options={"expose"=true})
    * @Template()
    */
    public function newMessageAction($to, $subject, $msgId = null)
    {
        //Nouveau message ou brouillon
        $right_manager = $this->get('bns.right_manager');

        //Vérification du / des droits (everywhere : pas de contexte) 
        if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

        $email = new EmailType();
        $resources = null;
        if($msgId == null)
        {
            $email->to = html_entity_decode($to);
            if($subject != null)
            {
                $email->subject = "RE : ".html_entity_decode($subject);   
            }
            else
            {
                $email->subject = html_entity_decode($subject);   
            }
        }
        else
        {
            $mail_manager = $this->get('bns.mail_manager');
            $mailResponse = $mail_manager->getMail($msgId, 'SF_DRAFT');
            $email->message = $mailResponse->mail->message;
            $email->to = html_entity_decode($mailResponse->mail->header->to);
            $email->subject = html_entity_decode($mailResponse->mail->header->subject);
            $email->id = $msgId;
            
            //Récupérer le MailAttachment
            $attachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                ->filterByMailId($msgId)
                ->filterByMailFolder('SF_DRAFT')
                ->findOne();
            
            //Si il existe ajouter les resources id dans $resources
            if($attachment != null)
            {
                $resources = $attachment->getResourceAttachments();
            }
        }
        
        $emailform = $this->createForm(new EmailType(), $email)->createView();
        
        if($resources != null)
        {
            return array(
                'email_form' => $emailform,
                'resources' => $resources
            );
        }
        
        return array(
            'email_form' => $emailform
        );
    }
            
    /**
    * @Route("/edit-message/{msgId}", name="BNSAppMessagingBundle_frontajax_edit_message", options={"expose"=true})
    * @Template()
    */
    public function editMessageAction($msgId)
    {
        //Reprise d'un brouillon
        $right_manager = $this->get('bns.right_manager');

        //Vérification du / des droits (everywhere : pas de contexte) 
        if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

        $response = $this->forward('BNSAppMessagingBundle:FrontAjax:newMessage', array(
            'to' => null,
            'subject' => null,
            'msgId' => $msgId,
        ));
        
        return $response;
    }
        
    /**
     * Ajout d'un nouveau travail pour un/des groupes
     * @param Request $request
     * @Route("envoie-message", name="BNSAppMessagingBundle_frontajax_send_email")
     */
    public function sendMailAction(Request $request)
    {   
        $right_manager = $this->get('bns.right_manager');
        //Vérification du / des droits (everywhere : pas de contexte) 
        if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));
        
        $email = new Mail();
        $emailType = new EmailType();
        $form = $this->createForm(new EmailType(), new EmailType());
        if ($request->getMethod() == 'POST') {

            $form->bindRequest($request);
            if ($form->isValid()) {
                
                //Transformer le tableau en objet
                $emailType = $form->getData();
                
                $mail_manager = $this->get('bns.mail_manager');
                
                //Récupérer les PJ sélectionnées
                $attachmentIds = $this->get('bns.resource_manager')->getAttachmentsId($request);
                
                //3 cas possibles : brouillon (create mail attachment), envoi (get flux et envoyer via api), modération (set attachment to moderated messages)
                
                if($emailType->mustSave == "true")
                {
                    //Save as draft
                    $response = $mail_manager->saveAsDraft($emailType->message, $emailType->subject, $emailType->to, $emailType->id);
                    if($emailType->id == null)
                    {
                        //Ajout du mailAttachment à la réponse
                        $mailAttachment = new MailAttachment();
                        $mailAttachment->setUserId($right_manager->getUserSessionId());
                        $mailAttachment->setMailId($response->id->msgId);
                        $mailAttachment->setMailFolder('SF_DRAFT');
                        $mailAttachment->save();
                        
                        if($attachmentIds != null)
                        {
                            foreach ($attachmentIds as $idAtt) {
                                $mailAttachment->addResourceAttachment($idAtt);
                            }
                            $mailAttachment->save();
                        }
                        else
                        {
                            $mailAttachment->delete();
                        }
                    }
                    else
                    {
                        //Changement de l'id message du mailAttachment 
                        $mailAttachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                ->filterByMailId($emailType->id)
                                ->filterByMailFolder('SF_DRAFT')
                                ->findOne();
                        
                        if($mailAttachment == null)
                        {
                            //Ajout du mailAttachment à la réponse
                            $mailAttachment = new MailAttachment();
                            $mailAttachment->setUserId($right_manager->getUserSessionId());
                            $mailAttachment->setMailId($response->id->msgId);
                            $mailAttachment->setMailFolder('SF_DRAFT');
                            $mailAttachment->save();
                        }
                        
                        //Changement de l'id message du mailAttachment
                        $mailAttachment->setMailId($response->id->msgId);
                        $mailAttachment->save();
                        
                        $mailAttachment->deleteAllResourceAttachments();
                        
                        if($attachmentIds != null)
                        {
                            foreach ($attachmentIds as $idAtt) {
                                $mailAttachment->addResourceAttachment($idAtt);
                            }
                            $mailAttachment->save();
                        }
                        else
                        {
                            $mailAttachment->delete();
                        }
                        
                    }
                }
                else
                {
                    $has_classroom_contacts = false;
                    $has_external_contacts = false;
                    
                    //Test des emails internes/externes
                    $contacts = explode(',', $emailType->to);
                    
                    foreach ($contacts as $contact) 
                    {
                        if(strpos($contact, "@".$this->container->getParameter('mail_domain')))//Interne
                        {
                            $has_classroom_contacts = true;
                        }
                        else if(strpos($contact, '@'))//Externe
                        {
                            $has_external_contacts = true;
                        }
                    }
                    
                    //Si il n'y a pas de modération du tout
                    if($right_manager->hasRightSomeWhere('MESSAGING_NO_EXTERNAL_MODERATION') && $right_manager->hasRightSomeWhere('MESSAGING_NO_GROUP_MODERATION'))
                    {
                        //Envoie des PJ
                        if($attachmentIds != null)
                        {
                            foreach ($attachmentIds as $singleId) {
                                $currentResource = ResourceQuery::create()->findOneById($singleId);
                                $path = $currentResource->getFilePath();
                                $label = $currentResource->getLabel();
                                $mail_manager->addAttachment($label, $path);
                            }
                        }
                        
                        //Envoyer
                        $response = $mail_manager->sendMail($emailType->message, $emailType->subject, $emailType->to, $emailType->id);
                        
                        //Supprimer le mailAttachment si il existe
                        if($emailType->id != null)
                        {
                            $mailAttachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                ->filterByMailId($emailType->id)
                                ->filterByMailFolder('SF_DRAFT')
                                ->findOne();
                        
                            if($mailAttachment != null)
                            {
                                $mailAttachment->delete();
                            }
                        }
                    }
                    //Si il n'y a pas de modération externe
                    else if($right_manager->hasRightSomeWhere('MESSAGING_NO_EXTERNAL_MODERATION'))
                    {
                        //Si il y a des contacts internes : moderation
                        if($has_classroom_contacts)
                        {
                            $moderatedMessage = new ModeratedMessage();
                            if($emailType->id != null)
                            {   
                                //Supprimer le brouillon
                                $response = $mail_manager->deleteSingleMail('SF_DRAFT', $emailType->id);
                                //Récupérer le MailAttachment
                                $attachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                    ->filterByMailId($emailType->id)
                                    ->filterByMailFolder('SF_DRAFT')
                                    ->findOne();

                                //Si il existe le supprimer après suppression
                                if($attachment != null)
                                {
                                    $attachment->delete();
                                }
                            }
                            $moderatedMessage->setUserId($right_manager->getUserSessionId());
                            $moderatedMessage->setMailSubject($emailType->subject);
                            $moderatedMessage->setMailContent($emailType->message);
                            $moderatedMessage->setMailTo($emailType->to);
                            $moderatedMessage->save();
                            
                            //Lier les resources au moderated message
                            if($attachmentIds != null)
                            {
                                foreach ($attachmentIds as $singleId) {
                                    $moderatedMessage->addResourceAttachment($singleId);   
                                }
                                $moderatedMessage->save();
                            }
                        }
                        //Sinon envoyer 
                        else
                        {
                            //Envoie des pj
                            if($attachmentIds != null)
                            {
                                foreach ($attachmentIds as $singleId) {
                                    $currentResource = ResourceQuery::create()->findOneById($singleId);
                                    $path = $currentResource->getFilePath();
                                    $label = $currentResource->getLabel();
                                    $mail_manager->addAttachment($label, $path);
                                }
                            }
                            $response = $mail_manager->sendMail($emailType->message, $emailType->subject, $emailType->to, $emailType->id);
                            
                            //Supprimer le mailAttachment si il existe
                            if($emailType->id != null)
                            {
                                $mailAttachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                    ->filterByMailId($emailType->id)
                                    ->filterByMailFolder('SF_DRAFT')
                                    ->findOne();

                                if($mailAttachment != null)
                                {
                                    $mailAttachment->delete();
                                }
                            }
                        }
                    }
                    //Si il n'y a pas de modération interne
                    else if($right_manager->hasRightSomeWhere('MESSAGING_NO_GROUP_MODERATION'))
                    {
                        //Si il y a des contacts externes : moderation
                        if($has_external_contacts)
                        {
                            $moderatedMessage = new ModeratedMessage();
                            if($emailType->id != null)
                            {
                                //Supprimer le brouillon
                                $response = $mail_manager->deleteSingleMail('SF_DRAFT', $emailType->id);
                                //Récupérer le MailAttachment
                                $attachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                    ->filterByMailId($emailType->id)
                                    ->filterByMailFolder('SF_DRAFT')
                                    ->findOne();

                                //Si il existe le supprimer après suppression
                                if($attachment != null)
                                {
                                    $attachment->delete();
                                }
                            }
                            $moderatedMessage->setUserId($right_manager->getUserSessionId());
                            $moderatedMessage->setMailSubject($emailType->subject);
                            $moderatedMessage->setMailContent($emailType->message);
                            $moderatedMessage->setMailTo($emailType->to);
                            $moderatedMessage->save();
                            //Lier les resources au moderated message
                            if($attachmentIds != null)
                            {
                                foreach ($attachmentIds as $singleId) {
                                    $moderatedMessage->addResourceAttachment($singleId);   
                                }
                                $moderatedMessage->save();
                            }
                        }
                        //Sinon envoyer 
                        else
                        {
                            //Envoie des PJ
                            if($attachmentIds != null)
                            {
                                foreach ($attachmentIds as $singleId) {
                                    $currentResource = ResourceQuery::create()->findOneById($singleId);
                                    $path = $currentResource->getFilePath();
                                    $label = $currentResource->getLabel();
                                    $mail_manager->addAttachment($label, $path);
                                }
                            }
                            $response = $mail_manager->sendMail($emailType->message, $emailType->subject, $emailType->to, $emailType->id);
                            //Supprimer le mailAttachment si il existe
                            if($emailType->id != null)
                            {
                                $mailAttachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                    ->filterByMailId($emailType->id)
                                    ->filterByMailFolder('SF_DRAFT')
                                    ->findOne();

                                if($mailAttachment != null)
                                {
                                    $mailAttachment->delete();
                                }
                            }
                        }
                    }
                    //Si il y a toujours modération
                    else
                    {
                        //Moderation
                        $moderatedMessage = new ModeratedMessage();
                        if($emailType->id != null)
                        {
                            //Supprimer le brouillon
                            $response = $mail_manager->deleteSingleMail('SF_DRAFT', $emailType->id);
                            
                            //Récupérer le MailAttachment
                            $attachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                                ->filterByMailId($emailType->id)
                                ->filterByMailFolder('SF_DRAFT')
                                ->findOne();

                            //Si il existe le supprimer après envoie
                            if($attachment != null)
                            {
                                $attachment->delete();
                            }
                        }
                        $moderatedMessage->setUserId($right_manager->getUserSessionId());
                        $moderatedMessage->setMailSubject($emailType->subject);
                        $moderatedMessage->setMailContent($emailType->message);
                        $moderatedMessage->setMailTo($emailType->to);
                        $moderatedMessage->save();
                        //Lier les resources au moderated message
                        if($attachmentIds != null)
                        {
                            foreach ($attachmentIds as $singleId) {
                                $moderatedMessage->addResourceAttachment($singleId);   
                            }
                            $moderatedMessage->save();
                        }
                    }
                }
                
            }
            else
            {
                throw new \HttpException('Une erreur est survenue durant la validation !');
            }
        }

        return new \Symfony\Component\HttpFoundation\Response();
    }
    
        /**
	 * Routing en annotation	
	 * @Route("/message/supprimer/{messageId}/{folderFunctionalName}", name="BNSAppMessagingBundle_frontajax_delete_message", options={"expose"=true})
	 */
	public function deleteMessageAction($messageId, $folderFunctionalName)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                $mail_manager = $this->get('bns.mail_manager');
                

                //Récupération de l'email 
                $reponse = $mail_manager->deleteSingleMail($folderFunctionalName, $messageId);
                
                //Récupérer le MailAttachment
                $attachment = MailAttachmentQuery::create()->filterByUserId($right_manager->getUserSessionId())
                    ->filterByMailId($messageId)
                    ->filterByMailFolder($folderFunctionalName)
                    ->findOne();

                //Si il existe le supprimer après envoie
                if($attachment != null)
                {
                    $attachment->delete();
                }

                return new \Symfony\Component\HttpFoundation\Response();
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));
	}
        

                
        /**
	 * Routing en annotation	
	 * @Route("/message/piece-jointe", name="BNSAppMessagingBundle_frontajax_get_attachment", options={"expose"=true})
	 */
	public function getAttachmentAction(Request $request)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                $mail_manager = $this->get('bns.mail_manager');
                
                //Récupération des params nécessaires à l'enregistrement d'une resource
                $url = $request->request->get('url');
                $name = $request->request->get('name');
                $msgId = $request->request->get('msgId');
                $folder = $request->request->get('folder');
                $user = $right_manager->getUserSession();
                
                //Récupération de l'extension du fichier
                $tempArray = explode('.', $name);
                $extension = $tempArray[count($tempArray)-1];
                
                $resource_creator = $this->get('bns.resource_creator');
                
                
                $tempDir = $resource_creator->getTempDir().DIRECTORY_SEPARATOR;
                
                //Récupération de l'attachment
                $filePath = $mail_manager->getAttachment($url, $name, $tempDir);
                
                //Créer un objet de type : mailAttachment avec les informations de la requête
                //Récupérer ou créer
                $attachment = MailAttachmentQuery::create()->filterByUserId($user->getId())
                        ->filterByMailId($msgId)
                        ->filterByMailFolder($folder)
                        ->findOne();
                if($attachment == null)
                {
                    $attachment = new MailAttachment();
                    $attachment->setUserId($user->getId());
                    $attachment->setMailId($msgId);
                    $attachment->setMailFolder($folder);
                    $attachment->save();
                }
                
                //Lui Ajouter une resource qui représente ce fichier lié à ce mail attachment 
		$resource_creator->setUser($this->get('bns.right_manager')->getModelUser());
		
		$resource_creator->initFileUploader();
                
                try
                {
                    //verification des droits
                    $resource = $resource_creator->createResourceFromFile($filePath, $attachment->getId().'-'.$name, $extension, $name);

                    //Lier la PJ à l'objet BDD
                    $attachment->save();
                    $attachment->addResourceAttachment($resource->getId());

                    //Ajouter la resource dans le dossier utilisateur
                    $userFolder = ResourceLabelUserQuery::create()->filterByUserId($user->getId())
                            ->filterByTreeLevel(0)
                            ->findOne();
                    $linkResourceFolder = new ResourceLinkUser();
                    $linkResourceFolder->setResourceId($resource->getId());
                    $linkResourceFolder->setResourceLabelUserId($userFolder->getId());
                    $linkResourceFolder->save();

                }
                catch (\Exception $e)
                {
                    unlink($filePath);   
                    throw $e;
                }

                return new \Symfony\Component\HttpFoundation\Response();
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));
	}
        
    
        /**
	 * Routing en annotation	
	 * @Route("/emails", name="BNSAppMessagingBundle_frontajax_get_emails", options={"expose"=true})
	 */
	public function getMailsAction()
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');
                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));

                //Récupération de la liste des id users
		$userIds = $this->getRequest()->get('user_ids');
                $userIds = (null != $userIds ? $userIds : '');
                
                $arrayUserIds = explode(',', $userIds);
                $response = "";
                
                foreach ($arrayUserIds as $user_id) {
                    $user = UserQuery::create()->findOneById($user_id);
                    if($user != null)
                    {
                        $response = $response."\"".$user->getFullName()."\" <".$user->getSlug()."@".$this->container->getParameter('mail_domain').">,";
                    }
                }

                return new \Symfony\Component\HttpFoundation\Response($response);
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_front'));
	}
        
}


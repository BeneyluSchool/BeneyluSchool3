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
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;

/**
 * @Route("/gestion")
 */

class BackAjaxController extends Controller
{	
	 
        /**
        * Routing en annotation
        * @Route("/liste-emails/{page}", defaults={"page" = 1}, name="BNSAppMessagingBundle_backajax_list_emails", options={"expose"=true})
        * @Template()
        */
        public function listMailsAction($page)
        {
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRight('MESSAGING_ACCESS_BACK'));
                
                //Récupérer le groupe courrant de l'utilisateur
                $context = $right_manager->getContext();
                $groupId = $context['id'];
                $group = GroupQuery::create()->findOneById($groupId);
                
                //Récupérer la liste des utilisateurs du groupe
                $group_manager = $this->get('bns.group_manager');
                $group_manager->setGroup($group);
                $userIds = array();
                $users = $group_manager->getUsers();
                
                foreach ($users as $user) {
                    $userIds[] = $user['user_id'];
                }
                
                $nbDisplay = 10;
                
                //Récupérer la liste des messages modérés pour ces utilisateurs
                $moderatedMessages = ModeratedMessageQuery::create()->findByUsersIds($userIds, $nbDisplay, ($page*10-10));
                
                $count = ModeratedMessageQuery::create()->countByUsersIds($userIds);
                
                $nb = ceil($count/$nbDisplay);
                if($nb == 0)
                {
                    $nb++;
                }
                
                return array('moderatedMessages' => $moderatedMessages, 'page' => $page, 'nbPages' => $nb);
            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));

        }  
        
        
        
                /**
	 * Routing en annotation	
	 * @Route("/message/{messageId}", name="BNSAppMessagingBundle_backajax_message", options={"expose"=true})
	 * @Template()
	 */
	public function messageAction($messageId)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS_BACK'));

                //Récupérer la liste des messages modérés pour ces utilisateurs
                $moderatedMessage = ModeratedMessageQuery::create()->findOneById($messageId);
                
                $toEmails = explode(',', $moderatedMessage->getMailTo());
                
                return array('moderatedMessage' => $moderatedMessage, 'toEmails' => $toEmails);

            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));
	}
        
        /**
	 * Routing en annotation	
	 * @Route("/valider-message/{messageId}", name="BNSAppMessagingBundle_backajax_validate_message", options={"expose"=true})
	 * @Template()
	 */
	public function validateMessageAction($messageId)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS_BACK'));

                //Récupérer la liste des messages modérés pour ces utilisateurs
                $moderatedMessage = ModeratedMessageQuery::create()->findOneById($messageId);
     
                $mail_manager = $this->get('bns.mail_manager');
                
                //Récupérer les PJ sélectionnées
                $attachments = $moderatedMessage->getResourceAttachments();
                
                
                if($moderatedMessage != null)
                {
                    //Se déconnecter du professeur
                    $mail_manager->invalidCurrentSession();
                    
                    //Ajout de PJ en tant que
                    if($attachments != null)
                    {
                        foreach ($attachments as $currentResource) {
                            $path = $currentResource->getFilePath();
                            $label = $currentResource->getLabel();
                            $mail_manager->addAttachment($label, $path, $moderatedMessage->getUser());
                        }
                    }
                    //Envoyer ici le message en tant que
                    $mail_manager->sendMail($moderatedMessage->getMailContent(), $moderatedMessage->getMailSubject(), $moderatedMessage->getMailTo(), null, $moderatedMessage->getUser());
                    
                    $moderatedMessage->delete();
                    
                    //Se déconnecter de l'utilisateur modéré
                    $mail_manager->invalidCurrentSession();
                }
                
                return new \Symfony\Component\HttpFoundation\Response();

            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));
	}
        
        /**
	 * Routing en annotation	
	 * @Route("/refuser-message/{messageId}", name="BNSAppMessagingBundle_backajax_refuse_message", options={"expose"=true})
	 * @Template()
	 */
	public function refuseMessageAction($messageId)
	{	
            if($this->getRequest()->isXmlHttpRequest())
            {
                $right_manager = $this->get('bns.right_manager');

                //Vérification du / des droits (everywhere : pas de contexte) 
                if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS_BACK'));

                //Récupérer la liste des messages modérés pour ces utilisateurs
                $moderatedMessage = ModeratedMessageQuery::create()->findOneById($messageId);
                
                if($moderatedMessage != null)
                {
                    $moderatedMessage->delete();
                }
                
                return new \Symfony\Component\HttpFoundation\Response();

            }

            return $this->redirect($this->generateUrl('BNSAppMessagingBundle_back'));
	}

}


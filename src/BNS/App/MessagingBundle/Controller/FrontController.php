<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use BNS\App\MessagingBundle\Controller\CommonController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

class FrontController extends CommonController 
{	
	/**
	 * Routing en annotation	
	 * @Route("", name="BNSAppMessagingBundle_front")
	 * @Template()
	 * @RightsSomeWhere("MESSAGING_ACCESS")
	 */
	public function indexAction()
	{
		if($this->getMessagingType() == 'light'){
			return $this->render('BNSAppMessagingBundle:Front/Light:index.html.twig', array('type' => $this->getMessagingType(),'content' => 'inbox','firstRequest' => true));	
		}elseif($this->getMessagingType() == 'real'){
			return $this->render('BNSAppMessagingBundle:Front:index.html.twig', array('type' => $this->getMessagingType(),'content' => 'inbox'));	
		}
	}
	        
        /**
	 * Render template sidebar front
	 * @Template()
	 */
        public function sidebarAction($currentFolderId)
        {
            $right_manager = $this->get('bns.right_manager');
            
            //Vérification du / des droits (everywhere : pas de contexte) 
            if($right_manager->forbidIfHasNotRightSomeWhere('MESSAGING_ACCESS'));
            
            $mail_manager = $this->get('bns.mail_manager');
            
            //Récupérer la liste des dossiers
            $folderResponse = $mail_manager->getMailboxFolder();
            
            //Dossiers de base de la boite mail
            $folderSystems = $folderResponse->folderSystem;
            
            //On récupère la boite de réception, messages envoyés, brouillons et supprimés pour le style spécifique
            $received = null;
            $sent = null;
            $drafted = null;
            $deleted = null;
            foreach($folderSystems as $folder)
            {
                switch ($folder->functionalName) {
                    case "SF_INBOX":
                        $received = $folder;
                        break;
                    case "SF_OUTBOX":
                        $sent = $folder;
                        break;
                    case "SF_DRAFT":
                        $drafted = $folder;
                        break;
                    case "SF_TRASH":
                        $deleted = $folder;
                        break;
                }
            }
            
            //On récupère la liste des dossiers perso simplement
            $folderUsers = $folderResponse->folderUser;
            //On les réorganisent avec les parents
            $folderUsersOrganized = $this->reorganizeFolders($folderUsers);
            
            return array('received' => $received, 'sent' => $sent, 'drafted' => $drafted, 'deleted' => $deleted, 'userFolders' => $folderUsersOrganized);
        }  
        
        /**
	 * Render template subfolders front
	 * @Template()
	 */
        public function subfoldersAction($parent)
        {
            return array('folder' => $parent);
        }
        
        
        /**
         * Méthode permettant de réorganiser les dossiers avec les dossiers parents
         * @param type $folders
         * @return type M
         */
        private function reorganizeFolders($folders)
        {
            $result = array();
            foreach ($folders as $folder) 
            {
                if($folder->fatherFunctionalName != 'SF_INBOX')
                {
                    foreach ($folders as $parentFolder) 
                    {
                        if($folder->fatherFunctionalName == $parentFolder->functionalName)
                        {
                            $parentFolder->childs[] = $folder;
                        }
                    }
                }
                else
                {
                    $result[] = $folder;
                }
            }
            
            return $result;
        }
        
}


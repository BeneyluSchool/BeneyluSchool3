<?php

namespace BNS\App\InfoBundle\Controller;

use BNS\App\InfoBundle\Form\Type\SponsorshipType;
use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\InfoBundle\Form\Type\ContactType;
use BNS\App\InfoBundle\Model\Contact;
use BNS\App\InfoBundle\Model\Sponsorship;
use BNS\App\InfoBundle\Model\SponsorshipPeer;
use BNS\App\InfoBundle\Model\SponsorshipQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

class FrontController extends CommonController
{

    protected function mergeAnnouncementAndItems($feedItems,$announcementType)
    {
        $announcement = array();
        foreach($feedItems as $item)
        {
            $announcement[] = $item->get_link();
        }
        $announcements = AnnouncementQuery::create()->filterByType($announcementType)->filterByLabel($announcement)->orderByCreatedAt(\Criteria::DESC)->find();
        $i = 0;
        foreach($announcements as $announcement)
        {
            $announcement->setItemFeed($this->get('bns.info_manager')->getItem($i));
            $i++;
        }
        return $announcements;
    }

    protected function getAnnouncementNotReadByType()
    {
        //Pour la colonne de gauche
        //$announcements = AnnouncementQuery::create()->filterByActivated()->leftJoin()
    }

    /**
     * @Route("/", name="BNSAppInfoBundle_front")
     * @Route("/", name="BNSAppInfoBundle_back")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function indexAction()
    {

        $announcements = AnnouncementQuery::create()->filterByActivated()->filterByType('CUSTOM')->orderById(\Criteria::DESC)->find();
        $blogItems = $this->get('bns.info_manager')->getItems($this->container->getParameter('bns_app_info_feeds_blog'),0,$this->container->getParameter('bns_app_info_nb_announcements_index_blog'));
        $blogAnnouncements = $this->mergeAnnouncementAndItems($blogItems,'BLOG');
        $onPublicVersion = $this->get('bns.right_manager')->getCurrentGroupManager()->isOnPublicVersion();
        return array(
            'announcements'     =>      $announcements,
            'blogAnnouncements' =>      $blogAnnouncements,
            'user'              =>      $this->get('bns.right_manager')->getModelUser(),
            'section'           =>      'index',
            'queryString'       =>      '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium,
            'premiumInformation' =>      $this->get('bns.paas_manager')->getPremiumInformations($this->get('bns.right_manager')->getCurrentGroup()),
            'subscriptionDate' => $this->get('bns.right_manager')->getCurrentGroup()->getRegistrationDate(),
            'onPublicVersion' => $onPublicVersion
        );
    }

    /**
     * @Route("/communaute", name="BNSAppInfoBundle_front_community")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function communityAction()
    {
        return array(
            'twitterFeed'  => $this->container->getParameter('bns_app_info_feeds_twitter'),
            'forumItems'   => $this->get('bns.info_manager')->getItems($this->container->getParameter('bns_app_info_feeds_forum'),0,$this->container->getParameter('bns_app_info_nb_announcements_index_forum')),
            'section'      => 'community',
            'queryString'  => '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium
        );
    }

    /**
     * @Route("/nouveautes", name="BNSAppInfoBundle_front_updates")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function updatesAction()
    {
        $updateItems = $this->get('bns.info_manager')->getItems($this->container->getParameter('bns_app_info_feeds_updates'),0,$this->container->getParameter('bns_app_info_nb_announcements_index_forum'));
        $updateAnnouncements = $this->mergeAnnouncementAndItems($updateItems,'UPDATE');
        return array(
            'updateAnnouncements'   => $updateAnnouncements,
            'section'               => 'updates',
            'user'              =>      $this->get('bns.right_manager')->getModelUser(),
            'queryString'  => '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium
        );
    }

    /**
     * @Route("/centre-d-aide", name="BNSAppInfoBundle_front_help")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function helpAction()
    {
        $contact = new Contact();
        $form = $this->createForm(new ContactType(),$contact);

        if ($this->getRequest()->getMethod() == 'POST'){
            $form->bind($this->getRequest());
            if ($form->isValid() && $contact->getDescription() != ""){
                $contact->setUserId($this->get('bns.right_manager')->getModelUser()->getId());
                $contact->setGroupId($this->get('bns.right_manager')->getCurrentGroupId());
                $this->get('bns.info_manager')->newContactProcess($contact);
                $this->get('session')->getFlashBag()->add('success',$this->get('translator')->trans("FLASH_MESSAGE_SEND_SUCCESS", array(), 'INFO'));
                return $this->redirect($this->generateUrl("BNSAppInfoBundle_front_help"));
            }else{
                $this->get('session')->getFlashBag()->add('error',$this->get('translator')->trans("FLASH_ENTER_MESSAGE", array(), 'INFO'));
            }
        }
        return array(
            'section'  => 'help',
            'form'     => $form->createView(),
            'queryString'  => '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium
        );
    }

    /**
     * @Route("/abonnement", name="BNSAppInfoBundle_front_subscription")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function subscriptionAction()
    {

        $premiumInformation = $this->get('bns.paas_manager')->getPremiumInformations($this->get('bns.right_manager')->getCurrentGroup());
        $onPublicVersion = $this->get('bns.right_manager')->getCurrentGroupManager()->isOnPublicVersion();
        return array(
            'section'  => 'subscription',
            'premiumInformation' => $premiumInformation,
            'subscriptionDate' => $this->get('bns.right_manager')->getCurrentGroup()->getRegistrationDate(),
            'queryString'       =>      '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium,
            'onPublicVersion' => $onPublicVersion
        );
    }

    /**
     * @Route("/parrainage", name="BNSAppInfoBundle_front_sponsorship")
     * @Template()
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function sponsorshipAction()
    {
        $onPublicVersion = $this->get('bns.right_manager')->getCurrentGroupManager()->isOnPublicVersion();
        if(!$onPublicVersion)
        {
            return $this->redirect($this->generateUrl('BNSAppInfoBundle_front'));
        }

        $sponsorship = new Sponsorship($this->get('translator'));
        $userId = $this->get('bns.right_manager')->getModelUser()->getId();
        $schools = $this->get('bns.right_manager')->getUserManager()->getSimpleGroupsAndRolesUserBelongs(true,3);
        $form = $this->createForm(new SponsorshipType(),$sponsorship);

        if ($this->getRequest()->getMethod() == 'POST'){
            $form->handleRequest($this->getRequest());
            if ($form->isValid()){
                $sponsorship->setFromUserId($userId);
                $available = $sponsorship->isAvailable();
                if($available === true)
                {
                    $sponsorship->setStatus('PENDING');
                    $sponsorship->setSchoolId($schools->getFirst()->getId());
                    $sponsorship->save();
                    $this->get('bns.mailer')->send('SPONSORSHIP_NEW',array('email' => $form->get('email')->getData(), 'sponsor_full_name' => $sponsorship->getUserRelatedByFromUserId()->getFullName()),$form->get('email')->getData());
                    $this->get('session')->getFlashBag()->add('success',$this->get('translator')->trans("FLASH_REFERAL_SUCCESS", array(), 'INFO'));
                }else{
                    switch($available)
                    {
                        /*case 'EXISTS':
                            $this->get('session')->getFlashBag()->add('notice',"Cet email est déjà associé à un utilisateur de Beneylu School.");
                            break;*/
                        case 'SPONSORSHIP_EXISTS':
                            $this->get('session')->getFlashBag()->add('notice',$this->get('translator')->trans("FLASH_EMAIL_ALREADY_HAVE_REFERAL", array(), 'INFO'));
                            break;
                    }
                }
                return $this->redirect($this->generateUrl("BNSAppInfoBundle_front_sponsorship"));
            }
        }
        return array(
            'section'  => 'sponsorship',
            'onPublicVersion' => $onPublicVersion,
            'form' => $form->createView(),
            'sponsorships' => SponsorshipQuery::create()->joinUserRelatedByFromUserId()->findByFromUserId($userId)
        );
    }



}

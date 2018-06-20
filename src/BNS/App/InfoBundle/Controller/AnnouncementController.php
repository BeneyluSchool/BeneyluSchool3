<?php

namespace BNS\App\InfoBundle\Controller;

use BNS\App\InfoBundle\Model\Announcement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

/**
 * @Route("/annonces")
 */

class AnnouncementController extends CommonController
{
    protected function renderTemplate(Announcement $announcement)
    {
        $this->getItemFeed($announcement);
        return $this->render('BNSAppInfoBundle:Block:announcement_' . strtolower($announcement->getType()) . '.html.twig',
            array(
                'announcement' => $announcement,
                'user'         => $this->get('bns.right_manager')->getModelUser(),
                'queryString'  =>      '?utm_source=' . self::$utm_source . '&utm_medium=' . self::$utm_medium
            )
        );
    }

    protected function getItemFeed(Announcement $announcement)
    {
        switch($announcement->getType())
        {
            case 'BLOG':
                $items = $blogItems = $this->get('bns.info_manager')->getItems($this->container->getParameter('bns_app_info_feeds_blog'),0,10);
            break;
            case 'UPDATE':
                $items = $blogItems = $this->get('bns.info_manager')->getItems($this->container->getParameter('bns_app_info_feeds_updates'),0,10);
        }
        if(isset($items))
        {
            foreach($items as $item)
            {
                if($item->get_link() == $announcement->getLabel())
                {
                    $announcement->setItemFeed($item);
                }
            }
        }
    }

    /**
     * @Route("/lire/{slug}", name="BNSAppInfoBundle_announcement_read" , options={"expose"=true})
     * @ParamConverter("announcement")
     * @RightsSomeWhere("NOTIFICATION_ACCESS")
     */
    public function readAction(Announcement $announcement)
    {
        $this->get('bns.right_manager')->forbidIf(!$announcement->isReadable());
        $user = $this->get('bns.right_manager')->getModelUser();
        $announcement->read($user);
        return $this->renderTemplate($announcement);
    }

    /**
     * @Route("/participer/{slug}", name="BNSAppInfoBundle_announcement_participate" , options={"expose"=true})
     * @ParamConverter("announcement")
     * @RightsSomeWhere("NOTIFICATION_ACCESS")
     */
    public function participateAction(Announcement $announcement)
    {
        $this->get('bns.right_manager')->forbidIf(!$announcement->isParticipable());
        $user = $this->get('bns.right_manager')->getModelUser();
        $announcement->participate($user);
        return $this->renderTemplate($announcement);
    }

    /**
     * @Route("/enlever-lire/{slug}", name="BNSAppInfoBundle_announcement_unread" , options={"expose"=true})
     * @ParamConverter("announcement")
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function unreadAction(Announcement $announcement)
    {
        $this->get('bns.right_manager')->forbidIf(!$announcement->isReadable());
        $user = $this->get('bns.right_manager')->getModelUser();
        $announcement->unread($user);
        return $this->renderTemplate($announcement);
    }

    /**
     * @Route("/enelever-participer/{slug}", name="BNSAppInfoBundle_announcement_unparticipate" , options={"expose"=true})
     * @ParamConverter("announcement")
     * @RightsSomeWhere("INFO_ACCESS")
     */
    public function unparticipateAction(Announcement $announcement)
    {
        $this->get('bns.right_manager')->forbidIf(!$announcement->isParticipable());
        $user = $this->get('bns.right_manager')->getModelUser();
        $announcement->unparticipate($user);
        return $this->renderTemplate($announcement);
    }
}

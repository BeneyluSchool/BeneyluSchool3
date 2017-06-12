<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\MainBundle\Model\HomeNew;
use BNS\App\MainBundle\Model\HomeNewQuery;
use BNS\App\MainBundle\Form\Type\HomeNewType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;

use BNS\App\GroupBundle\Controller\CommonController;


/**
 * @Route("/gestion/accueil")
 */

class BackHomeManageController extends CommonController
{
    /*
     * Vérifie les droits d'agir sur une nouvelle
     */
    protected function checkHomeNew($slug)
    {
        $homeNew = HomeNewQuery::create()->findOneBySlug($slug);
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$homeNew || $rm->getCurrentGroupId() != $homeNew->getGroupId());
        return $homeNew;
    }

	/**
     * Page listant les news pour le groupe en question
 	 * @Route("/", name="BNSAppGroupBundle_back_home_manage")
     * @Rights("HOME_MANAGE")
	 * @Template()
	 */
	public function indexAction()
	{
        $groupId = $this->get('bns.right_manager')->getCurrentGroupId();
        // On récupère les 5 dernières publications
        $homeNews = HomeNewQuery::create()->getLastsByGroup($groupId)->find();

        return array(
            'homeNews'  => $homeNews
        );
	}

    /**
     * Fiche d'une actualité
     * @Route("/fiche/{slug}", name="BNSAppGroupBundle_back_home_manage_sheet")
     * @Rights("HOME_MANAGE")
     * @Template()
     */
    public function sheetAction($slug)
    {
        return array(
            'homeNew'  => $this->checkHomeNew($slug)
        );
    }

    /**
     * Création d'une nouvelle actualité ou édition
     * @Route("/nouveau", name="BNSAppGroupBundle_back_home_manage_new")
     * @Route("/editer/{slug}", name="BNSAppGroupBundle_back_home_manage_edit")
     * @Rights("HOME_MANAGE")
     * @Template()
     */
    public function newAction($slug = null)
    {
        if($slug != null)
        {
            $homeNew = $this->checkHomeNew($slug);
        }else{
            $homeNew = new HomeNew();
        }

        $form = $this->createForm(new HomeNewType(),$homeNew);
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());
            if ($form->isValid()) {
                $rightManager = $this->get('bns.right_manager');
                $groupId = $rightManager->getCurrentGroupId();
                $isNew = $homeNew->isNew();
                $homeNew = $form->getData();
                $homeNew->setGroupId($groupId);
                $homeNew->save();
                $this->get('session')->getFlashBag()->add('success', $isNew ? $this->get('translator')->trans('NEWS_HAS_BEEN_CREATE_SUCCESS', array(), 'GROUP') : $this->get('translator')->trans('NEWS_HAS_BEEN_UPDATE', array(), 'GROUP'));
                return $this->redirect($this->generateUrl('BNSAppGroupBundle_back_home_manage'));
            }
        }

        return array(
            'form'	=> $form->createView()
        );
    }

    /**
     * Suppression d'une actualité
     * @Route("/supprimer/{slug}", name="BNSAppGroupBundle_back_home_manage_delete")
     * @Rights("HOME_MANAGE")
     */
    public function deleteAction($slug)
    {
        $homeNew = $this->checkHomeNew($slug);
        $homeNew->delete();
        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('NEWS_DELETE_SUCCESS', array(), 'GROUP'));
        return $this->redirect($this->generateUrl('BNSAppGroupBundle_back_home_manage'));
    }

    /**
     * Gestion de l'alerte
     * @Route("/alerte", name="BNSAppGroupBundle_back_home_manage_alert")
     * @Route("/alerte-editer", name="BNSAppGroupBundle_back_home_manage_alert_edit")
     * @Rights("HOME_MANAGE")
     * @Template()
     */
    public function alertAction()
    {
        if ($this->getRequest()->isMethod('POST'))
        {
            if(trim($this->getRequest()->get('alert')) != "" && trim($this->getRequest()->get('alert_title')))
            {
                $this->get('bns.right_manager')->getCurrentGroup()->setAttribute('HOME_ALERT',$this->getRequest()->get('alert'));
                $this->get('bns.right_manager')->getCurrentGroup()->setAttribute('HOME_ALERT_TITLE',$this->getRequest()->get('alert_title'));
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_ALERT_UPDATE_SUCCESS', array(), 'GROUP'));
            }else{
                $this->get('session')->getFlashBag()->add('notice_error', $this->get('translator')->trans('FLASH_ENTER_TITLE_CONTENT_ALERT', array(), 'GROUP'));
            }
            return $this->redirect($this->generateUrl('BNSAppGroupBundle_back_home_manage_alert'));
        }

        $alert = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_ALERT');
        $alertTitle = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_ALERT_TITLE');
        $alertState = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_ACTIVE_ALERT');

        return array(
            'alert' => $alert,
            'alertTitle' => $alertTitle,
            'alertState' => $alertState
        );
    }

    /**
     * Toggle de l'activité de l'alerte
     * @Route("/alerte-changer", name="BNSAppGroupBundle_back_home_manage_alert_toggle")
     * @Rights("HOME_MANAGE")
     */
    public function alertToggleAction()
    {
        $oldValue = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_ACTIVE_ALERT');
        $this->get('bns.right_manager')->getCurrentGroup()->setAttribute(
            'HOME_ACTIVE_ALERT',
            !$oldValue
        );
        $this->get('session')->getFlashBag()->add('success',$oldValue ? $this->get('translator')->trans('FLASH_ALERT_DESACTIVATE', array(), 'GROUP') : $this->get('translator')->trans('FLASH_ALERT_ACTIVATE', array(), 'GROUP'));
        return $this->redirect($this->generateUrl('BNSAppGroupBundle_back_home_manage_alert'));
    }

}

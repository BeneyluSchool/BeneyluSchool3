<?php

namespace BNS\App\PortalBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\PortalBundle\Form\Type\PortalType;
use BNS\App\PortalBundle\Manager\PortalManager;
use BNS\App\PortalBundle\Model\PortalWidget;
use BNS\App\PortalBundle\Model\PortalWidgetGroup;
use BNS\App\PortalBundle\Model\PortalWidgetGroupQuery;
use BNS\App\PortalBundle\Model\PortalWidgetQuery;
use BNS\App\PortalBundle\Model\PortalZoneQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class BackController extends CommonController
{
    /**
     * @Route("/", name="BNSAppPortalBundle_back")
     * @Template()
     * @Rights("PORTAL_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {
        $portal = $this->getCurrentPortal();
        $form = $this->createForm(new PortalType(),$portal);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            $mlrm = $this->get('bns.media_library_right.manager');
            if ($form->isValid()) {
                //Check sécu des images
                if($form->get('logoId')->getData())
                {
                    if (!$this->hasFeature('portal_logo')) {
                        throw $this->createAccessDeniedException();
                    }
                    $canReadLogo = $mlrm->canReadMedia(MediaQuery::create()->findOneById($form->get('logoId')->getData()),true);
                    $this->get('bns.right_manager')->forbidIf(!$canReadLogo);
                }
                if($form->get('backgroundSmallId')->getData())
                {
                    $canReadBS = $mlrm->canReadMedia(MediaQuery::create()->findOneById($form->get('backgroundSmallId')->getData()),true);
                    $this->get('bns.right_manager')->forbidIf(!$canReadBS);
                }
                if($form->get('backgroundMediumId')->getData())
                {
                    $canReadBM = $mlrm->canReadMedia(MediaQuery::create()->findOneById($form->get('backgroundMediumId')->getData()),true);
                    $this->get('bns.right_manager')->forbidIf(!$canReadBM);
                }
                if($form->get('backgroundLargeId')->getData())
                {
                    $canReadBL = $mlrm->canReadMedia(MediaQuery::create()->findOneById($form->get('backgroundLargeId')->getData()),true);
                    $this->get('bns.right_manager')->forbidIf(!$canReadBL);
                }
                $portal->save();
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PORTAL_UPDATE_SUCCESS', array(), 'PORTAL'));

                return $this->redirect($this->generateUrl('BNSAppPortalBundle_back'));
            }
        }

        return array(
            'portal' => $portal,
            'form' => $form->createView(),
            'fonts' => PortalManager::$fonts,
            'colors' => array_keys(PortalManager::$colors)
        );
    }

    public function saveWidgets(Request $request, PortalWidgetGroup $widgetGroup)
    {
        $widgetSaved = array();
        $i = 0;
        foreach($request->request as $key => $parameter)
        {
            // 1 on retrouve le widget en question
            $widgetId = substr(strrchr($key,'-'), 1);
            $widget = PortalWidgetQuery::create()->findOneById($widgetId);

            if(isset($parameter['enabled']))
            {
                $widget->setEnabled($parameter['enabled']);
            }
            // 2 on assure la sécurité
            if($widget->getPortalWidgetGroupId() == $widgetGroup->getId())
            {
                $widgetSaved[] = $widgetId;
                switch($widget->getType())
                {
                    case 'BANNER':
                        $widget->setDatas(array('bannerId' => $parameter['banner']));
                        $widget->save();
                        break;
                    case 'TEXT':
                        $widget->setDatas(array('text' => $parameter['text']));
                        $widget->save();
                        break;
                    case 'RSS':
                        $finalArray = array();
                        foreach($parameter['rss'] as $key => $feed)
                        {
                            //On vérifie les image et on récupère le titre
                            //Secu Image

                            //
                            if($feed['feed'] != "")
                            {
                                $simplePieService = $this->get('fkr_simple_pie.rss');
                                $simplePieService->set_feed_url($feed['feed']);

                                $init = $simplePieService->init();

                                if($init != false)
                                {
                                    $title = $simplePieService->get_title();
                                    $finalArray[$key] = array(
                                        'feed' => $feed['feed'],
                                        'title' => $title,
                                        'image' => $feed['image']
                                    );

                                    $widget->setDatas(array('rss' => $finalArray, 'title' => $parameter['title']));
                                    $widget->save();

                                }else{
                                    //Flux invalide
                                    $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_FLUX_NOT_SAVE', array(), 'PORTAL'));

                                }
                            }
                        }

                        break;
                    case 'LINK':
                        $finalArray = array();
                        foreach($parameter['link'] as $key => $link)
                        {
                            //On vérifie les image et on récupère le titre
                            //Secu Image

                            //

                            $finalArray[$key] = array(
                                'url' => $link['url'],
                                'image' => $link['image']
                            );

                        }
                        $widget->setDatas(array('link' => $finalArray, 'title' => $parameter['title']));
                        $widget->save();
                        break;

                    case 'MINISITE':
                        $listIds = [];
                        if (isset($parameter['lists'])) {
                            $listIds = explode(',', $parameter['lists']);
                            $listIds = array_map(function($item) {
                                return (int)$item;
                            }, $listIds);
                            $listIds =  DistributionListQuery::create()
                                ->filterById($listIds)
                                ->filterByGroup($this->get('bns.right_manager')->getCurrentGroup())
                                ->select('Id')
                                ->find()
                                ->getArrayCopy()
                            ;
                        }
                        $widget->setDatas([
                            'title' => isset($parameter['title']) ? $parameter['title'] : '',
                            'lists' => $listIds,
                            'all' => isset($parameter['all']) ? (boolean) $parameter['all'] : false,
                        ]);
                        $widget->save();

                        if (empty($listIds) && !$parameter['all']) {
                            $widget->setEnabled(false);
                            return $widget->getType();
                        }
                        break;
                }

                $widget->setPosition($i);
                $widget->save();
                $i++;
            }

        }
        //On supprime les widgets qui ont été  ... supprimés
        PortalWidgetQuery::create()->filterByPortalWidgetGroupId($widgetGroup->getId())->filterById($widgetSaved,\Criteria::NOT_IN)->delete();
    }

    /**
     * @Route("/zone-principale", name="BNSAppPortalBundle_back_main_zone")
     * @Template()
     * @Rights("PORTAL_ACCESS_BACK")
     */
    public function mainZoneAction(Request $request)
    {
        $widgetZone = PortalZoneQuery::create()->findOneByUniqueName('MAIN');
        $widgetGroup = PortalWidgetGroupQuery::create()
            ->filterByPortal($this->getCurrentPortal())
            ->filterByPortalZone($widgetZone)
            ->findOneOrCreate();

        if($widgetGroup->isNew())
        {
            $widgetGroup->save();
        }

        if($request->isMethod('POST'))
        {
            $type = $this->saveWidgets($request, $widgetGroup);
            if (!is_null($type) && $type == 'MINISITE') {
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_PORTAL_UPDATE_ERROR_EMPTY_LIST', array(), 'PORTAL'));
            } else {
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PORTAL_UPDATE_SUCCESS', array(), 'PORTAL'));
            }
        }

        $widgets = PortalWidgetQuery::create()
            ->orderByPosition(\Criteria::ASC)
            ->filterByPortalWidgetGroup($widgetGroup)
            ->find();

        return array(
            'widgets' => $widgets,
            'widgetGroup' => $widgetGroup,
            'portal' => $this->getCurrentPortal(),
            'simplePie' => $this->get('fkr_simple_pie.rss'),
        );
    }

    /**
     * @Route("/ajouter-widget/{type}/{widgetGroupId}", name="BNSAppPortalBundle_back_add_widget", options={"expose"=true})
     * @Rights("PORTAL_ACCESS_BACK")
     */
    public function addWidget($type, $widgetGroupId,  Request $request)
    {
        $widgetGroup = PortalWidgetGroupQuery::create()->findOneById($widgetGroupId);
        $this->get('bns.right_manager')->forbidIf($widgetGroup->getPortalId() != $this->getCurrentPortal()->getId());

        // only allow valid type
        if (!in_array(strtolower($type), [
            'banner',
            'link',
            'minisite',
            'rss',
            'school',
            'text'
        ])) {
            throw $this->createNotFoundException();
        }

        if(!$this->hasFeature('portal_widgets') && in_array(strtolower($type), ['rss',
                'school',
                'link'
            ])) {
            throw $this->createAccessDeniedException();
        }

        $widget = new PortalWidget();
        $widget->setType($type);
        $widget->setPortalWidgetGroupId($widgetGroupId);
        $widget->setPosition(1);
        $widget->save();

        return $this->render('BNSAppPortalBundle:BackWidgets:'. strtolower($type) .'.html.twig',
            array(
                'widget' => $widget,
                'isNew' => true,
                'portal' => $this->getCurrentPortal()
            )
        );
    }

    /**
     * @Route("/colonne-laterale", name="BNSAppPortalBundle_back_side_zone")
     * @Template()
     * @Rights("PORTAL_ACCESS_BACK")
     */
    public function sideZoneAction(Request $request)
    {
        $widgetZone = PortalZoneQuery::create()->findOneByUniqueName('SIDE');
        $widgetGroup = PortalWidgetGroupQuery::create()
            ->filterByPortal($this->getCurrentPortal())
            ->filterByPortalZone($widgetZone)
            ->findOneOrCreate();

        if($widgetGroup->isNew())
        {
            $widgetGroup->save();
        }

        if($request->isMethod('POST'))
        {
            $type = $this->saveWidgets($request, $widgetGroup);
            if (!is_null($type) && $type == 'MINISITE') {
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_PORTAL_UPDATE_ERROR_EMPTY_LIST', array(), 'PORTAL'));
            } else {
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PORTAL_UPDATE_SUCCESS', array(), 'PORTAL'));
            }
        }

        $widgets = PortalWidgetQuery::create()
            ->orderByPosition(\Criteria::ASC)
            ->filterByPortalWidgetGroup($widgetGroup)
            ->find();

        return array(
            'widgets' => $widgets,
            'widgetGroup' => $widgetGroup,
            'portal' => $this->getCurrentPortal(),
            'simplePie' => $this->get('fkr_simple_pie.rss')
        );
    }

}

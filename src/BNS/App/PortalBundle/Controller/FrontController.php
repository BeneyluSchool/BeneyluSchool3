<?php

namespace BNS\App\PortalBundle\Controller;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\PortalBundle\Model\Portal;
use BNS\App\PortalBundle\Model\PortalPeer;
use BNS\App\PortalBundle\Model\PortalQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use FOS\UserBundle\Model\GroupManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class FrontController extends CommonController
{
    /**
     * @Route("/", name="BNSAppPortalBundle_front")
     * @Template("BNSAppPortalBundle:Out:homepage.html.twig")
     */
    public function indexAction()
    {
        $this->get('stat.portal')->visit();
        return array(
            'isAuthenticated' => true,
            'portal' => $this->getCurrentPortal(),
            'simplePie' => $this->get('fkr_simple_pie.rss'),
            'gm' => $this->get('bns.group_manager')->setGroup($this->getCurrentPortal()->getGroup())
        );
    }

    /**
     * @Route("/redirect-minisite/{schoolId}", name="BNSAppPortalBundle_front_redirect_minisite")
     */
    public function redirectMinisiteAction($schoolId, Request $request)
    {
        $minisite = GroupQuery::create()->findOneById($schoolId)->getMiniSites()->getFirst();
        if($minisite)
        {
            return $this->redirect($this->generateUrl('minisite_by_slug',array('slug' => $minisite->getSlug())));
        }else{
            $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_SCHOOL_HAVENT_MINISITE', array(), 'PORTAL'));
            return $this->redirect($this->getRequest()->headers->get('referer'));
        }
    }

    /**
     * @Route("/school_widget/{groupId}", name="BNSAppPortalBundle_school_widget")
     * @Template()
     */
    public function schoolWidgetAction($groupId)
    {
        $gm = $this->get('bns.group_manager')->setGroupById($groupId);
        $portal = PortalQuery::create()->findOneByGroupId($groupId);

        $schools = $gm->getSubgroupsByGroupType('SCHOOL', true);

        $redis = $this->get('snc_redis.default');
        if($redis->exists('portal_school' . $gm->getGroup()->getId()))
        {
            return array(
                'schools' => json_decode($redis->get('portal_school' . $gm->getGroup()->getId())),
                'portal' => $portal
            );
        }

        if($this->container->hasParameter('check_group_enabled'))
        {
            $needEnable = true;
        }else{
            $needEnable = false;
        }

        $schoolsArray = array();

        foreach($schools as $school)
        {
            /** @var Group $school */
            if($school->isEnabled() || !$needEnable)
            {

                if($school->getAttribute('GEOCOORDS') == null || $school->getAttribute('GEOCOORDS') == '')
                {
                    $fullAddress = $school->getAttribute('ADDRESS') . " " . $school->getAttribute('ZIPCODE') . " " . $school->getAttribute('CITY');
                    $coord = $this->get('bns.geocoords_manager')->queryService->queryCoordinates($fullAddress);
                    $groupGeocoords = $coord->getLatitude() . ';' . $coord->getLongitude();
                    $school->setAttribute('GEOCOORDS',str_replace(',','.',$groupGeocoords));
                }

                //Test Minisite
                $miniSitePage = MiniSitePageQuery::create()
                    ->orderByIsHome()
                    ->filterByIsPublic(true)
                    ->useMiniSiteQuery()
                        ->filterByGroupId($school->getId())
                    ->endUse()
                    ->findOne();
                if($miniSitePage)
                {
                    $miniSiteUrl = $this->container->getParameter('application_base_url') . 'site/' . $miniSitePage->getSlug();
                }else{
                    $miniSiteUrl = null;
                }


                $schoolsArray[] = array(
                    'name' => $school->getLabel(),
                    'address' => $school->getAttribute('ADDRESS'),
                    'zipcode' => $school->getAttribute('ZIPCODE'),
                    'city' => $school->getAttribute('CITY'),
                    'geocoords' => $school->getAttribute('GEOCOORDS'),
                    'id' => $school->getId(),
                    'miniSiteUrl' => $miniSiteUrl
                );
            }
        }

        $redis->set('portal_school' . $gm->getGroup()->getId(), json_encode($schoolsArray));
        $redis->expire('portal_school' . $gm->getGroup()->getId(), 3600 * 5);

        return array(
            'schools' => $schoolsArray,
            'portal' => $portal
        );
    }
}

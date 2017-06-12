<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/contexte")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontContextController extends CommonController
{
    /**
     * @Route("/voir", name="resource_context_list", options={"expose": true})
     *
     * @RightsSomeWhere("RESOURCE_ACCESS")
     */
    public function listContextAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
        }

        $groups = $this->get('bns.user_manager')->getGroupsWherePermission('RESOURCE_ACCESS');
        $groupNodes = $this->get('bns.group_manager')->buildParentGraph($groups);

        return $this->render('BNSAppResourceBundle:Modal:change_context_body_content.html.twig', array(
            'groupNodes' => $groupNodes,
            'padding'    => 0
        ));
    }

    /**
     * @Route("/changer", name="resource_context_change", options={"expose": true})
     */
    public function changeContextAction(Request $request)
    {
        if (!$request->isXmlHttpRequest() || !$request->isMethod('POST') || false === $request->get('groupId', false) ||
            !$this->get('bns.user_manager')->hasRight('RESOURCE_ACCESS', $request->get('groupId'))) {
            return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
        }

        $this->setGroupContext($request->get('groupId'));

        return $this->forward('BNSAppResourceBundle:FrontNavigation:sidebar', array(
            'groupId' => $request->get('groupId')
        ));
    }
}
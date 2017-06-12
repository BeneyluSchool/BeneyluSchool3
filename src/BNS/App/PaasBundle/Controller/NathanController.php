<?php
namespace BNS\App\PaasBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class NathanController extends Controller
{
    /**
     * @Template()
     * @Route("/nathan")
     * @Rights("MEDIA_LIBRARY_ACCESS")
     */
    public function catalogAction(Request $request)
    {
        $user = $this->getUser();
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $uai = $group->getUAI();
        if (!$uai) {
            $parent = $this->get('bns.group_manager')->setGroup($group)->getParent();
            if ($parent) {
                $uai = $parent->getUAI();
            }
        }
        if ($uai) {
            $refreshCache = !!$request->get('refresh', false);
            $resources = $this->get('bns_app_paas.manager.nathan_resource_manager')->getCatalog('nathan', $user, $uai, $refreshCache);
        } else {
            $resources = false;
        }

        return [
            'resources' => $resources
        ];
    }

}

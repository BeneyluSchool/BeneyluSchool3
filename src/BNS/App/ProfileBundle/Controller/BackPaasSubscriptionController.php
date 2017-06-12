<?php
namespace BNS\App\ProfileBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackPaasSubscriptionController extends Controller
{
    /**
     * @Route("/subscriptions/{page}", requirements={"page"="\d*"})
     * @Template("BNSAppClassroomBundle:BackPaasSubscription:subscription.html.twig")
     * @RightsSomeWhere("SPOT_ACCESS")
     */
    public function subscriptionAction(Request $request, $page = 1)
    {
        $filters = array_intersect(array('current', 'ending', 'ended'), explode(',', $request->get('filters')));

        $subscriptions = $this->get('bns.paas_manager')->getFormattedSubscriptions($this->getUser());

        if (count($filters) > 0) {
            $subscriptions = array_filter($subscriptions, function($val) use ($filters) {
                return in_array($val['status'], $filters);
            });
        }

        $pager = new Pagerfanta(new ArrayAdapter($subscriptions));
        $pager->setNormalizeOutOfRangePages(true);
        $pager->setMaxPerPage(5);
        $pager->setCurrentPage($page);

        return array(
            'subscriptions' => $pager,
            'page'          => $page,
            'filters'       => $filters,
            'type'          => 'user'
        );
    }
}

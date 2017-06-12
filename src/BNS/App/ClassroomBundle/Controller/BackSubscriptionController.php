<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * @Route("/abonnements")
 */
class BackSubscriptionController extends Controller
{
    const SESSION_FILTER_NAME = 'classroom_subscription_filters';

    /**
     * @Route("/", name="classroom_manager_subscription")
     *
     * @Rights("CLASSROOM_SUBSCRIPTION_ACCESS")
     */
    public function listAction(Request $request)
    {
        // Clean filters
        $request->getSession()->remove(self::SESSION_FILTER_NAME);

        return $this->render('BNSAppClassroomBundle:BackSubscription:index.html.twig', array(
            'subscriptions' => $this->get('subscription_manager')->getSubcriptions($this->getSchool())
        ));
    }

    /**
     * @Route("/filter", name="classroom_manager_subscription_filter", options={"expose": true})
     *
     * @Rights("CLASSROOM_SUBSCRIPTION_ACCESS")
     */
    public function filterAction(Request $request)
    {
        if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
            null === $request->get('filter', null) || null === $request->get('status', null)) {
            return $this->redirect($this->generateUrl('classroom_manager_subscription'));
        }

        $filter = $request->get('filter');
        $status = $request->get('status');

        $filters = $request->getSession()->get(self::SESSION_FILTER_NAME, array());
        $filters[$filter] = $status;

        $request->getSession()->set(self::SESSION_FILTER_NAME, $filters);

        return $this->render('BNSAppClassroomBundle:BackSubscription:subscription_list.html.twig', array(

        ));
    }

    /**
     * @Route("/gerer", name="classroom_manager_subscription_manage")
     *
     * @Rights("CLASSROOM_SUBSCRIPTION_ACCESS")
     */
    public function manageAction()
    {
        return $this->render('BNSAppClassroomBundle:BackSubscription:manage.html.twig', array(
            'csrf' => $this->container->get('form.csrf_provider')->generateCsrfToken('providers_manage')
        ));
    }

    /**
     * @Route("/gerer/liste", name="classroom_manager_subscription_manage_render")
     *
     * @Rights("CLASSROOM_SUBSCRIPTION_ACCESS")
     */
    public function renderProviderAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('classroom_manager_subscription'));
        }

        $subscriptionManager = $this->get('subscription_manager');
        $school = $this->getSchool();

        $subscriptions = $subscriptionManager->getSubcriptions($school);
        $availableProviders = $subscriptionManager->getAvailableProviders($school);
        $providers = $subscriptionManager->getProviders();

        // Provider process
        foreach ($providers as &$provider) {
            // Adding "enabled" option if provider is available
            $provider['enabled'] = false;
            foreach ($availableProviders as $aProvider) {
                if ($provider['id'] == $aProvider['id']) {
                    $provider['enabled'] = true;
                    break 1;
                }
            }

            // Adding "selected" option if provider is selected
            $provider['selected'] = false;
            foreach ($subscriptions as $subscription) {
                if ($provider['id'] == $subscription['id']) {
                    $provider['selected'] = true;
                    break 1;
                }
            }
        }

        return $this->render('BNSAppClassroomBundle:BackSubscription:provider_list.html.twig', array(
            'providers' => $providers
        ));
    }

    /**
     * @Route("/gerer/sauvegarder", name="classroom_manager_subscription_save")
     *
     * @Rights("CLASSROOM_SUBSCRIPTION_ACCESS")
     */
    public function saveAction(Request $request)
    {
        if (!$request->isMethod('POST') || !$this->container->get('form.csrf_provider')->isCsrfTokenValid('providers_manage', $request->get('_csrf')) ||
            false === $request->get('providers', false)) {
            return $this->redirect($this->generateUrl('classroom_manager_subscription_manage'));
        }

        // Parsing provider parameter
        $providers = $request->get('providers', false);
        if (false !== strpos($providers, ',')) {
            $providers = explode(',', $providers);
        }
        elseif (strlen($providers) == 0) {
            $providers = array();
        }
        else {
            $providers = array($providers);
        }

        $school = $this->getSchool();
        $subscriptionManager = $this->get('subscription_manager');
        $subcriptions = $subscriptionManager->getSubcriptions($school);

        // Add
        $addProviderIds = array();
        $found = false;

        foreach ($providers as $provider) {
            foreach ($subcriptions as $subcription) {
                if ($provider == $subcription['id']) {
                    $found = true;
                    break 1;
                }
            }

            if (!$found) {
                $addProviderIds[] = $provider;
            }

            $found = false;
        }

        // Delete
        $deleteProviderIds = array();
        foreach ($subcriptions as $subcription) {
            foreach ($providers as $provider) {
                if ($provider == $subcription['id']) {
                    $found = true;
                    break 1;
                }
            }

            if (!$found) {
                $deleteProviderIds[] = $subcription['id'];
            }

            $found = false;
        }

        // Processing add & delete
        if (isset($addProviderIds[0])) {
            $subscriptionManager->addSubscriptions($school, $addProviderIds);
        }

        if (isset($deleteProviderIds[0])) {
            $subscriptionManager->removeSubscriptions($school, $deleteProviderIds);
        }

        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_SUBSCRIPTIONS_CHANGE', array(), "CLASSROOM"));

        return $this->redirect($this->generateUrl('classroom_manager_subscription'));
    }

    /**
     * @return Group
     */
    private function getSchool(Group $group = null)
    {
        $parents = $this->get('bns.group_manager')->setGroup(null == $group ? $this->get('bns.right_manager')->getCurrentGroup() : $group)->getParents();
        foreach ($parents as $parent) {
            if ($parent->getGroupType()->getType() == 'SCHOOL') {
                return $parent;
            }
        }

        throw new \RuntimeException('The group #' . $group->getId() . ' has no school parent !');
    }
}

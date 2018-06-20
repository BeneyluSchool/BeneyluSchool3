<?php

namespace BNS\App\MainBundle\Twig;

use JMS\Serializer\SerializationContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NavbarExtension
 *
 * @package BNS\App\MainBundle\Twig
 * @deprecated
 */
class NavbarExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getNavbarData', array($this, 'getNavbarData'), array(
                'is_safe' => array('html'),
            )),
        );
    }

    public function getNavbarData($module_unique_name = null, $is_in_front = null)
    {
        $rightManager = $this->container->get('bns.right_manager');

        $modules = $rightManager->getDockModules($module_unique_name, $is_in_front);

        $showInactiveSchoolPage = false;

        if ($this->container->hasParameter('bns.enable_register') && $this->container->getParameter('bns.enable_register') == true) {
            if ($rightManager->getCurrentGroupManager()->isOnPublicVersion()) {
                if ($rightManager->hasRight('CLASSROOM_ACCESS_BACK')) {
                    if (!$rightManager->getCurrentGroupManager()->getParent()->isPremium()) {
                        $showInactiveSchoolPage = true;
                    }
                }
            }
        }

        $context = SerializationContext::create();
        $context->setGroups(array('Default', 'basic'));

        return $this->container->get('serializer')->serialize(array(
            'context_modules'           => $modules['context'],
            'constant_modules'          => $modules['global'],
            'current_module'            => $modules['current_module'],
            'is_in_front'               => $is_in_front,
//            'moduleContextBackAccess'   => $modules['moduleContextBackAccess'],
            'groups_context'             => $rightManager->getGroups(true),
//            'currentGroupType'          => $rightManager->getCurrentGroupType(),
            'current_group'              => $rightManager->getCurrentGroup(),
//            'currentGroupRoute'         => $rightManager->getRedirectRouteOfCurrentGroup(!$is_in_front),
//            'rightManager'              => $rightManager,
//            'nbNotifInfo'               => $rightManager->getNbNotifInfo(),
//            'showInactiveSchoolPage'    => $showInactiveSchoolPage
        ), 'json', $context);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'navbar_extension';
    }

}

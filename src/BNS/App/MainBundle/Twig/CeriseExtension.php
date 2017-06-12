<?php

namespace BNS\App\MainBundle\Twig;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CeriseExtension
 *
 * @package BNS\App\MainBundle\Twig
 */
class CeriseExtension extends \Twig_Extension
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
            new \Twig_SimpleFunction('hasCerise', array($this, 'hasCerise'), array(
                'is_safe' => array('html'),
            )),
        );
    }

    public function hasCerise () {
        $rightManager = $this->container->get('bns.right_manager');
        $hasCerise = $rightManager->hasCerise();
        $token = $this->container->get('security.token_storage')->getToken();
        $user = $token ? $token->getUser() : null;

        if (!$hasCerise || !$user || ! ($user instanceof User)) {
            return false;
        } else {
            $userManager = $this->container->get('bns.user_manager');
            $currentGroup = $rightManager->getCurrentGroup();
            $oldUser = $userManager->getUser();

            if ($currentGroup && in_array($currentGroup->getType(), ['SCHOOL','CLASSROOM'])
                && $rightManager->hasCerise($currentGroup, true)
                && in_array($userManager->setUser($user)->getMainRole(), ['teacher','director','admin'])
            ) {
                $userManager->setUser($oldUser);

                return true;
            }
            $userManager->setUser($oldUser);
        }

        return false;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'cerise_extension';
    }

}

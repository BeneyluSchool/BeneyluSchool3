<?php
namespace BNS\App\CoreBundle\Parameter;

use OpenSky\Bundle\RuntimeConfigBundle\Model\ParameterProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class RuntimeParameter implements ParameterProviderInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return array|void
     */
    public function getParametersAsKeyValueHash()
    {
        return array(
            'cdn_url' => $this->getCdnUrl()
        );
    }

    /**
     * custom cdn url based on user agent
     * @return string cdn url
     */
    protected function getCdnUrl()
    {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8')) {
                if ($this->container->hasParameter('cdn_url_ie8')) {
                    return $this->container->getParameter('cdn_url_ie8');
                }
            }
        }

        return $this->container->getParameter('cdn_url');
    }
}

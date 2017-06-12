<?php
namespace BNS\App\CoreBundle\Beta;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BetaManager
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var boolean
     */
    protected $betaModeAllowed;

    /**
     * @var boolean
     */
    protected $betaModeEnabled;

    /**
     * @var string
     */
    protected $betaDomain;

    /**
     * @var string
     */
    protected $normalDomain;

    protected $defaultScheme = 'https';

    public function __construct(RouterInterface $router, RequestStack $requestStack, $betaModeAllowed, $betaModeEnabled, $betaDomain, $normalDomain)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;

        $this->betaModeAllowed = $betaModeAllowed;
        $this->betaModeEnabled = $betaModeEnabled;
        $this->betaDomain = $betaDomain;
        $this->normalDomain = $normalDomain;

        // save scheme for reuse in terminated state
        $request = $this->requestStack->getMasterRequest();
        if ($request) {
            $this->defaultScheme = $request->getScheme();
        }
    }

    /**
     * @return boolean
     */
    public function isBetaModeAllowed()
    {
        return $this->betaModeAllowed;
    }

    /**
     * @return boolean
     */
    public function isBetaModeEnabled()
    {
        return $this->betaModeEnabled;
    }

    /**
     * @return string
     */
    public function getBetaDomain()
    {
        return $this->betaDomain;
    }

    /**
     * @return string
     */
    public function getNormalDomain()
    {
        return $this->normalDomain;
    }

    public function generateBetaRoute($name, $params = array())
    {
        $path = $this->router->generate($name, $params);

        return $this->generateRoute($this->betaDomain, $path);
    }

    public function generateNormalRoute($name, $params = array())
    {
        $path = $this->router->generate($name, $params);

        return $this->generateRoute($this->normalDomain, $path);
    }

    protected function generateRoute($domain, $path)
    {
        $scheme = $this->defaultScheme;
        $request = $this->requestStack->getMasterRequest();
        if ($request) {
            $scheme = $request->getScheme();
        }
        $url = $scheme . '://' . $domain . $path;

        return $url;
    }
}

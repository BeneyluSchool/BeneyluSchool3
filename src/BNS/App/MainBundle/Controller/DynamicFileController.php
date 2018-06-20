<?php

namespace BNS\App\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class DynamicFileController
 *
 * @package BNS\App\MainBundle\Controller
 */
class DynamicFileController extends Controller
{

    /**
     * @Route("dynamic/trackers.js")
     * @Template("BNSAppMainBundle:DynamicFile:trackers.js.twig")
     */
    public function trackersAction()
    {
        return [];
    }

    /**
     * @Route("dynamic/manifest.json")
     * @return BinaryFileResponse
     */
    public function manifestAction()
    {
        if ($this->container->hasParameter('manifest')) {
            $manifest = $this->container->getParameter('manifest');
        } else {
            $manifest = 'beneylu.json';
        }
        $file = $this->get('kernel')->getRootDir().'/../web/medias/manifest/'.$manifest;

        return new BinaryFileResponse($file);
    }

    /**
     * @Route("dynamic/favicon.ico")
     * @return BinaryFileResponse
     */
    public function faviconAction()
    {
        if ($this->container->hasParameter('graphic_chart')) {
            $favicon = sprintf(
                'medias/images/main/graphic_chart/%s/favicon.ico',
                $this->container->getParameter('graphic_chart')['name']
            );
        } else {
            $favicon = 'favicon.ico';
        }
        $file = $this->get('kernel')->getRootDir().'/../web/'.$favicon;

        return new BinaryFileResponse($file);
    }

}

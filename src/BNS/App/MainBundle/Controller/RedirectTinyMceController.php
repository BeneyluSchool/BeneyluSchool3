<?php
/**
 * Created by PhpStorm.
 * User: Maxime
 * Date: 24/06/2015
 * Time: 17:20
 */

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class RedirectTinyMceController extends Controller
{
    /**
     * Redirection temporaire des emoticones dans nouveau dossier
     *
     * @Route("/bundles/stfalcontinymce/vendor/tinymce/plugins/emotions/img/{slug}", name="BNSAppMainBundle_tiny_redirect")
     */
    public function tinyRedirecttAction($slug)
    {
        return new RedirectResponse($this->container->getParameter('application_base_url') . "/bundles/stfalcontinymce/vendor/tinymce/plugins/emoticons/img/" . $slug);
    }

}
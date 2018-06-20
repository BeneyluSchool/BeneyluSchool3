<?php

namespace BNS\App\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class AngularjsController
 *
 * @package BNS\App\MainBundle\Controller
 */
class AngularjsController extends Controller
{

    /**
     * Catches requests to the old angularjs root template, and redirect them to the new Angular
     * root template.
     *
     * @deprecated
     * @see NgController
     *
     * @Route("/app/{rest}", requirements={"rest": ".*"})
     *
     * @param string $rest
     * @return RedirectResponse
     */
    public function oldAppIndexAction($rest = '')
    {
        @trigger_error('The "BNSAppMainBundle_front" route is deprecated, use "ng_index" instead.', E_USER_DEPRECATED);

        return $this->redirect($this->generateUrl('ng_index', [
            'rest' => 'app/'.$rest,
        ]));
    }

}

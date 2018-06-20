<?php

namespace BNS\App\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class NgController
 *
 * @package BNS\App\MainBundle\Controller
 *
 * @Route("/os/{_locale}", requirements={"_locale": "|(\w{2}([-_]\w{2})?)"}, defaults={"_locale": ""})
 */
class NgController extends Controller
{

    /**
     * Base of the Angular frontend app.
     *
     * Routes with supported locales should be intercepted by Apache and served directly the Angular app.
     * Routes with unset, unsupported or non-canonical locales should land here, where locale is normalized and user is then redirected to the proper locale route, which will be intercepted by Apache.
     *
     * @Route("/{rest}", name="ng_index", requirements={"rest": ".*"}, defaults={"rest": ""})
     * @Route("/login", name="ng_login")
     * @Route("/chat", name="BNSAppChatBundle_front")
     *
     * @param Request $request
     * @param string $rest
     * @return RedirectResponse
     */
    public function indexAction(Request $request, $rest)
    {
        $localeManager = $this->get('bns.locale_manager');

        // get user locale, or guess from request
        $locale = $request->getLocale();

        // make sure locale is valid
        $locale = $localeManager->localeOrDefault($locale);

        // because this route accepts anything as locale, make sure to set back the corrected locale
        $request->getSession()->set('_locale', $locale);

        // transform to a URL-friendly locale
        $locale = $localeManager->slugify($locale);

        // send a redirect response, will be caught by apache and served directly
        return $this->redirect($this->generateUrl('ng_index', [
            '_locale' => $locale,
            'rest' => $rest,
        ]));
    }

}

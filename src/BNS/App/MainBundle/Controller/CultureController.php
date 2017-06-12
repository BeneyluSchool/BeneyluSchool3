<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class CultureController extends Controller
{
    /**
     * Change la culture de l'utilisateur en cours
     *
     * @param string $culture : Culture à setter
     *
     * @Route("/switch-language/{culture}", name="BNSAppMainBundle_change_culture")
     */
    public function changeCultureAction(Request $request, $culture)
    {
        $locale = $this->get('bns.locale_manager')->getBestLocale($culture);

        $session = $this->get('session');
        $session->set('_locale', $locale);

        if ($request->isXmlHttpRequest()) {
            // Do not redirect if is an ajax call
            return new JsonResponse('OK');
        }

        // validate referer for security raison
        $url = $request->headers->get('referer');
        $errors = $this->get('validator')->validate($url, [
            new NotBlank(),
            new Url()
        ]);
        if (count($errors) > 0) {
            // no valid referer
            return $this->redirect($this->generateUrl('home'));
        }

        $refererRequest = Request::create($url);
        // do not redirect to to referrer if it's from outside of our app
        if ($request->getUriForPath('') === $refererRequest->getUriForPath('')) {
            // remove _locale from url to prevent locale not changed
            if ($refererRequest->query->has('_locale')) {
                $refererRequest->query->remove('_locale');
            }

            $parameters = $refererRequest->query->all();
            $queryString = http_build_query($parameters);

            $url = $refererRequest->getSchemeAndHttpHost().$refererRequest->getBaseUrl().$refererRequest->getPathInfo() . ($queryString? '?' . $queryString :'');

            return $this->redirect($url);
        }

        return $this->redirect($this->generateUrl('home'));
    }
}

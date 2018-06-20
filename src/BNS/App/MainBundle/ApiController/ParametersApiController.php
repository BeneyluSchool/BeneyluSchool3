<?php

namespace BNS\App\MainBundle\ApiController;

use BNS\App\CoreBundle\Model\User;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use BNS\App\CoreBundle\Controller\BaseApiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Class ParametersApiController
 *
 * @package BNS\App\MainBundle\ApiController
 */
class ParametersApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Parameters",
     *  resource=true,
     *  description="Get all parameters for the current user and domain",
     *  statusCodes = {
     *      200 = "Ok"
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View()
     *
     * @param Request $request
     *
     * @return array
     */
    public function indexAction(Request $request)
    {
        // calculated and translated values
        $parameters = [
            'locale' => $request->getLocale(),
            'anonymous' => !$this->getUser(),
            'cerise' => $this->container->get('bns.cerise_extension')->hasCerise()
                ? $this->generateUrl('BNSAppSchoolBundle_back_cerise_login')
                : false,
            'pay_url' => $this->getStoreLink('pay_url'),
            'plans_url' => $this->getStoreLink('plans_url') ?: $this->getLocaleLink('plans_url'),
            'is_mobile' => $this->container->get('mobile_detect.device_view')->isMobileView(),
            'auth_login_cookies_content' => $this->container->get('translator')->trans('DESCRIPTION_COOKIES_POLICY', [
                '%cookies_more_info_url%' => $this->generateUrl('main_logon_cookies'),
                '%beneylu_brand_name%' => $this->container->getParameter('beneylu_brand_name'),
            ], 'MAIN'),
            'auth_login_footer_content' => $this->container->get('translator')->trans('FOOTER_LEGALS', [
                '%copyright_year%' => (new \DateTime())->format('Y'),
                '%eula_url%' => $this->getLocaleLink('legal_notice_url'),
            ], 'MAIN'),
        ];

        if ($this->container->hasParameter('graphic_chart')) {
            $parameters['favicon'] = $this->assetize(sprintf(
                'medias/images/main/graphic_chart/%s/favicon.ico',
                $this->container->getParameter('graphic_chart')['name']
            ));
        }

        if ($this->container->hasParameter('manifest')) {
            $manifest = $this->container->getParameter('manifest');
        } else {
            $manifest = 'beneylu.json';
        }
        $parameters['manifest'] = $this->assetize(sprintf('medias/manifest/%s', $manifest));

        // config parameters
        $ngParameterKeys = [
            'application_base_url',
            'auth_base_url=oauth_host',
            'auth_client_id=oauth_security_client_id',
            'auth_login_activate_account',
            'auth_login_background_img|asset',
            'auth_login_below',
            'auth_login_below_component',
            'auth_login_cookies_content',
            'auth_login_footer_background',
            'auth_login_footer_color',
            'auth_login_footer_content',
            'auth_login_footer_logos|asset',
            'auth_login_forgot_password',
            'auth_login_header_background',
            'auth_login_header_color',
            'auth_login_header_elevation',
            'auth_login_header_logos|asset',
            'auth_login_header_title',
            'auth_login_logo',
            'auth_login_register=bns.enable_register',
            'auth_login_saml_providers=saml.providers',
            'auth_login_title',
            'is_beta=bns_beta_enabled',
            'pusher_key',
            'pusher_cluster',
            'pusher_auth_endpoint=lopi_pusher_bundle_auth|path',

            // angularjs
            'has_account_management=bns.account.has_management',
            'has_plan_management=bns.account.has_plans',
        ];
        foreach ($ngParameterKeys as $key) {
            $this->addParameter($parameters, $key);
        }

        return $parameters;
    }

    /**
     * Adds the container parameter with given name to the array of parameters
     *
     * @param array $parameters
     * @param string $name Parameter name.
     *                     Can be prefixed by "asset:" to run returned value through Sf templating asset helper
     *                     (supports array of values)
     *                     Can be a construct of the form "resultingParamName=actualSfContainerParamName" if the name of
     *                     the Sf container parameter is different than the name of the resulting parameter.
     */
    private function addParameter(&$parameters, $name)
    {
        // check if value needs to be transformed
        $doAssetize = strpos($name, '|asset') > -1;
        if ($doAssetize) {
            $name = str_replace('|asset', '', $name);
        }
        $doPath = strpos($name, '|path') > -1;
        if ($doPath) {
            $name = str_replace('|path', '', $name);
        }

        // extract src and dest keys
        @list($destKey, $srcKey) = explode('=', $name, 2);
        if (!$srcKey) {
            $srcKey = $destKey;
        }

        if ($this->container->hasParameter($srcKey)) {
            $value = $this->container->getParameter($srcKey);
            if ($doAssetize) {
                $value = $this->assetize($value);
            }
            if ($doPath) {
                $value = $this->pathize($value);
            }

            $parameters[$destKey] = $value;
        } else if ($doPath) {
            // parameter not found, maybe it's a route name
            try {
                $value = $this->pathize($srcKey);
                $parameters[$destKey] = $value;
            } catch (RouteNotFoundException $e) {}
        }
    }

    private function assetize($value) {
        return $this->map(function ($v) {
            if (is_string($v)) {
                return $this->container->get('templating.helper.assets')->getUrl($v);
            }

            return $v;
        }, $value);
    }

    private function pathize($value) {
        return $this->map(function ($v) {
            if (is_string($v)) {
                return $this->container->get('router')->generate($v);
            }

            return $v;
        }, $value);
    }

    private function map($callback, $value) {
        if (is_array($value)) {
            $value = array_map($callback, $value);
        } else {
            $value = $callback($value);
        }

        return $value;
    }

    private function getStoreLink($item) {
        return $this->container->get('twig.extension.parameter_extension')->getBnsStoreLinks($item);
    }

    private function getLocaleLink($item) {
        return $this->container->get('twig.extension.parameter_extension')->getBnsLocaleLinks($item);
    }

}

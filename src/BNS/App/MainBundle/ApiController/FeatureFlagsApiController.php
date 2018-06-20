<?php

namespace BNS\App\MainBundle\ApiController;

use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use BNS\App\CoreBundle\Controller\BaseApiController;

/**
 * Class FeatureFlagsApiController
 *
 * @package BNS\App\MainBundle\ApiController
 */
class FeatureFlagsApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Feature flags",
     *  resource=true,
     *  description="Get all feature flags for the current context",
     *  statusCodes = {
     *      200 = "Ok"
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View()
     *
     * @return array
     */
    public function indexAction()
    {
        $toggleManager = $this->get('qandidate.toggle.manager');
        $context = $this->getToggleContext();

        $flags = [];
        foreach ($toggleManager->all() as $toggle) {
            $flags[$toggle->getName()] = $toggleManager->active($toggle->getName(), $context);
        }

        return $flags;
    }

    /**
     * @ApiDoc(
     *  section="Feature flags",
     *  resource=true,
     *  description="Get a feature flag for the current context",
     *  statusCodes = {
     *      200 = "Ok"
     *  }
     * )
     * @Rest\Get("/{flag}")
     * @Rest\View()
     *
     * @param string $flag
     * @return array
     */
    public function getAction($flag)
    {
        $toggleManager = $this->get('qandidate.toggle.manager');
        $context = $this->getToggleContext();

        return [
            'value' => $toggleManager->active($flag, $context),
        ];
    }

    private function getToggleContext()
    {
        // TODO fix BNSRightManager to be able to use the context properly
//        return $this->get('qandidate.toggle.context');
        return $this->get('bns.toggle.context_factory')->createContext();
    }

}

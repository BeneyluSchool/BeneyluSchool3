<?php

namespace BNS\App\StarterKitBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\User;
use BNS\App\StarterKitBundle\Form\Type\ApiStarterKitStateType;
use BNS\App\StarterKitBundle\Model\StarterKitState;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class StateApiController
 *
 * @package BNS\App\StarterKitBundle\ApiController
 */
class StateApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Starter Kit",
     *  resource=true,
     *  description="État d'un starter kit pour l'utilisateur en cours",
     *  requirements = {
     *      {
     *          "name" = "name",
     *          "dataType" = "string",
     *          "requirement" = "\d+",
     *          "description" = "Le nom du starter kit"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      403 = "Pas accès",
     *      404 = "Le starter kit n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Get("/{name}")
     * @Rest\View(serializerGroups={"Default", "homework_list", "homework_detail", "back", "media_basic"})
     *
     * @param string $name
     * @return mixed
     */
    public function getAction($name)
    {
        $user = $this->getUser();
        if ($response = $this->checkForErrors($name, $user)) {
            return $response;
        }

        $manager = $this->get('bns.starter_kit_manager');

        $state = $manager->getState($name, $user);
        if (!$state) {
            $state = $manager->createState($name, $user);
        }

        return $state;
    }

    /**
     * @ApiDoc(
     *  section="Starter Kit",
     *  description="Mise à jour de l'état d'un starter kit pour l'utilisateur en cours",
     *  requirements = {
     *      {
     *          "name" = "name",
     *          "dataType" = "string",
     *          "requirement" = "\d+",
     *          "description" = "Le nom du starter kit"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      403 = "Pas accès",
     *      404 = "Le starter kit n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Patch("/{name}")
     * @Rest\View(serializerGroups={"Default", "homework_list", "homework_detail", "back", "media_basic"})
     *
     * @param string $name
     * @param Request $request
     * @return mixed
     */
    public function patchAction($name, Request $request)
    {
        $user = $this->getUser();
        if ($response = $this->checkForErrors($name, $user)) {
            return $response;
        }

        $ctrl = $this;
        $manager = $this->get('bns.starter_kit_manager');
        $state = $manager->getState($name, $user);
        if (!$state) {
            return $this->view(null, Codes::HTTP_NOT_FOUND);
        }

        return $this->restForm(new ApiStarterKitStateType(), $state, [
            'csrf_protection' => false, // TODO
        ], null, function (StarterKitState $state, Form $form) use ($request, $manager, $ctrl) {
            $lastStep = $request->get('last_step');
            if ($lastStep) {
                if (!$manager->setLastStep($state, $lastStep, true)) {
                    return $ctrl->view(null, Codes::HTTP_BAD_REQUEST);
                }
            }
            $done = $request->get('done', false);
            if ($done) {
                $manager->setDone($state);
            }
            $state->save();

            return $state;
        });
    }

    /**
     * Checks the given parameters for error. Returns the appropriate response, if any
     *
     * @param $name
     * @param User $user
     * @return \FOS\RestBundle\View\View|null
     */
    public function checkForErrors($name, User $user)
    {
        if (!$this->get('bns.starter_kit_manager')->isValidName($name)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        // use an existing module to check access for main starter kit
        if ('MAIN' === $name) {
            $name = 'CLASSROOM';
        }

        if (!$this->get('bns.user_manager')->setUser($user)->hasRightSomeWhere($name.'_ACCESS_BACK')) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        return null;
    }

}

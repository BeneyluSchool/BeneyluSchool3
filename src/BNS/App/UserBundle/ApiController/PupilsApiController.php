<?php

namespace BNS\App\UserBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PupilsApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class PupilsApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Users - Pupils",
     *  description="Validates quick add pupil data",
     * )
     *
     * @Rest\Post("/quick-add/validate")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response
     */
    public function quickAddValidateAction(Request $request)
    {
        $source = $request->get('source');

        return $this->get('bns.user_manager')->textToUserArray($source);
    }

    /**
     * @ApiDoc(
     *  section="Users - Pupils",
     *  resource = true,
     *  description="Quick add pupil data",
     * )
     *
     * @Rest\Post("/quick-add")
     * @Rest\View()
     *
     * @param Request $request
     * @return View
     */
    public function quickAddAction(Request $request)
    {
        $source = $request->get('source');
        $pupilsList = $this->get('bns.user_manager')->textToUserArray($source);

        if (!count($pupilsList)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $result = $this->get('bns.classroom_manager')
            ->setClassroom($this->get('bns.right_manager')->getCurrentGroup())
            ->importPupilFromTextarea($pupilsList);

        if ($result['success_insertion_count'] == $result['user_count']) {
            $msgType = 'success';
            $msg = $this->get('translator')->trans('FLASH_PROCESS_IMPORT_SUCCESS', array('%user%' => $result['user_count']), "CLASSROOM");
        } else {
            $msgType = 'error';
            $msg =  $this->get('translator')->trans('FLASH_PROCESS_IMPORT_ERROR', array(
                '%resultSuccess%' => $result['success_insertion_count'],
                '%skiped%' => $result['skiped_count'],
            ), "CLASSROOM");
        }

        return $this->view([
            'message' => $msg,
            'type' => $msgType,
        ]);
    }

}

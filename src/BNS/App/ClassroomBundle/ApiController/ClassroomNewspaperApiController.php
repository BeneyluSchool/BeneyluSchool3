<?php

namespace BNS\App\ClassroomBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * Class ClassroomNewspaperApiController
 *
 * @package BNS\App\ClassroomBundle\ApiController
 */
class ClassroomNewspaperApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *     section="Classroom - Newspaper",
     *     resource=true,
     *     description="Get today's newspaper, if it exist",
     *     statusCodes={
     *          200 = "Ok",
     *          404 = "Not found",
     *     }
     * )
     * @Rest\Get()
     * @Rest\View(serializerGroups={"Default", "front", "media_basic", "media_detail"})
     *
     * @return \BNS\App\ClassroomBundle\Model\ClassroomNewspaper|View
     */
    public function todayAction()
    {
        $newspaper = $this->get('bns_app_classroom.newspaper_manager')->getForDate(date('Y-m-d'));
        if (!$newspaper) {
            return $this->view('', Codes::HTTP_NOT_FOUND);
        }

        return $newspaper;
    }

}

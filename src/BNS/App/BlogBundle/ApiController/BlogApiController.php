<?php

namespace BNS\App\BlogBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\BlogQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BlogApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class BlogApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Blog",
     *  resource = true,
     *  description="Activer ou désactiver le compteur de vues",
     * )
     *
     * @Rest\Patch("/{id}/count-views")
     * @Rest\View()
     */
    public function patchCountViewsAction(Request $request, $id)
    {
        if (!$this->hasFeature('blog_views')) {
            throw $this->createAccessDeniedException();
        }

        $blog = BlogQuery::create()->findPk($id);
        if (!$blog) {
            return  View::create('', Codes::HTTP_NOT_FOUND);
        }

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('BLOG_ADMINISTRATION', $blog->getGroupId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $state = json_decode($request->getContent());
        if ($state && isset($state->state)) {
            $blog->setCountView(!$state->state)->save();
        }


        return new JsonResponse(array('moderate' => $blog->getCountView()));

    }

}

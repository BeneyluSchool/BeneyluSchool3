<?php

namespace BNS\App\BlogBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BlogArticleApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class BlogArticleApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Blog - article",
     *  resource = true,
     *  description="Activer ou désactiver le compteur de vues",
     * )
     *
     * @Rest\Post("/{id}/views")
     * @Rest\View()
     */
    public function postViewArticleAction(Request $request, $id)
    {
        if (!$this->hasFeature('blog_views')) {
            throw $this->createAccessDeniedException();
        }

        $blogArticle = BlogArticleQuery::create()->findPk($id);
        if (!$blogArticle) {
            return  View::create('', Codes::HTTP_NOT_FOUND);
        }

        $rightManager = $this->get('bns.right_manager');

        $blogIds = $blogArticle->getBlogs()->getPrimaryKeys();
        $accessibleBlogIds = [];
        foreach ($rightManager->getGroupsWherePermission('BLOG_ACCESS') as $group) {
            $accessibleBlogIds[] = $group->getBlog()->getId();
        }
        if (!count(array_intersect($blogIds, $accessibleBlogIds))) {
            throw $this->createAccessDeniedException();
        }

        if (!$blogArticle->getblogReference()->getCountView()) {
            throw $this->createAccessDeniedException();
        }

        $redis = $this->get('snc_redis.default');
        $key = 'blog:view:' . $this->getCurrentUser()->getId() . ':' . $blogArticle->getId();
        if (!$redis->get($key)) {
           $redis->set($key, true);
           $redis->expire($key, 600);
           $blogArticle->setViewsNumber($blogArticle->getViewsNumber()+ 1 )->save();
        }
        return $this->view("", Codes::HTTP_OK);

    }

}

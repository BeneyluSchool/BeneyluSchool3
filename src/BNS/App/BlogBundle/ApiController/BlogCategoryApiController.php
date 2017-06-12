<?php

namespace BNS\App\BlogBundle\ApiController;


use BNS\App\BlogBundle\Form\Type\BlogCategoryType;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BlogCategoryApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class BlogCategoryApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Blogs",
     *  resource = true,
     *  description="Create a new category",
     * )
     *
     * @Rest\Post("/{id}/categories")
     * @Rest\View()
     */
    public function postCategoryAction(Request $request, $id)
    {
        $blog = BlogQuery::create()->findPk($id);
        if (!$blog) {
            return  View::create('', Codes::HTTP_NOT_FOUND);
        }

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('BLOG_ADMINISTRATION', $blog->getGroupId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $blogCategory = new BlogCategory();
        $blogCategory->setBlog($blog);
        $blogCategory->setIconClassname('default');
        return $this->restForm(new BlogCategoryType(), $blogCategory, [
            'csrf_protection' => false
        ], null, function($blogCategory, $form) use ($blog) {
            $rootCategory = BlogCategoryQuery::create()->findRoot($blog->getId());
            $blogCategory->insertAsLastChildOf($rootCategory);
            if ($blogCategory->getIconClassname() === null) {
                $blogCategory->setIconClassname('default');
            }
            $blogCategory->save();

            return $blogCategory;
        });

    }

}

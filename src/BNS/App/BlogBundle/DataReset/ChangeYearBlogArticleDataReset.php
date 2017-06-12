<?php

namespace BNS\App\BlogBundle\DataReset;

use BNS\App\BlogBundle\Form\Type\ChangeYearBlogArticleDataResetType;
use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CoreBundle\Model\BlogArticleBlog;
use BNS\App\CoreBundle\Model\BlogArticleBlogQuery;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearBlogArticleDataReset extends AbstractDataReset
{
    /**
     * @var string 
     */
    public $choice;

    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_blog_article';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        if ('KEEP' == $this->choice) {
            return;
        }

        // DELETE
        $blogId = BlogQuery::create('b')
            ->select('b.Id')
        ->findOneByGroupId($group->getId());

        $blogArticleBlog = BlogArticleBlogQuery::create()->findByBlogId($blogId);
        $blogArticleBlog->delete();

        $root = BlogCategoryQuery::create('ae')->findRoot($blogId);
        BlogCategoryQuery::create('bc')
            ->where('bc.Id != ?', $root->getId())
            ->where('bc.BlogId = ?', $blogId)
        ->delete();
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppBlogBundle:DataReset:change_year_blog_article.html.twig';
    }

    /**
     * @return ChangeYearBlogArticleDataResetType
     */
    public function getFormType()
    {
        return new ChangeYearBlogArticleDataResetType();
    }

    /**
     * @return array<String, String> 
     */
    public static function getChoices()
    {
        return array(
            'KEEP'     => 'KEEP_ALL_ARTICLE',
            'DELETE'   => 'DELETE_ALL_ARTICLE'
        );
    }
}

<?php
namespace BNS\App\BlogBundle\Manager;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\om\BaseBlogArticlePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BlogManager
{
    /** @var  BNSRightManager */
    protected $rightManager;

    /** @var BNSUserManager */
    protected $userManager;

    /** @var  Blog|null */
    protected $currentBlog;

    public function __construct(BNSRightManager $rightManager, BNSUserManager $userManager)
    {
        $this->rightManager = $rightManager;
        $this->userManager = $userManager;
    }

    /**
     * Test if a $user can access article in a manage mode
     * @param BlogArticle $article
     * @param User|null $user
     * @return bool
     * @throws \PropelException
     */
    public function canManageArticle(BlogArticle $article, User $user = null)
    {
        $user = $user ? : $this->rightManager->getUserSession();
        if (!$user) {
            return false;
        }

        if ($article->isNew()) {
            $blogIds = $article->getBlogs()->getPrimaryKeys(false);
            if (!count($blogIds)) {
                return true;
            }
            $blogGroupIds = GroupQuery::create()
                ->useBlogQuery()
                    ->filterById($blogIds)
                ->endUse()
                ->select(['Id'])
                ->find()
                ->getArrayCopy()
            ;
        } else {
            $blogGroupIds = GroupQuery::create()
                ->useBlogQuery()
                    ->useBlogArticleBlogQuery()
                        ->filterByArticleId($article->getId())
                    ->endUse()
                ->endUse()
                ->select(['Id'])
                ->find()
                ->getArrayCopy()
            ;
        }

        $right = 'BLOG_ACCESS_BACK';
        if ($user->getId() !== $article->getAuthorId()) {
            // user is not author so it need admin access
            $right = 'BLOG_ADMINISTRATION';
        }

        $groupIds = $this->userManager->getGroupIdsWherePermission($right);
        $isAllowed = count(array_intersect($blogGroupIds, $groupIds)) > 0;

        return $isAllowed;
    }

    /**
     * Test if a $user can edit this article
     * @param BlogArticle $article
     * @param User|null $user
     * @return bool
     * @throws \PropelException
     */
    public function canEditArticle(BlogArticle $article, User $user = null)
    {
        $user = $user ? : $this->rightManager->getUserSession();
        if (!$user) {
            return false;
        }

        $blogGroupIds = GroupQuery::create()
            ->useBlogQuery()
                ->useBlogArticleBlogQuery()
                    ->filterByArticleId($article->getId())
                ->endUse()
            ->endUse()
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;

        $groupIds = $this->userManager->getGroupIdsWherePermission('BLOG_ADMINISTRATION');
        $isAdmin = count(array_intersect($blogGroupIds, $groupIds)) > 0;
        if ($isAdmin) {
            // user is admin, no need for further check
            return true;
        }

        $right = 'BLOG_ACCESS_BACK';
        if ($user->getId() === $article->getAuthorId() && in_array($article->getStatus(), [
                BaseBlogArticlePeer::STATUS_PUBLISHED,
                BaseBlogArticlePeer::STATUS_FINISHED,
            ])) {
            $right = 'BLOG_PUBLISH';
        } else if ($user->getId() !== $article->getAuthorId() || !in_array($article->getStatus(), [
                BaseBlogArticlePeer::STATUS_DRAFT,
                BaseBlogArticlePeer::STATUS_WAITING_FOR_CORRECTION
            ])) {
            // user is not author or the article is not in an editable state
            $right = 'BLOG_ADMINISTRATION';
        }

        $groupIds = $this->userManager->getGroupIdsWherePermission($right);
        $isAllowed = count(array_intersect($blogGroupIds, $groupIds)) > 0;

        return $isAllowed;
    }

    public function getCurrentBlog()
    {
        if (null === $this->currentBlog) {
            $group = $this->rightManager->getCurrentGroup();
            if ($group) {
                $this->currentBlog = $group->getBlog();
            }
        }

        return $this->currentBlog;
    }
}

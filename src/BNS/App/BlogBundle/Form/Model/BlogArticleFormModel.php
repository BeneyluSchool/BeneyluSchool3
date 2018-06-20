<?php

namespace BNS\App\BlogBundle\Form\Model;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CorrectionBundle\Model\Correction;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;

use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\MediaLibraryBundle\Parser\PublicMediaParser;
use BNS\App\MediaLibraryBundle\Twig\MediaExtension;
use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Context\ExecutionContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BlogArticleFormModel
{
	public $title;
	public $draftTitle;
	public $content;
	public $draftContent;
	public $status;
	public $programmation_day;
	public $programmation_time;
	public $publication_day;
	public $publication_time;
	public $is_comment_allowed = true;
    public $blog_id;
    public $blog_ids;

	/**
	 * @var BlogArticle
	 */
	private $article;

    private $statService;
    /** @var PublicMediaParser */
    private $parseMediaService;

    /**
     * @param \BNS\App\CoreBundle\Model\BlogArticle $article
     */
    public function __construct($statService, $parseMediaService, BlogArticle $article = null, Blog $blog)
    {
        if (null == $article) {
            $this->article = new BlogArticle();
            $this->status = 'DRAFT';

            return;
        }

        $this->statService = $statService;
        $this->parseMediaService = $parseMediaService;
        $this->article = $article;
        $this->title = $article->getTitle();
        $this->draftTitle = ($article->getDraftTitle() !== "") ? $article->getDraftTitle(): $article->getTitle();
        $this->content = $article->getContent();
        $this->draftContent = $this->parseMediaService->parse((($article->getDraftContent() !== null) ? $article->getDraftContent(): $article->getContent()),true, $size = 'medium', true);
        $this->is_comment_allowed = $article->getIsCommentAllowed();
        $this->blog_id = $blog->getId();
        if ($this->article->isNew()) {
            // set current blog to default blog if article is new
            $this->blog_ids = [$this->blog_id];
        } else {
            // fill saved blog otherwise
            $this->blog_ids = $article->getBlogs()->getPrimaryKeys();
        }

        $this->status = $article->getStatus();
        if ($article->isProgrammed()) {
            $this->status = 'PROGRAMMED';
        }

        if ($article->isProgrammed()) {
            $this->programmation_day = $article->getPublishedAt();
            $this->programmation_time = $article->getPublishedAt()->getTime();
        } elseif ($article->isPublished()) {
            $this->publication_day = $article->getPublishedAt();
            $this->publication_time = $article->getPublishedAt()->getTime();
        }
    }

	public function getCategories ()
	{
		return $this->article->getBlogCategories();
	}

	public function setCategories ($categories)
	{
		return $this->article->setBlogCategories($categories);
	}

	/**
	 * Void
	 */
	public function preSave()
	{
	    $this->article->setDraftTitle($this->draftTitle);
	    $this->article->setDraftContent($this->draftContent);
	}

    public function getContent()
    {
        $container = BNSAccess::getContainer();
        $ext = new MediaExtension($container);
        return $ext->parsePublicResources($this->article->getContent(),true, $size = 'medium', true);
    }

	/**
	 * @param \BNS\App\CoreBundle\Right\BNSRightManager $rightManager
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * @param \BNS\App\MediaLibraryBundle\Manager\MediaManager $mediaManager
	 * @param \Symfony\Component\HttpFoundation\Request $request
	 * @param \BNS\App\CoreBundle\Model\Blog $blog
     * @param bool $isAutosave
	 *
	 * @throws \RuntimeException
	 */
	public function save(BNSRightManager $rightManager, User $user, MediaManager $mediaManager, Request $request, Blog $blog = null, $isAutosave = false)
	{
		$this->preSave();

		// Process for new article only
		if ($this->article->isNew()) {
			$this->article->setCreatedAt(time());

			if (null == $blog) {
				throw new \RuntimeException('The blog can NOT be null !');
			}
            $this->article->addBlog($blog);
			$this->article->setBlogReferenceId($blog->getId());
			$this->article->setAuthorId($user->getId());
		} elseif ($blog != null && $this->blog_id != null && $blog->getId() != $this->blog_id) {
            $newBlog = BlogQuery::create()->findOneById($this->blog_id);
            $rightManager->forbidIfHasNotRight(Blog::PERMISSION_BLOG_ADMINISTRATION,$newBlog->getGroupId());
            $this->article->addBlog($newBlog);
            $this->article->setBlogReferenceId($this->blog_id);
        }

        if (!($isAutosave && $this->status !== 'DRAFT')) {
            $this->article->setContent($this->article->getDraftContent());
            $this->article->setTitle($this->article->getDraftTitle());
        }
        if (!$isAutosave) {
            $this->article->setUpdatedAt(time());
            $this->article->setUpdater($user);
        }

		if ($rightManager->hasRight(Blog::PERMISSION_BLOG_ADMINISTRATION) || ($rightManager->hasRight('BLOG_PUBLISH') && ($this->article->isNew() || $user->getId() === $this->article->getAuthorId()))) {
			if ($this->status == BlogArticlePeer::STATUS_PUBLISHED || $this->status == 'PROGRAMMED') {
				$this->article->setStatus(BlogArticlePeer::STATUS_PUBLISHED);

				if ($this->status == 'PROGRAMMED') {
					$this->article->setPublishedAt(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time);
				}
				elseif ($this->article->isNew()) {
					$this->article->setPublishedAt(time());
				}
				elseif (null != $this->publication_day && null != $this->publication_time) {
					$this->article->setPublishedAt(date('Y-m-d', $this->publication_day->getTimestamp()) . ' ' . $this->publication_time);
				}
				elseif (!$this->article->isPublished()) {
					$this->article->setPublishedAt(time());
				}

                //statistic action
                $this->statService->publishArticle();
            } else {
				$this->article->setStatus($this->status);
				$this->article->setPublishedAt(null);
			}

			$this->article->setIsCommentAllowed($this->is_comment_allowed);
		} else {
			if ($isAutosave && $this->status === BlogArticlePeer::STATUS_DRAFT) {
                $this->article->setStatus(BlogArticlePeer::STATUS_DRAFT);
            } elseif ($isAutosave && $this->status === BlogArticlePeer::STATUS_WAITING_FOR_CORRECTION) {
                $this->article->setStatus(BlogArticlePeer::STATUS_WAITING_FOR_CORRECTION);
            } else {
                $this->article->setStatus(BlogArticlePeer::STATUS_FINISHED);
            }
			$this->article->setPublishedAt(null);
		}

		$this->article->save();

		// Attached files process
		$mediaManager->saveAttachments($this->article, $request);


	}

	/**
	 * @return BlogArticle
	 */
	public function getArticle()
	{
		return $this->article;
	}

	/**
	 * Constraint validation
	 */
    public function isStatusExists($context)
	{
		$statuses = BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS);
		$statuses[] = 'PROGRAMMED'; // custom status

		if (!in_array($this->status, $statuses)) {
				$context->buildViolation('LABEL_STATUS_NO_CORRECT')
					->atPath('status')
					->setTranslationDomain('BLOG')
					->addViolation();
		}
	}

	/**
	 * Constraint validation
	 */
    public function isProgrammationValid(ExecutionContext $context)
	{
		if ($this->status == 'PROGRAMMED') {
			if (!$this->programmation_day instanceof \DateTime) {
                $context->buildViolation('INVALID_DATE')
                    ->atPath('programmation_day')
                    ->setTranslationDomain('BLOG')
                    ->addViolation();
            } elseif (!preg_match('/^((([0]?[1-9]|1[0-2])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?( )?(AM|am|aM|Am|PM|pm|pM|Pm))|(([0]?[0-9]|1[0-9]|2[0-3])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?))$/', $this->programmation_time)) {
                $context->buildViolation('INVALID_HOUR')
                    ->atPath('programmation_time')
                    ->setTranslationDomain('BLOG')
                    ->addViolation();
            } elseif (date('Y-m-d', $this->programmation_day->getTimestamp()) < date('Y-m-d', time()) || strtotime(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time) < time()) {
                $context->buildViolation('DATETIME_FUTURE')
                    ->atPath('programmation_day')
                    ->setTranslationDomain('BLOG')
                    ->addViolation();
			}
		}
	}

    public function isBlogAllowed(ExecutionContext $context)
    {
        if(count($this->blog_ids) == 0)
        {
            $context->buildViolation('INVALID_CHOOSE_ONE_BLOG')
                ->atPath('blog_ids')
                ->setTranslationDomain('BLOG')
                ->addViolation();
        }
    }

    public function getBlogIds()
    {
        return
            $this->article->getBlogs()->getPrimaryKeys()
        ;
    }

    public function setBlogIds($blogIds)
    {
        if($blogIds != null)
        {
            $this->article->setBlogs(BlogQuery::create()->findById($blogIds));
        }else{
            $this->article->setBlogs(BlogQuery::create()->findById($this->blog_id));
        }
    }

    public function getCorrection()
    {
        if ($this->article) {
            if (!$this->article->getCorrection() && !$this->article->isNew()) {
                $this->article->setCorrection(new Correction());
            }
            return $this->article->getCorrection();
        }

        return null;
    }

    public function setCorrection(Correction $correction)
    {
        if ($this->article) {
            $this->article->setCorrection($correction);
        }
    }

}

<?php
namespace BNS\App\CoreBundle\Blackboard;

use BNS\App\ClassroomBundle\Model\GroupBlackboard;
use BNS\App\ClassroomBundle\Model\GroupBlackboardQuery;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BlackboardManager
{
    /** @var  BNSRightManager */
    protected $rightManager;

    public function __construct(BNSRightManager $rightManager)
    {
        $this->rightManager = $rightManager;
    }

    public function getBlackboard(Group $group)
    {
        return GroupBlackboardQuery::create()
            ->filterByGroup($group)
            ->findOne();
    }

    /**
     * @param Group $group
     * @return array
     * @throws \PropelException
     */
    public function getLastNewsForGroup(Group $group)
    {
        $blackboard = $this->getBlackboard($group);
        if ($blackboard) {
            return $this->getLastNews($blackboard, $group);
        }

        return array();
    }

    /**
     * @param GroupBlackboard $blackboard
     * @param Group $group
     * @param int $nbElement
     * @return array
     * @throws \PropelException
     */
    public function getLastNews(GroupBlackboard $blackboard, Group $group, $nbElement = 8)
    {
        $lastFlux = array();
        if ($blackboard) {
            // Récupération des derniers articles
            $blog = BlogQuery::create()->filterByGroup($group)->findOne();
            if ($blog && $this->rightManager->hasRight('BLOG_ACCESS', $group->getId())) {
                $articles = BlogArticleQuery::create()
                    ->filterByBlog($blog)
                    ->isPublished()
                    ->joinWith('User')
                    ->orderByPublishedAt('desc')
                    ->limit($nbElement)
                    ->find()
                ;

                /** @var BlogArticle $article */
                foreach ($articles as $article) {
                    $title = $article->getTitle();
                    $slug = $article->getSlug();
                    $author = $article->getUser();
                    $authorFull = $author->getFullName();
                    $dateC = $article->getPublishedAt();
                    $date = $dateC->format('m/d/y H:i:s');
                    $blogFlux = [
                        'date' => $date,
                        'date_display' => $dateC,
                        'title' => $title,
                        'author' => $author,
                        'author_full' => $authorFull,
                        'flux_type' => 'blog',
                        'slug' => $slug
                    ];
                    array_push($lastFlux, $blogFlux);
                }
            }


            if (in_array($blackboard->getFlux(), [GroupBlackboard::FLUX_BLOG_MEDIA_LIBRARY, GroupBlackboard::FLUX_ALL])
                && $this->rightManager->hasRight('MEDIA_LIBRARY_ACCESS', $group->getId())
            ) {
                // Récupérations des derniers médias
                $groupsFoldersIds = MediaFolderGroupQuery::create()
                    ->filterByIsPrivate(false)
                    ->filterByStatusDeletion(MediaFolderManager::STATUS_ACTIVE)
                    ->filterByGroupId($group->getId())
                    ->select(MediaFolderGroupPeer::ID)
                    ->find()
                    ->toArray()
                ;

                $mediaGroups = MediaQuery::create()
                    ->filterByStatusDeletion(1)
                    ->filterByIsPrivate(false)
                    ->filterByMediaFolderType('GROUP')
                    ->filterByMediaFolderId($groupsFoldersIds)
                    ->orderByCreatedAt('desc')
                    ->limit($nbElement)
                    ->find();

                /** @var Media $media */
                foreach ($mediaGroups as $media) {
                    $title = $media->getLabel();
                    $id = $media->getId();
                    $dateC = $media->getCreatedAt();
                    $date = $dateC->format('m/d/y H:i:s');
                    $authorId = $media->getUserId();
                    $author = UserQuery::create()->findOneById($authorId);
                    $authorFull = $author->getFullName();
                    $mediaFlux = [
                        'date' => $date,
                        'date_display' => $dateC,
                        'title' => $title,
                        'author' => $author,
                        'author_full' => $authorFull,
                        'flux_type' => 'media',
                        'id' => $id
                    ];
                    array_push($lastFlux, $mediaFlux);
                }
            }

            if (GroupBlackboard::FLUX_ALL === $blackboard->getFlux()) {
                if ($this->rightManager->hasRight('LIAISONBOOK_ACCESS', $group->getId())) {
                    // Récupération des messages du carnet de liaison
                    $messages = LiaisonBookQuery::create()
                        ->filterByGroupId($group->getId())
                        ->orderByCreatedAt('desc')
                        ->limit($nbElement)
                        ->find();

                    /** @var LiaisonBook $message */
                    foreach ($messages as $message) {
                        $title = $message->getTitle();
                        $author = $message->getAuthorId();
                        $author = UserQuery::create()
                            ->findOneById($author);
                        $authorFull = $author->getFullName();
                        $dateC = $message->getCreatedAt();
                        $slug = $message->getSlug();
                        $date = $dateC->format('m/d/y H:i:s');
                        $liaisonBookFlux = array("date" => $date, "author" => $author, "author_full" => $authorFull, "date_display" => $dateC,  "title" => $title, "flux_type" => "liaison_book", "slug" => $slug);
                        array_push($lastFlux, $liaisonBookFlux);
                    }
                }

                // TODO find minsite pages published order by updatedAt
//                $minisite = MinisiteQuery::create()->findByGroupId($group->getId());
//                if ($minisite && $this->rightManager->hasRight('MINISITE_ACCESS', $group->getId())) {
//                    // Récupération des dernières news du minisite
//                    $minisitePages = MinisitePageQuery::create()
//                        ->findOneByMiniSiteId($minisite->getId());
//                }
            }
            // Tri par date
            usort($lastFlux, function($a1, $a2) {
                $v1 = strtotime($a1['date']);
                $v2 = strtotime($a2['date']);
                return $v2 - $v1;
            });
            $lastFlux = array_slice($lastFlux, 0, $nbElement);
        }

        return $lastFlux;
    }
}

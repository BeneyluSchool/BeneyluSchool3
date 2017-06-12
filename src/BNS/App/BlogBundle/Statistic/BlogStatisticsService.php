<?php
namespace BNS\App\BlogBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au blog bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class BlogStatisticsService extends StatisticsService
{

    /**
     * Création d'un article
     */
    public function newArticle()
    {
        $this->increment("BLOG_CREATE_ARTICLE");
    }

    /**
     * Publication d'un article
     */
    public function publishArticle()
    {
        $this->increment("BLOG_PUBLISH_ARTICLE");
    }

    /**
     * Création d'un commentaire sur un article
     */
    public function newCommentArticle()
    {
        $this->increment("BLOG_CREATE_COMMENT-ARTICLE");
    }

    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('BLOG_VISIT');
        $this->enableCascadeParentGroup();
    }
}

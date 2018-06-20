<?php
namespace BNS\App\MediaLibraryBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au resource bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 */
class MediaStatisticsService extends StatisticsService
{
    /**
     * recherche
     */
    public function newSearch()
    {
        $this->increment("MEDIA_DO_SEARCH");
    }

    /**
     * document ajouté
     */
    public function newFile()
    {
        $this->increment("MEDIA_ADD_FILE");
    }

    /**
     * Evolution de l'espace autorisé
     */
    public function changeAllowedSpace()
    {
        $this->increment("MEDIA_CHANGE_ALLOWED_SPACE");
    }

    public function visit()
    {
        $this->increment('MEDIALIBRARY_VISIT');
    }
}

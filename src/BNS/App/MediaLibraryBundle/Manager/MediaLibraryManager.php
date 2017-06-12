<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\MediaLibraryBundle\Model\Media;

/**
 * Class MediaLibraryManager
 *
 * @package BNS\App\MediaLibraryBundle\Manager
 */
class MediaLibraryManager
{

    /**
     * @var string
     */
    protected $resourceFilesDir;

    public function __construct($resourceFilesDir)
    {
        $this->resourceFilesDir = $resourceFilesDir;
    }

    /**
     * Supprime tous les fichiers associés au média, excepté le fichier original
     */
    public function cleanMediaDirectory(Media $media)
    {
        $path = $this->resourceFilesDir . '' . $media->getFilePathPattern();
        $this->recursiveDeleteDir($path, array($media->getFilename()));
    }

    /**
     * Supprime le dossier associé au média
     */
    public function deleteMediaDirectory(Media $media)
    {
        $path = $this->resourceFilesDir . '' . $media->getFilePathPattern();
        $this->recursiveDeleteDir($path);
    }

    /**
     * Supprime le dossier donné et tout son contenu, excepté les fichiers/dossiers qui matchent la liste blanche.
     *
     * @TODO: Déplacer dans un service plus générique
     *
     * @param string $path
     * @param array $whitelist Liste blanche
     * @return bool Si la suppression a été effectuée
     */
    protected function recursiveDeleteDir($path, $whitelist = array())
    {
        // directory not found
        if (!is_dir($path)) {
            return false;
        }

        $keepRoot = false;

        $files = array_diff(scandir($path), array('.', '..'));
        foreach ($files as $file) {
            if (in_array($file, $whitelist)) {
                $keepRoot = true;
                continue;
            }

            if (is_dir($path.'/'.$file)) {
                $this->recursiveDeleteDir($path.'/'.$file);
            } else {
                @unlink($path.'/'.$file);
            }
        }

        return $keepRoot ? true : rmdir($path);
    }

}
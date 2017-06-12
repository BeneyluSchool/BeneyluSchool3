<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\CoreBundle\Cleaner\FileCleaner;
use BNS\App\MediaLibraryBundle\Model\Media;
use Gaufrette\Adapter\Zip;

/**
 * Class MediaArchiveManager
 *
 * @package BNS\App\MediaLibraryBundle\Manager
 */
class MediaArchiveManager
{

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var FileCleaner
     */
    protected $fileCleaner;

    /**
     * Remember paths of generated archives, for cleanup
     *
     * @var array
     */
    protected $tmpPaths = array();

    public function __construct(MediaManager $mediaManager, FileCleaner $fileCleaner)
    {
        $this->mediaManager = $mediaManager;
        $this->fileCleaner = $fileCleaner;
    }

    /**
     * Creates a zip archive with the given medias. Returns the full path where the archive resides.
     *
     * @param Media[] $medias
     * @return string
     */
    public function create($medias)
    {
        $path = tempnam(sys_get_temp_dir(), 'BNS');
        $archive = new Zip($path);

        foreach ($medias as $media) {
            $this->mediaManager->setMediaObject($media);
            $content = $this->mediaManager->read();
            if ($content) {
                // build file name from user-friendly label + rand + actual file extension
                $ext = pathinfo($media->getFilename(), PATHINFO_EXTENSION);
                $rand = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $filename = $media->getLabel();                             // start from user-defined label
                $filename = preg_replace('#\.'.$ext.'$#', '', $filename);   // remove extension if any
                $filename .= '-' . $rand . '.' . $ext;                      // add a random component and the actual file extension
                $archive->write($filename, $content);
            }
        }

        $this->fileCleaner->add($path);

        return $path;
    }

}

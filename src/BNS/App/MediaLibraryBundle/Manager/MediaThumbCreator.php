<?php
namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use Gaufrette\Adapter;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaThumbCreator
{
    protected $fileSystemManager;

    protected $localAdapter;

    protected $logger;

    public static $thumbnails = array(
        'micro'                  => array(30,30),
        'thumbnail'              => array(60,60),
        'board'                 => array(100, 100),
        'small'                  => array(180,180),
        'favorite'               => array(300,140),
        'medium'                 => array(600,400),
        'large'                  => array(1200,800),
        'banner_minisite_front'  => array(1150, 200),
        'banner_minisite_back'   => array(1150, 200),
        'portal_banner'          => array(770, 190)
    );

    public function __construct(BNSFileSystemManager $fileSystemManager, Adapter $localAdapter, LoggerInterface $logger)
    {
        $this->fileSystemManager = $fileSystemManager;
        $this->localAdapter = $localAdapter;
        $this->logger = $logger;
    }

    public function createLocalThumbForKey($originalPath, $resizePath, $size)
    {
        /** @var Adapter $adapter */
        $adapter = $this->fileSystemManager->getAdapter();

        if (!isset(self::$thumbnails[$size])) {
            return false;
        }

        if (!$originalData = $adapter->read($originalPath)) {
            return false;
        }
        $imagine = new Imagine();
        if (!$imageInfo = @getimagesizefromstring($originalData)) {
            return false;
        }
        $extension = pathinfo($originalPath, \PATHINFO_EXTENSION);

        $largeur = $imageInfo[0]; // largeur de l'image
        $hauteur = $imageInfo[1]; // hauteur de l'image

        $palette = new RGB();
        $color = $palette->color(array(255, 255, 255));

        //Si l'image est strictement moins large ET moins haute que le thumbnail demandé alors
        //On crée un thumbnail blanc avec l'image au milieu non redimmensionnée
        if (self::$thumbnails[$size][0] > $largeur && self::$thumbnails[$size][1] > $hauteur) {
            if ($size === 'medium') {
                //Medium utilisé pour les insertions on ne redimensionne pas les petits images
                $this->localAdapter->write($resizePath, $originalData);

                return true;
            }

            //Remplir de blanc le thumbnail
            $squaredSize = new Box(self::$thumbnails[$size][0], self::$thumbnails[$size][1]);


            //Création du rendu final
            $final = $imagine->create($squaredSize, $color);

            //On ouvre l'image
            $image = $imagine->load($originalData);

            //On la colle au milieu du rendu final
            $x = (self::$thumbnails[$size][0] / 2) - ($largeur / 2);
            $y = (self::$thumbnails[$size][1] / 2) - ($hauteur / 2);

            try {
                $final->paste($image, new Point($x, $y));

                $this->localAdapter->write($resizePath, $final->get($extension));

                return true;
            } catch (\Exception $exception) {
                $this->logger->error('Error while trying to create thumb :' . $exception->getMessage());

                return false;
            }
        }
        //Si l'image est strictement moins large OU moins haute que le thumbnail demandé alors
        //On doit redimmensionner en pourcentage pour que l'image rentre correctement dans le bandeau
        else {
            if (self::$thumbnails[$size][0] > $largeur || self::$thumbnails[$size][1] > $hauteur) {
                // Pourcentage de resize
                if (self::$thumbnails[$size][0] < $largeur) {
                    $percentResize = self::$thumbnails[$size][0] / $largeur;
                } else {
                    $percentResize = self::$thumbnails[$size][1] / $hauteur;
                }

                //Remplir de blanc
                $squaredSize = new Box(self::$thumbnails[$size][0], self::$thumbnails[$size][1]);


                //Création du rendu final
                $final = $imagine->create($squaredSize, $color);

                //On ouvre l'image
                $image = $imagine->load($originalData);

                if ($size === "medium") {
                    $mode = ImageInterface::THUMBNAIL_INSET;
                } else {
                    $mode = ImageInterface::THUMBNAIL_OUTBOUND;
                }
                //Taille de l'image redimmensionnée proportionnellement
                $resizedImageSize = new Box($largeur * $percentResize, $hauteur * $percentResize);

                //On colle l'image au milieu
                $x = (self::$thumbnails[$size][0] / 2) - ($largeur * $percentResize / 2);
                $y = (self::$thumbnails[$size][1] / 2) - ($hauteur * $percentResize / 2);

                // Corrige un bug image outofbound problème de précision
                if ($x < 0) {
                    $x = 0;
                }
                if ($y < 0) {
                    $y = 0;
                }

                // Coller l'image redimmensionnée
                $final->paste($image->thumbnail($resizedImageSize, $mode), new Point($x, $y));
                // Création des thumbs en local
                $this->localAdapter->write($resizePath, $final->get($extension));

                return true;
            }
            //Si l'image est strictement plus large ET plus haute que le thumbnail demandé alors
            //On fait un resize et le rendu peut dépasser
            else {
                $boxWidth = self::$thumbnails[$size][0];
                $boxHeight = self::$thumbnails[$size][1];
                $ratio = $largeur / $hauteur;
                if ($size === "medium" && $ratio < 1) {
                    $tmp = $boxHeight;
                    $boxHeight = $boxWidth;
                    $boxWidth = $tmp;
                }

                $squaredSize = new Box($boxWidth, $boxHeight);
                if ($size === "medium") {
                    $mode = ImageInterface::THUMBNAIL_INSET;
                    $final = $imagine->load($originalData)->thumbnail($squaredSize, $mode);
                } else {
                    $mode = ImageInterface::THUMBNAIL_OUTBOUND;
                    $final = $imagine->load($originalData)->thumbnail($squaredSize, $mode)->resize($squaredSize);
                }
                //Création des thumbs en local (repertoire temporaire)


                $this->localAdapter->write($resizePath, $final->get($extension));

                return true;
            }
        }
    }
}

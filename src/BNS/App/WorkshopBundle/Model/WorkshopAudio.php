<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopAudio;

/**
 * Class WorkshopAudio
 *
 * @package BNS\App\WorkshopBundle\Model
 *
 * @method string getType()
 * @method User getAuthor()
 * @method WorkshopDocument setAuthor()
 * @method Media getMedia()
 * @method WorkshopDocument setMedia(Media $media)
 */
class WorkshopAudio extends BaseWorkshopAudio implements WorkshopContentInterface
{

    /**
     * Gets the id of the associated media
     *
     * @return int
     */
    public function getMediaId()
    {
        return $this->getMedia()->getId();
    }

    /**
     * Gets the label of the associated media
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->getMedia()->getLabel();
    }

}

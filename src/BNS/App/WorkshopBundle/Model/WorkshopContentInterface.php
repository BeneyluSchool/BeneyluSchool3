<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\Media;

/**
 * Interface WorkshopContentInterface
 *
 * @package BNS\App\WorkshopBundle\Model
 *
 * @method string getType()
 * @method User getAuthor()
 * @method WorkshopDocument setAuthor()
 * @method Media getMedia()
 * @method WorkshopDocument setMedia(Media $media)
 */
interface WorkshopContentInterface
{

    const TYPE_DOCUMENT = 'ATELIER_DOCUMENT';
    const TYPE_AUDIO = 'ATELIER_AUDIO';

    /**
     * @return WorkshopContent
     */
    public function getWorkshopContent();

    /**
     * @param WorkshopContent $v
     * @return $this
     */
    public function setWorkshopContent(WorkshopContent $v = null);

    /**
     * Gets the id of the associated media
     *
     * @return int
     */
    public function getMediaId();

    /**
     * Gets the label of the associated media
     *
     * @return string
     */
    public function getLabel();

}

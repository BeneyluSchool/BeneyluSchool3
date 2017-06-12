<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\om\BaseWorkshopDocument;
use BNS\App\CoreBundle\Model\User;

/**
 * Class WorkshopDocument
 *
 * @package BNS\App\WorkshopBundle\Model
 *
 * @method string getType()
 * @method User getAuthor()
 * @method WorkshopDocument setAuthor()
 * @method Media getMedia()
 * @method WorkshopDocument setMedia(Media $media)
 */
class WorkshopDocument extends BaseWorkshopDocument implements WorkshopContentInterface
{

    const STATUS_EDITABLE = 'e';

    const STATUS_LOCKED = 'l';

    /**
     * Renvoie le nom du document qui est stocké dans la table resource
     * @return mixed
     */
    public function getLabel()
    {
        return $this->getMedia()->getLabel();
    }

    public function getMediaId()
    {
        return $this->getMedia()->getId();
    }

    /**
     * Ajoute une page
     * @param $layout
     * @param $position
     * @param $orientation
     * @return WorkshopPage
     */
    public function addPage($layout, $position, $orientation)
    {
        $page = new WorkshopPage();
        $page->setDocumentId($this->getId());
        $page->setLayoutCode($layout);
        $page->setPosition($position);
        $page->setOrientation($orientation);
        $page->save();
        return $page;
    }

    public function getWorkshopPagesArray()
    {
        $return = array();
        foreach($this->getWorkshopPages() as $workshopPage)
        {
            $return[] = $workshopPage;
        }
        return $return;
    }

    public function getWidgetGroups()
    {
        return WorkshopWidgetGroupQuery::create()
            ->useWorkshopPageQuery()
                ->filterByWorkshopDocument($this)
            ->endUse()
            ->find()
        ;
    }

    public function isLocked()
    {
        return self::STATUS_LOCKED === $this->getStatus();
    }

    public function isEditable()
    {
        return self::STATUS_EDITABLE === $this->getStatus();
    }

    public function isQuestionnaire()
    {
        $documentType = parent::getDocumentType();

        if ($documentType == 2) {
            return true;
        }

        return false;
    }
}

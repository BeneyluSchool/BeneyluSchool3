<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopContent;

class WorkshopContent extends BaseWorkshopContent
{

    public function getLabel()
    {
        if ($media = $this->getMedia()) {
            return $media->getLabel();
        }

        return '';
    }

    public function isDocument()
    {
        return WorkshopContentPeer::TYPE_DOCUMENT === $this->getType();
    }

    public function isAudio()
    {
        return WorkshopContentPeer::TYPE_AUDIO === $this->getType();
    }

}

<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopContent;

class WorkshopContent extends BaseWorkshopContent
{

    public function getLabel()
    {
        return $this->getMedia()->getLabel();
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

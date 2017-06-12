<?php

namespace BNS\App\CampaignBundle\Model;

use BNS\App\CampaignBundle\Model\om\BaseCampaign;
use BNS\App\CoreBundle\RichText\RichTextParser;

class Campaign extends BaseCampaign
{
    use RichTextParser;

    public function getTypeName() {
        $type = $this->getType();

        switch ((string)$type) {
            case CampaignPeer::CLASSKEY_CAMPAIGNEMAIL:
                return "EMAIL";
            case CampaignPeer::CLASSKEY_CAMPAIGNMESSAGING:
                return "MESSAGING";
            case CampaignPeer::CLASSKEY_CAMPAIGNSMS:
                return "SMS";
        }

    }

    public function getRichMessage()
    {
        // do not parse SMS text
        if (CampaignPeer::CLASSKEY_CAMPAIGNSMS === $this->getType()) {
            return parent::getMessage();
        }

        return $this->parse(parent::getMessage());
    }
}

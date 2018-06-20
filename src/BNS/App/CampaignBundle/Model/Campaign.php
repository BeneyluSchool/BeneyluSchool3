<?php

namespace BNS\App\CampaignBundle\Model;

use BNS\App\CampaignBundle\Model\om\BaseCampaign;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\UserQuery;
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

    public function getNumberOfSentMessages()
    {
        if ($this->getStatus() !== CampaignPeer::STATUS_SENT) {
            return;
        }
        return CampaignRecipientQuery::create()->filterByCampaignId($this->getId())
            ->filterByIsSent(true)->count();
    }

    public function getNotSentMessages()
    {
        $rightManager = BNSAccess::getContainer()->get('bns.right_manager');
        $user = $rightManager->getUserSession();
        $usersNotSentTo = UserQuery::create()->useCampaignRecipientQuery()
            ->filterByIsSent(false)
            ->filterByCampaignId($this->getId())
            ->endUse()
            ->find();
        if($rightManager->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $this->getGroupId())) {
            return $usersNotSentTo;
        }
        return count($usersNotSentTo);
    }
}

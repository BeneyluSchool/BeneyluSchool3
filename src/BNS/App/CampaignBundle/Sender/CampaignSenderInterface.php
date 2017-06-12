<?php

namespace BNS\App\CampaignBundle\Sender;

use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CoreBundle\Model\User;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface CampaignSenderInterface
{
    /**
     * @param Campaign $campaign the campaign to send
     * @param $users User[]|array a list of user to send the message
     * @return boolean true on success
     */
    public function send(Campaign $campaign, $users);

    /**
     * return true if this sender support this campaign
     * @param Campaign $campaign the campaign
     * @return boolean
     */
    public function support(Campaign $campaign);
}

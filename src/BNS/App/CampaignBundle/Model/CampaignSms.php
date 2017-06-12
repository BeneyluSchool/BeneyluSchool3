<?php

namespace BNS\App\CampaignBundle\Model;



/**
 * Skeleton subclass for representing a row from one of the subclasses of the 'campaign' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CampaignBundle.Model
 */
class CampaignSms extends Campaign {

    /**
     * Constructs a new CampaignSms class, setting the type column to CampaignPeer::CLASSKEY_4.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType(CampaignPeer::CLASSKEY_4);
    }

} // CampaignSms

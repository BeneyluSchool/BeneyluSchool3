<?php

namespace BNS\App\PupilMonitoringBundle\Model;

use BNS\App\PupilMonitoringBundle\Model\om\BasePupilLpcLinkQuery;
use BNS\App\PupilMonitoringBundle\Model\PupilLpcQuery;

class PupilLpcLinkQuery extends BasePupilLpcLinkQuery
{
    public static function handleLink($user,$lpcSlug,$date)
    {
        $lpc = PupilLpcQuery::create()
            ->findoneBySlug($lpcSlug);
        if(!$lpc)
        {
            return false;
        }
        
        $link = self::create()
                ->filterByUserId($user->getId())
                ->filterByPupilLpcId($lpc->getid())
                ->findOneOrCreate();
        
        if($date != 'null')
        {
            $link->setDate($date);
            $link->save();
        }else{
            $link->delete();
        }
        return true;
    }
    
    public static function getOrderedLinks($user)
    {
        $links = self::create()
            ->select(array('pupil_lpc_id','date'))
            ->filterByUserId($user->getId())
            ->find();
        $return = array();
        foreach($links as $link)
        {
            $return[$link['pupil_lpc_id']] = $link['date'];
        }
        return $return;
    }
}

<?php

namespace BNS\App\PupilMonitoringBundle\Model;

use BNS\App\PupilMonitoringBundle\Model\om\BasePupilAbsenceQuery;
use BNS\App\PupilMonitoringBundle\Model\om\BasePupilAbsencePeer;

class PupilAbsenceQuery extends BasePupilAbsenceQuery
{
    public static function getAbsenceFromDatas($user,$date,$group)
    {
        return self::create()
            ->filterByUserId($user->getId())
            ->filterByDate($date)
            ->filterByGroupId($group->getId())
            ->findOneOrCreate();
    }
    
    public static function handleAbsence($user,$date,$group,$type)
    {
        $absence = self::getAbsenceFromDatas($user,$date,$group);
        
        if($absence->isNew())
        {
            $absence->setType($type);
            $absence->save();
        }else{
            if(
                $absence->getType() == BasePupilAbsencePeer::TYPE_MORNING && $type == BasePupilAbsencePeer::TYPE_AFTERNOON ||
                $absence->getType() == BasePupilAbsencePeer::TYPE_AFTERNOON && $type == BasePupilAbsencePeer::TYPE_MORNING
            ){
                $absence->setType(BasePupilAbsencePeer::TYPE_DAY);
                $absence->save();
            }elseif($absence->getType() == BasePupilAbsencePeer::TYPE_DAY){
                if($type == BasePupilAbsencePeer::TYPE_AFTERNOON){
                    $absence->setType(BasePupilAbsencePeer::TYPE_MORNING);
                }elseif($type == BasePupilAbsencePeer::TYPE_MORNING){
                    $absence->setType(BasePupilAbsencePeer::TYPE_AFTERNOON);
                }
                $absence->save();
            }elseif($absence->getType() == $type){
                $absence->delete();
            }
        }
    }
    
    public static function handleLegitimate($user,$date,$group,$legitimate)
    {
        $absence = self::getAbsenceFromDatas($user,$date,$group);
        $absence->setIsLegitimate($legitimate);
        $absence->save();
    }
    
    public static function getOrderedAbsences($date,$groupId,$dateEnd = null)
    {
        $absences = self::create()
            ->select(array('userId', 'type', 'is_legitimate', 'date'))
            ->filterByGroupId($groupId)
            ->_if($dateEnd == null)
                ->filterByDate($date)
            ->_else()
                ->condition('cond1','pupil_absence.date >= ?',$date)
                ->condition('cond2','pupil_absence.date < ?',$dateEnd)
                ->where(array('cond1', 'cond2'), 'and')
            ->_endIf()
            ->find();
        $orderedAbsences = array();
        foreach($absences as $absence)
        {
            if($dateEnd != null)
            {
                $orderedAbsences[$absence['userId']][$absence['date']]['type'] = $absence['type'];
                $orderedAbsences[$absence['userId']][$absence['date']]['legitimate'] = $absence['is_legitimate'];
            }else{
                $orderedAbsences[$absence['userId']]['type'] = $absence['type'];
                $orderedAbsences[$absence['userId']]['legitimate'] = $absence['is_legitimate'];
            }
        }
        return $orderedAbsences;
    }
    
    public static function getPupilAbsences($pupil,$groupId)
    {
        return self::create()
            ->filterByUserId($pupil->getId())
            ->filterByGroupId($groupId)
            ->orderByDate(\Criteria::DESC)
            ->find();
    }
}

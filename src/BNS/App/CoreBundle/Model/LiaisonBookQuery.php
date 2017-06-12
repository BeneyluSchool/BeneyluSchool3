<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseLiaisonBookQuery;
use \Criteria;


/**
 * Skeleton subclass for performing query and update operations on the 'liaison_book' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class LiaisonBookQuery extends BaseLiaisonBookQuery {
    
    public function findByGroupIdAndDate($group_id, $month, $year)
    {
        //Criteria dates and group_id
        $criterias = new Criteria();  
        $criterias->add(LiaisonBookPeer::GROUP_ID, $group_id, Criteria::EQUAL);
	$criterias->add(LiaisonBookPeer::DATE,"YEAR(". LiaisonBookPeer::DATE .") = $year AND MONTH(". LiaisonBookPeer::DATE .") = $month" ,Criteria::CUSTOM);
        $criterias->addDescendingOrderByColumn(LiaisonBookPeer::DATE);
        
        //Select
        $liaisonBooks = LiaisonBookPeer::doSelect($criterias);

        return $liaisonBooks;
    }
    
    public function findByGroupIdAndLessOneYear($group_id)
    {
        $date = date(mktime(0, 0, 0, date('n'), 1, date('y')-1));
        
        //Criteria dates and group_id
        $criterias = new Criteria();  
        $criterias->add(LiaisonBookPeer::GROUP_ID, $group_id, Criteria::EQUAL);
        $criterias->add(LiaisonBookPeer::DATE, $date, Criteria::GREATER_EQUAL);
        $criterias->addDescendingOrderByColumn(LiaisonBookPeer::DATE);
        
        //Select
        $liaisonBooks = LiaisonBookPeer::doSelect($criterias);

        return $liaisonBooks;
    }

} // LiaisonBookQuery

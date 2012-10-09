<?php

namespace BNS\App\MessagingBundle\Model;

use BNS\App\MessagingBundle\Model\om\BaseModeratedMessageQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'moderated_message' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.MessagingBundle.Model
 */
class ModeratedMessageQuery extends BaseModeratedMessageQuery {

    public function findByUsersIds($userIds, $limit = 100, $offset = 0)
    {
        $c = new \Criteria();
        $c->setLimit($limit);
        $c->setOffset($offset);
        $crit0 = $c->getNewCriterion(ModeratedMessagePeer::USER_ID, $userIds, \Criteria::IN);

        $c->add($crit0);
        $result = ModeratedMessagePeer::doSelect($c);

        return $result;
    }
    
    public function countByUsersIds($userIds)
    {
        $c = new \Criteria();
        $crit0 = $c->getNewCriterion(ModeratedMessagePeer::USER_ID, $userIds, \Criteria::IN);

        $c->add($crit0);
        $count = ModeratedMessagePeer::doCount($c);

        return $count;
    }
    
    
    
} // ModeratedMessageQuery

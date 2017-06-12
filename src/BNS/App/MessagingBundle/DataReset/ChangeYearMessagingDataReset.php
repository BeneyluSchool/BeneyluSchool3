<?php

namespace BNS\App\MessagingBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\User\DataResetUserInterface;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearMessagingDataReset implements DataResetUserInterface
{
    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_messaging';
    }

    /**
     * @param array $usersId
     */
    public function reset($usersId)
    {
        MessagingMessageQuery::create()
            ->filterByAuthorId($usersId)
            //On garde les messages sur les 10 derniers jours
            ->filterByCreatedAt(date('Y-m-d H:i:s',time() - 10 * 24 * 3600),\Criteria::LESS_THAN)
        ->delete();
    }
}
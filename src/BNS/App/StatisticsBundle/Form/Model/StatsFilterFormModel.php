<?php

namespace BNS\App\StatisticsBundle\Form\Model;

use BNS\App\CoreBundle\Model\GroupTypeQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class StatsFilterFormModel
{
    /**
     * @var int
     */
    public $group_type;

    /**
     * @var \DateTime
     */
    public $date_start;

    /**
     * @var \DateTime
     */
    public $date_end;

    /**
     * @var string
     */
    public $marker;

    /**
     * @var boolean
     */
    public $aggregation;

    /**
     * @var string
     */
    public $period;

    /**
     * @var string
     */
    public $title;

    /**
     * @var boolean
     */
    public $graph_pro;


    /**
     * @param \DateTime $dateStart
     * @param \DateTime $dateEnd
     */
    public function __construct($dateStart, $dateEnd)
    {
        $this->date_start = $dateStart;
        $this->date_end	  = $dateEnd;
    }

    /**
     * @return \DateTime
     */
    public function getDateStart()
    {
        return $this->date_start;
    }

    /**
     * @return \DateTime
     */
    public function getDateEnd()
    {
        return $this->date_end;
    }

    /**
     * @return string
     */
    public function getMarker()
    {
        return $this->marker;
    }

    /**
     * @return boolean
     */
    public function getAggregation()
    {
        return $this->aggregation;
    }

    /**
     * @return string
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getGroupType()
    {
        return $this->group_type;
    }


    /**
     * @return boolean
     */
    public function getGraphPro()
    {
        return $this->graph_pro;
    }

    /**
     * @param BNSGroupManager $groupManager
     * 
     * @return array<Integer>
     */
    public function getUserIds($groupManager)
    {
        if (null == $this->group_type) {
            $userIds = $groupManager->getUsersIds();
        }
        else {
            $users = $groupManager->getUsersByRoleUniqueName($this->group_type);
            $userIds = array();

            foreach ($users as $user) {
                $userIds[] = $user['id'];
            }
        }

        return $userIds;
    }
}
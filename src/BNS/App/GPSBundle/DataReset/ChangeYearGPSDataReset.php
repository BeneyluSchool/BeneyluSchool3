<?php

namespace BNS\App\GPSBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\GPSBundle\Form\Type\ChangeYearGPSDataResetType;
use BNS\App\GPSBundle\Model\GpsCategoryQuery;
use BNS\App\GPSBundle\Model\GpsPlaceQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearGPSDataReset extends AbstractDataReset
{
    /**
     * @var string 
     */
    public $choice;

    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_gps';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        if ('KEEP' == $this->choice) {
            return;
        }

        // DELETE
        $categoriesId = GpsCategoryQuery::create('gc')
            ->select('gc.Id')
        ->findByGroupId($group->getId());

        GpsPlaceQuery::create('gp')
            ->where('gp.GpsCategoryId IN ?', $categoriesId)
        ->delete();

        GpsCategoryQuery::create('gc')
            ->where('gc.Id IN ?', $categoriesId)
        ->delete();
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppGPSBundle:DataReset:change_year_gps.html.twig';
    }

    /**
     * @return ChangeYearGPSDataResetType
     */
    public function getFormType()
    {
        return new ChangeYearGPSDataResetType();
    }

    /**
     * @return array<String, String> 
     */
    public static function getChoices()
    {
        return array(
            'KEEP'     => 'CHOICE_KEEP_PLACE',
            'DELETE'   => 'CHOICE_DELETE_PLACE'
        );
    }
}
<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileFeedQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'profile_feed' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileFeedQuery extends BaseProfileFeedQuery
{

    public function filterByNewYearStatus()
    {
        return $this
            ->filterByPublishable(true)
            ->addAscendingOrderByColumn('RAND()')
            ->useProfileQuery()
                ->useUserQuery()
                ->endUse()
            ->endUse()
            ->useProfileFeedStatusQuery()
                ->filterByContent('%#' . date('Y') . '%')
                ->where('LENGTH(content) < 160')
            ->endUse()
            ;
    }

    public function filterByNewYearStatusAdmin()
    {
        return $this
            ->filterByStatus('VALIDATED')
            ->filterByPublishable(null)
            ->useProfileQuery()
            ->useUserQuery()
            ->endUse()
            ->endUse()
            ->useProfileFeedStatusQuery()
                ->filterByContent('%#' . date('Y') . '%')
                ->where('LENGTH(content) < 160')
            ->endUse()
            ;
    }
	
}
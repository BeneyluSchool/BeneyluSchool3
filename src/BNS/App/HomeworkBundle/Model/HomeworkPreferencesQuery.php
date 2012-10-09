<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkPreferencesQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'homework_preferences' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkPreferencesQuery extends BaseHomeworkPreferencesQuery
{

    public function findOrInit($group_id)
    {
        $prefs = $this->findOneByGroupId($group_id);

        if (!$prefs) {
            $prefs = new HomeworkPreferences();
            $prefs->setGroupId($group_id);
            $prefs->setShowTasksDone(true);
            $prefs->setActivateValidation(true);
            $prefs->setDays(array('MO','TU','WE','TH','FR'));
            $prefs->save();
        }

        return $prefs;
    }
}

// HomeworkPreferencesQuery

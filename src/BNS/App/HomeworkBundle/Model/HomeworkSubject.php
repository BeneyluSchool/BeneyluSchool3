<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkSubject;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;

/**
 * Skeleton subclass for representing a row from the 'homework_subject' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkSubject extends BaseHomeworkSubject
{

    public static function fetchRoot($group_id, \PropelPDO $con = null)
    {
        $root = HomeworkSubjectQuery::create()->findRoot($group_id, $con);

        if ($root == null) {
            $root = new HomeworkSubject();
            $root->setGroupId($group_id);
            $root->setName("subjects for group " . $group_id);
            $root->makeRoot();
            $root->save($con);
        }

        return $root;
    }

}

// HomeworkSubject

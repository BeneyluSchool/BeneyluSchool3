<?php

namespace BNS\App\CompetitionBundle\Model;



/**
 * Skeleton subclass for representing a row from one of the subclasses of the 'competition' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CompetitionBundle.Model
 */
class PedagogicCourse extends Competition {

    /**
     * Constructs a new PedagogicCourse class, setting the class_key column to CompetitionPeer::CLASSKEY_4.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setClassKey(CompetitionPeer::CLASSKEY_4);
    }

} // PedagogicCourse

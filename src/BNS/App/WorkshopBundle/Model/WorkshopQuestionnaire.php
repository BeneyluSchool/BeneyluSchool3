<?php

namespace BNS\App\WorkshopBundle\Model;



/**
 * Skeleton subclass for representing a row from one of the subclasses of the 'workshop_document' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.WorkshopBundle.Model
 */
class WorkshopQuestionnaire extends WorkshopDocument {

    /**
     * Constructs a new WorkshopQuestionnaire class, setting the document_type column to WorkshopDocumentPeer::CLASSKEY_2.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setDocumentType(WorkshopDocumentPeer::CLASSKEY_2);
    }

    public function getType()
    {
        return 'QUESTIONNAIRE';
    }

} // WorkshopQuestionnaire

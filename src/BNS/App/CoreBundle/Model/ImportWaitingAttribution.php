<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseImportWaitingAttribution;
use BNS\App\CoreBundle\Model\GroupQuery;

class ImportWaitingAttribution extends BaseImportWaitingAttribution
{

    public function getChild()
    {
        switch($this->getChildIdentifierType())
        {
            case 'AAF_ID':
                return GroupQuery::create()->findOneByAafId($this->getChildIdentifier());
                break;
        }
    }

    public function getParent()
    {
        switch($this->getParentIdentifierType())
        {
            case 'AAF_ID':
                return GroupQuery::create()->findOneByAafId($this->getParentIdentifier());
                break;
            case 'INSEE_ID':
                return GroupQuery::create()->filterBySingleAttribute($this->getParentIdentifierType(),$this->getParentIdentifier())->findOne();
                break;
        }
    }



}

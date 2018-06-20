<?php

namespace BNS\App\CorrectionBundle\Model;

use BNS\App\CorrectionBundle\Model\om\BaseCorrectionQuery;

class CorrectionQuery extends BaseCorrectionQuery
{
    public function filterByObject($object)
    {
        if ($object instanceof \BaseObject) {
            $this->filterByObjectId($object->getPrimaryKey());
            $this->filterByObjectClass(get_class($object));
        }

        return $this;
    }
}

<?php

namespace BNS\App\CorrectionBundle\Model;

use BNS\App\CoreBundle\RichText\RichTextParser;
use BNS\App\CorrectionBundle\Model\om\BaseCorrection;
use PropelPDO;

class Correction extends BaseCorrection
{
    use RichTextParser;

    public function hasData()
    {
        return trim($this->getComment()) || $this->countCorrectionAnnotations() > 0;
    }

    public function setObject($object)
    {
        $this->setObjectId($object->getPrimaryKey());
        $this->setObjectClass(get_class($object));
    }

    public function preDelete(PropelPDO $con = null)
    {
        // Do it manually to be sure that postDelete is called
        $this->getCorrectionAnnotations(new \Criteria(), $con)->delete($con);

        return true;
    }

    public function getRichLastCorrection()
    {
        return $this->parse($this->getLastCorrection());
    }
}

<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\LsuBundle\Model\om\BaseLsu;

class Lsu extends BaseLsu
{

    public function getParCit()
    {
        return $this->getDataValue('PAR_CIT');
    }

    public function getParArt()
    {
        return $this->getDataValue('PAR_ART');
    }

    public function getParSan()
    {
        return $this->getDataValue('PAR_SAN');
    }

    public function getDataValue($key)
    {
        $data = $this->getData();

        return isset($data[$key]) ? $data[$key] : null;
    }

}

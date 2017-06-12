<?php

namespace BNS\App\PortalBundle\Model;

use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\PortalBundle\Model\om\BasePortalWidget;

class PortalWidget extends BasePortalWidget
{

    public function isEnabled()
    {
        return $this->getEnabled();
    }


    public function getData($data)
    {
        $datas = $this->getDatas();
        if($datas == null)
        {
            $datas = serialize(array());
        }

        $datas = unserialize($datas);

        if(isset($datas[$data]))
        {
            return $datas[$data];
        }
        return null;
    }

    public function getMedia($key)
    {
        if($this->getData($key) != null)
        {
            return MediaQuery::create()->findOneById($this->getData($key));
        }
        return null;
    }
}

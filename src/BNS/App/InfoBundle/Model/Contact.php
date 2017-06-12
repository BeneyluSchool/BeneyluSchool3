<?php

namespace BNS\App\InfoBundle\Model;

use BNS\App\InfoBundle\Model\om\BaseContact;

class Contact extends BaseContact
{
    public function done()
    {
        $this->setDone(true);
        $this->save();
    }

}

<?php

namespace BNS\App\CoreBundle\IpLocalized;

use BNS\App\CoreBundle\Model\Ip2countryQuery;

/**
 * @author Eymeric Taelman
 */
class IpLocalized
{

    public function __construct()
    {

    }


    public function getCountryFromIp($ip)
    {
        $iparray = explode('.', $ip);
        $ipdecimal = ((int) $iparray[0] * 16777216) + ((int) $iparray[1] * 65536) + ((int) $iparray[2] * 256) + ((int) $iparray[3]);

        $ip2Country = Ip2countryQuery::create()
            ->filterByMin($ipdecimal, \Criteria::LESS_THAN)
            ->filterByMax($ipdecimal, \Criteria::GREATER_THAN)
            ->findOne();

        if($ip2Country)
        {
            return $ip2Country->getCode();
        }else{
            return 'FR';
        }
    }

}
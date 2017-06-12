<?php

namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Utils\StringUtil;
use Twig_Extension;

class UtilsExtension extends Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('utm_campaign_string', array($this, 'utmCampaignString')),
        );
    }

    public function utmCampaignString($string)
    {
        $string = str_replace(' ','+',$string);
        return strtolower(StringUtil::stripAccents($string));
    }

    public function getName()
    {
        return 'utils_extension';
    }
}

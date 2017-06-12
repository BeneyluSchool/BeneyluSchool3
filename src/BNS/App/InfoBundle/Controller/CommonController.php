<?php

namespace BNS\App\InfoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class CommonController extends Controller
{
    protected static $utm_source = 'ent';
    protected static $utm_medium = 'module-information';
}

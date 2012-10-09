<?php
namespace BNS\App\CoreBundle\Tests\Access;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

class BNSAccessTest extends TestCase
{

    public function testSetRequest()
    {
        $request = new Request();
        BNSAccess::setRequest($request);

        $this->assertEquals($request, BNSAccess::getRequest());
    }
}
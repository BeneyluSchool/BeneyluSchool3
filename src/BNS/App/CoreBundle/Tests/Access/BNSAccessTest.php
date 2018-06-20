<?php
namespace BNS\App\CoreBundle\Tests\Access;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\DependencyInjection\Container;

class BNSAccessTest extends \PHPUnit_Framework_TestCase
{

    public function testSetContainer()
    {
        $container = new Container();
        BNSAccess::setContainer($container);

        $this->assertEquals($container, BNSAccess::getContainer());
    }
}

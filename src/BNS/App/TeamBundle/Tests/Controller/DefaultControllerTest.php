<?php

namespace BNS\App\TeamBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient(array('environment' => 'app_test'));

        //TODO write Tests
    }
}

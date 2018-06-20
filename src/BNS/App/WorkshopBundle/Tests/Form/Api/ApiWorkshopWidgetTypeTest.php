<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetType;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopWidgetTypeTest extends AppWebTestCase
{
    public function testBuildForm()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $mock = $this->getMockBuilder('BNS\App\MediaLibraryBundle\Manager\MediaLibraryRightManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $object = new WorkshopWidget();
        $formData = [
            'content' => 'foo bar',
            'settings' => '1',
            'media_id' => 3,
        ];
        $form = $container->get('form.factory')->create(new ApiWorkshopWidgetType($mock), $object);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($object, $form->getData());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }
}

<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\CoreBundle\Test\Form\Type\TypeTestCaseWithCore;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetGroupType;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopWidgetGroupTypeTest extends TypeTestCaseWithCore
{
    public function testBuildForm()
    {
        $object = new WorkshopWidgetGroup();
        $formData = [
            'id' => 123,
            'zone' => '1',
            'position' => 3,
            'workshop_widgets' => []
        ];
        $form = $this->factory->create(new ApiWorkshopWidgetGroupType(), $object);
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

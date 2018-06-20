<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\CoreBundle\Test\Form\Type\TypeTestCaseWithCore;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetConfigurationType;
use BNS\App\WorkshopBundle\Manager\WidgetConfigurationManager;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopWidgetConfigurationTypeTest extends TypeTestCaseWithCore
{
    public function testBuildForm()
    {
        $data = [];
        $formData = [
            'zone' => '1',
            'position' => 3,
            'code' => 'code',
        ];
        $form = $this->factory->create(new ApiWorkshopWidgetConfigurationType(new WidgetConfigurationManager('Resources/config/widget-configurations.yml')), $data);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($formData, $form->getData());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }
}

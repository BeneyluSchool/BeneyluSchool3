<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\CoreBundle\Test\Form\Type\TypeTestCaseWithCore;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopPageType;
use BNS\App\WorkshopBundle\Manager\LayoutManager;
use BNS\App\WorkshopBundle\Model\WorkshopPage;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopPageTypeTest extends TypeTestCaseWithCore
{
    public function testBuildForm()
    {
        $object = new WorkshopPage();
        $formData = [
            'layout_code' => 'test',
            'position' => 3,
            'orientation' => WorkshopPage::ORIENTATION_PORTRAIT,
            'sizes' => 'size'
        ];
        $form = $this->factory->create(new ApiWorkshopPageType(new LayoutManager('Resources/config/layouts.yml')), $object);
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

<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\CoreBundle\Test\Form\Type\TypeTestCaseWithCore;
use BNS\App\MediaLibraryBundle\Form\Api\ApiMediaType;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopDocumentType;
use BNS\App\WorkshopBundle\Manager\ThemeManager;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use Symfony\Component\Form\PreloadedExtension;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopDocumentTypeTest extends TypeTestCaseWithCore
{
    public function testBuildForm()
    {
        $object = new WorkshopDocument();
        $formData = [
            'theme_code' => 'test',
            'user_ids' => [1,2,3],
            'group_ids' => [4,5,6],
            'is_locked' => true
        ];
        $form = $this->factory->create('workshop_document', $object);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($object, $form->getData());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }

    protected function getExtensions()
    {
        // create a type instance with the mocked dependencies
        $mediaType = new ApiMediaType();
        $documentType = new ApiWorkshopDocumentType(new ThemeManager('Resources/config/themes.yml'));

        return array(
            // register the type instances with the PreloadedExtension
            new PreloadedExtension([
                $mediaType->getName() => $mediaType,
                $documentType->getName() => $documentType
            ], []),
        );
    }
}

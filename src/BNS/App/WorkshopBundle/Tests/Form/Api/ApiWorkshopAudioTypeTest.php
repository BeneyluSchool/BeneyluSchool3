<?php

namespace BNS\App\WorkshopBundle\Tests\Form\Api;

use BNS\App\MediaLibraryBundle\Form\Api\ApiMediaType;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopAudioType;
use BNS\App\WorkshopBundle\Model\WorkshopAudio;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiWorkshopAudioTypeTest extends TypeTestCase
{
    public function testBuildForm()
    {
        $object = new WorkshopAudio();
        $formData = [
            'user_ids' => [1,2,3],
            'group_ids' => [4,5,6],
        ];
        $form = $this->factory->create('workshop_audio', $object);
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
        $audioType = new ApiWorkshopAudioType();

        return array(
            // register the type instances with the PreloadedExtension
            new PreloadedExtension([
                $mediaType->getName() => $mediaType,
                $audioType->getName() => $audioType
            ], []),
        );
    }
}

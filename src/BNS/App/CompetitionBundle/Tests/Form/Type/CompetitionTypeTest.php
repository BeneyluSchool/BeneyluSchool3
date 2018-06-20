<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/03/2017
 * Time: 11:04
 */

namespace BNS\App\CompetitionBundle\Tests\Form\Type;

use BNS\App\CompetitionBundle\Form\Type\CompetitionType;
use BNS\App\CompetitionBundle\Model\Competition;
use Symfony\Component\Form\Test\TypeTestCase;

class CompetitionTypeTest extends TypeTestCase
{

    public function testBuildForm()
    {
        $object = new Competition();
        $formData = [
            ['title' => 'Titre Test', 'description' => 'Description Test', 'status' => 'DRAFT'],
            ['title' => 'Titre Test2', 'description' => 'Description Test', 'status' => 'PUBLISHED']
        ];

        for ($i = 0; $i < 2; $i++) {
            $type = new CompetitionType();
            $form = $this->factory->create($type, $object);
            $form->submit($formData[$i]);
            $this->assertTrue($form->isSynchronized());
            $this->assertEquals($object, $form->getData());

            $view = $form->createView();
            $children = $view->children;

            foreach (array_keys($formData[$i]) as $key) {
                $this->assertArrayHasKey($key, $children);
            }
        }
    }

}

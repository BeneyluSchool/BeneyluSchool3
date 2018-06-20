<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 09/05/2017
 * Time: 18:21
 */

namespace BNS\App\CompetitionBundle\Tests\Form\Type;


use BNS\App\CompetitionBundle\Form\Type\AnswerType;
use BNS\App\CompetitionBundle\Model\Answer;
use Symfony\Component\Form\Test\TypeTestCase;

class AnswerTypeTest extends TypeTestCase
{
    public function testBuildForm(){
        $object= new Answer();
        $formData=[
            'workshop_widget_id' => 1,
            'answer' => [1]
        ];
        $type= new AnswerType();
        $form = $this->factory->create($type,$object);
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

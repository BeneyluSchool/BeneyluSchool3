<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/03/2017
 * Time: 11:47
 */

namespace BNS\App\CompetitionBundle\Tests\Form\Type;

use BNS\App\CompetitionBundle\Form\Type\BookType;
use BNS\App\CompetitionBundle\Model\Book;
use Symfony\Component\Form\Test\TypeTestCase;

class BookTypeTest extends TypeTestCase
{
    public function testBuildForm(){
        $object= new Book();
        $formData=[
          'title'=>'Guerre et paix',
            'author'=>'Leo Tolstoi',
        ];
        $type= new BookType();
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

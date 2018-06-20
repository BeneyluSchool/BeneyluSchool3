<?php

namespace BNS\App\CorrectionBundle\Tests\Form\Type;

use BNS\App\CoreBundle\Form\Extension\PurifyTextareaExtension;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CorrectionBundle\Form\Type\CorrectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionTypeTest extends AppWebTestCase
{
    public function testGetName()
    {
        $correctionType = new CorrectionType();

        $this->assertEquals('correction_type', $correctionType->getName());
    }

    public function testConfigureOptions()
    {
        $correctionType = new CorrectionType();

        $resolver = new OptionsResolver();

        $this->assertArrayNotHasKey('data_class', $resolver->getDefinedOptions());
        $correctionType->configureOptions($resolver);

        $this->assertTrue($resolver->hasDefault('data_class'));
    }

    public function testBuildView()
    {
        $correctionType = new CorrectionType();

        $view = new FormView();

        $this->assertFalse($view->isRendered());

        $formMock = $this->getMockBuilder('Symfony\Component\Form\FormInterface')->getMock();

        $correctionType->buildView($view, $formMock, []);

        $this->assertTrue($view->isRendered());
    }

    public function testBuildForm()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $factory = $container->get('form.factory');

        $builder = $factory->createBuilder('correction_type');

        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $builder->get('comment'));
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $builder->get('correctionAnnotations'));
    }
}


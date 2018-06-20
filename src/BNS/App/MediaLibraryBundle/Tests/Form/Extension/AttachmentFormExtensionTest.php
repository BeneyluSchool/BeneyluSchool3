<?php

namespace BNS\App\MediaLibraryBundle\Tests\Form\Extension;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CorrectionBundle\Model\CorrectionAnnotation;
use BNS\App\MediaLibraryBundle\Form\Extension\AttachmentFormExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AttachmentFormExtensionTest extends AppWebTestCase
{
    public function testGetName()
    {
        $attachmentFormExtension = new AttachmentFormExtension();

        $this->assertEquals('media_attachments', $attachmentFormExtension->getName());
    }

    public function testGetParent()
    {
        $attachmentFormExtension = new AttachmentFormExtension();

        $this->assertEquals('collection', $attachmentFormExtension->getParent());
    }

    /**
     * @dataProvider getDefaultOptions
     */
    public function testConfigureOptions($key, $defaultValue)
    {
        $attachmentFormExtension = new AttachmentFormExtension();

        $resolver = new OptionsResolver();


        $this->assertArrayNotHasKey($key, $resolver->getDefinedOptions());
        $attachmentFormExtension->configureOptions($resolver);

        $this->assertTrue($resolver->hasDefault($key));
        $options = $resolver->resolve([]);

        $this->assertEquals($defaultValue, $options[$key]);
    }

    public function testBuildForm()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $factory = $container->get('form.factory');

        $builder = $factory->createBuilder('media_attachments');

        $transformers = $builder->getModelTransformers();
        $this->assertCount(1, $transformers);
        $this->assertInstanceOf('Symfony\Bridge\Propel1\Form\DataTransformer\CollectionToArrayTransformer', $transformers[0]);
    }

    public function testBuildViewNoParent()
    {
        $attachmentFormExtension = new AttachmentFormExtension();

        $view = new FormView();

        $this->assertArrayNotHasKey('objectId', $view->vars);
        $this->assertArrayNotHasKey('objectClass', $view->vars);
        $this->assertArrayNotHasKey('medias', $view->vars);

        $formMock = $this->getMockBuilder('Symfony\Component\Form\FormInterface')->getMock();

        $attachmentFormExtension->buildView($view, $formMock, []);

        $this->assertArrayNotHasKey('objectId', $view->vars);
        $this->assertArrayNotHasKey('objectClass', $view->vars);
        $this->assertArrayNotHasKey('medias', $view->vars);
    }

    public function testBuildViewBlogArticle()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $factory = $container->get('form.factory');

        $object = new CorrectionAnnotation();
        $object->setId(9999);

        $builder = $factory->createBuilder('form', $object)->add('attachments', 'media_attachments');

        $form = $builder->getForm();
        $view = $form->createView();
        $childView = $view->children['attachments'];

        $this->assertArrayHasKey('objectId', $childView->vars);
        $this->assertArrayHasKey('objectClass', $childView->vars);
        $this->assertArrayHasKey('medias', $childView->vars);

        $this->assertEquals(9999, $childView->vars['objectId']);
        $this->assertEquals('CorrectionAnnotation', $childView->vars['objectClass']);
        $this->assertCount(0, $childView->vars['medias']);
    }

    public function getDefaultOptions()
    {
        return [
            ['type', 'media_id_type'],
            ['allow_add', true],
            ['allow_remove', true],
            ['label', false],
            ['by_reference', false],
            ['delete_empty', true],
        ];
    }
}

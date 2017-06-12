<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\ResourceBundle\Form\Api\ApiResourceType;
use BNS\App\WorkshopBundle\Manager\ThemeManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints\Choice;

class ApiWorkshopDocumentType extends AbstractType
{

    /**
     * @var ThemeManager
     */
    private $themeManager;

    public function __construct(ThemeManager $themeManager)
    {
        $this->themeManager = $themeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('theme_code', 'text', array(
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->themeManager->getValidThemeCodes(),
                )),
            ),
        ));

        $builder->add('media', 'api_media');

        $builder->add('user_ids', 'text', array(
            'mapped' => false,
        ));
        $builder->add('group_ids', 'text', array(
            'mapped' => false,
        ));
        $builder->add('is_locked', 'checkbox', array(
            'mapped' => false,
        ));
        $builder->add('attempts_number', 'number', array(
            'mapped' => false,
        ));

        // enable __call, for delegate behavior
        $builder->setDataMapper(new PropertyPathMapper(
            PropertyAccess::createPropertyAccessorBuilder()
                ->enableMagicCall()
                ->getPropertyAccessor()
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopDocument'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'workshop_document';
    }
}

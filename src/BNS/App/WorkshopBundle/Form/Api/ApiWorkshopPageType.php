<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\WorkshopBundle\Manager\LayoutManager;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Class ApiWorkshopPageType
 *
 * @package BNS\App\WorkshopBundle\Form\Api
 */
class ApiWorkshopPageType extends AbstractType
{

    /**
     * @var LayoutManager
     */
    private $layoutManager;

    public function __construct(LayoutManager $layoutManager)
    {
        $this->layoutManager = $layoutManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('layout_code', 'text', array(
            'constraints' => array(
                new Choice(array(
                    'choices' => $this->layoutManager->getValidLayoutCodes(),
                )),
            ),
        ));

        // expose position, but do not map it: it needs custom handling to update other pages in collection
        $builder->add('position', 'number', array(
            'mapped' => false,
        ));

        $builder->add('orientation', 'choice', array(
            'choices' => array(
                WorkshopPage::ORIENTATION_PORTRAIT,
                WorkshopPage::ORIENTATION_LANDSCAPE,
            ),
        ));

        $builder->add('workshop_widget_groups', 'collection', array(
            'type' => new ApiWorkshopWidgetGroupType(),
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopPage',
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'workshop_page';
    }

}

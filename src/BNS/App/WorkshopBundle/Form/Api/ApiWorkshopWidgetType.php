<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryRightManager;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetExtendedSettingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * Class ApiWorkshopWidgetType
 *
 * @package BNS\App\WorkshopBundle\Form\Api
 */
class ApiWorkshopWidgetType extends AbstractType
{

    /**
     * @var MediaLibraryRightManager
     */
    private $mediaLibraryRightManager;

    public function __construct(MediaLibraryRightManager $mediaLibraryRightManager)
    {
        $this->mediaLibraryRightManager = $mediaLibraryRightManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $mediaLibraryRightManager = $this->mediaLibraryRightManager;

        /**
         * Callback to validate embedded Resource
         *
         * @param $id
         * @param ExecutionContextInterface $context
         */
        $resourceValidatorCallback = function ($id, $context) use ($mediaLibraryRightManager) {
            if (!$id) {
                return;
            }

            // check that resource exists
            $media = MediaQuery::create()->findPk($id);
            if (!$media) {
                $context->addViolation("La ressource n'a pas été trouvée");
                return;
            }
            // check that user can embed it in its document
            if (!$mediaLibraryRightManager->canReadMedia($media)) {
                $context->addViolation("Cette ressource n'est pas accessible");
            }
        };

        $builder->add('content', 'textarea');
        $builder->add('settings');
        $builder->add('media_id', null, array(
            'constraints' => array(
                new Callback(array($resourceValidatorCallback)),
            ),
        ));
        $builder->add('workshop_widget_extended_setting', new ApiWorkshopWidgetExtendedSettingType());
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopWidget',
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'workshop_widget';
    }

}

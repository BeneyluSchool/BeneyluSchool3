<?php

namespace BNS\App\WorkshopBundle\Form\Api;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryRightManager;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContext;

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
        $builder->add('content', 'textarea', [
            'correction_edit' => true,
        ]);
        $builder->add('settings');
        $builder->add('media_id');
        $builder->add('workshop_widget_extended_setting', new ApiWorkshopWidgetExtendedSettingType());
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $mediaLibraryRightManager = $this->mediaLibraryRightManager;

        /**
         * Callback to validate embedded Resource
         *
         * @param WorkshopWidget $widget
         * @param ExecutionContext $context
         */
        $resourceValidatorCallback = function ($widget, $context) use ($mediaLibraryRightManager) {
            if (!($widget && $widget->getMediaId())) {
                return;
            }

            if ($widget->getId()) {
                $oldMediaId = WorkshopWidgetQuery::create()
                    ->filterById($widget->getId())
                    ->select('MediaId')
                    ->findOne();
                if ($widget->getMediaId() == $oldMediaId) {
                    return;
                }
            }

            $media = MediaQuery::create()->findPk($widget->getMediaId());
            if (!$media) {
                $context->buildViolation("La ressource n'a pas été trouvée")
                    ->atPath('media_id')
                    ->addViolation();
                return;
            }
            // check that user can embed it in its document
            if (!$mediaLibraryRightManager->canReadMedia($media)) {
                $context->buildViolation("Cette ressource n'est pas accessible")
                    ->atPath('media_id')
                    ->addViolation()
                ;
                return;
            }
        };

        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopWidget',
            'constraints' => array(
                new Callback(array($resourceValidatorCallback)),
            ),
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

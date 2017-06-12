<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ClassroomLevelType
 *
 * @package BNS\App\ClassroomBundle\Form\Type
 */
class ClassroomLevelType extends AbstractType
{

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('locale', null);
        $resolver->setDefault('choices', function (Options $options) {
            return $this->getChoicesByLocale($options['locale']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'classroom_level';
    }
    
    protected function getChoicesByLocale($locale) {
        $choices = [];
        $levelGroupTypeDataTemplate = GroupTypeDataTemplateQuery::create()
            ->filterByUniqueName('LEVEL')
            ->findOne();

        switch ($locale) {
            case 'fr':
                $min = 4;
                $max = 8;
                break;
            case 'es':
                $min = 4;
                $max = 9;
                break;
            case 'en_GB':
                $min = 3;
                $max = 8;
                break;
            case 'en_US':
            case 'en':
            default:
                $min = 4;
                $max = 8;
                break;
        }

        $i = 0;
        foreach ($levelGroupTypeDataTemplate->getGroupTypeDataChoices() as $choice) {
            $i++;
            if (($i < $min || $i > $max) && 'OTHER' !== $choice->getValue()) {
                continue;
            }
            $choices[$choice->getValue()] = $choice->getLabel();
        }

        return $choices;
    }

}

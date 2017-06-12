<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupTypeDataChoice;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use JMS\TranslationBundle\Annotation\Ignore;


/**
 * Skeleton subclass for representing a row from the 'group_type_data_choice' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupTypeDataChoice extends BaseGroupTypeDataChoice
{
    use TranslatorTrait;

    public function getLabel()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getLabelToken();
        }

        /** @Ignore */
        return $this->getTranslator()->trans(
            $this->getLabelToken(),
            array(),
            'GROUP_TYPE'
        );
    }

    public function getLabelToken()
    {
        return 'LABEL_DATA_CHOICE_' . $this->getGroupTypeDataTemplateUniqueName() . '_' . $this->getValue();
    }

    public function setLabel($v)
    {
        return $this;
    }

} // GroupTypeDataChoice

<?php

namespace BNS\App\ClassroomBundle\Model;

use BNS\App\ClassroomBundle\Model\om\BaseClassroomPush;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Translation\TranslatorTrait;

class ClassroomPush extends BaseClassroomPush
{
    use TranslatorTrait;

    /**
     * @var Module
     */
    protected $application;

    public function getFinalTitle()
    {
        if ($this->getTitle()) {
            return $this->getTitle();
        }
        if ($this->getModuleUniqueName()) {
            /** @Ignore */return $this->getTranslator()->trans($this->getModuleUniqueName(), [], 'MODULE');
        }

        return '';
    }

    /**
     * Gets the related application.
     * Use with caution: the app is not fully decorated, and group-dependant values (is open, etc...) are missing.
     *
     * @return Module
     */
    public function getApplication()
    {
        if (!$this->application && $this->getModuleUniqueName()) {
            $this->application = ModuleQuery::create()->findOneByUniqueName($this->getModuleUniqueName());
        }

        return $this->application;
    }

}

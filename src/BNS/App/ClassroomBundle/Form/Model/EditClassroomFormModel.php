<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use JMS\TranslationBundle\Annotation\Ignore;
use Symfony\Component\Translation\TranslatorInterface;

class EditClassroomFormModel
{
    public $classroom;

    public $avatarId;

    public $name;

    public $level;

    public $description;

    /** @var  BNSGroupManager */
    public $groupManager;

    public $lang;

    public $timezone;

    public $country;

    private $translator;

    public function __construct(Group $classroom, $groupManager, TranslatorInterface $translatorInterface)
    {
        $this->classroom = $classroom;

        $this->avatarId = $this->classroom->getAttribute('AVATAR_ID');
        $this->name = $this->classroom->getLabel();
        $this->level = $this->classroom->getAttribute('LEVEL');
        $this->description = $this->classroom->getAttribute('DESCRIPTION');
        $this->home_message = $this->classroom->getAttribute('HOME_MESSAGE');
        $this->country = $this->classroom->getCountry();

        $this->groupManager = $groupManager;
        $this->translator = $translatorInterface;
    }

    public function save($withCountry = false)
    {
        $this->classroom->setLabel($this->name);
        $this->classroom->setAttribute('AVATAR_ID', $this->avatarId);
        $this->classroom->setAttribute('LEVEL', $this->level);
        $this->classroom->setAttribute('DESCRIPTION', $this->description);
        $this->classroom->setAttribute('HOME_MESSAGE', /** @Ignore */ $this->translator->trans($this->home_message, array(), 'CLASSROOM'));
        if ($withCountry) {
            $this->classroom->setCountry($this->country);
        }
        // Finally
        $this->classroom->save();

        //Update côté centrale + Agenda + Médiathèque
        $params = array('label' => $this->name);
        $this->groupManager->setGroup($this->classroom);
        $this->groupManager->updateGroup($params);
    }
}

<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Access\BNSAccess;

class EditTeamFormModel
{

    public $name;
    public $team;
    public $description;
    public $expirationDate;
    public $welcomeMessage;

    public function __construct(Group $team)
    {
        $this->team = $team;
        $this->name = $this->team->getLabel();
        $this->description = $this->team->getAttribute('DESCRIPTION');
        $this->expirationDate = $this->team->getAttribute('EXPIRATION_DATE') ? \DateTime::createFromFormat('Y-m-d', $this->team->getAttribute('EXPIRATION_DATE')) : null;
        $this->welcomeMessage = $this->team->getAttribute('HOME_MESSAGE');
    }

    public function save()
    {
        $params = array();
        $params['label'] = $this->name;
        $params['validated'] = $this->team->getValidationStatus();

        //update des attributes du partenariat
        $this->team->setAttribute('DESCRIPTION', $this->description);
        $this->team->setAttribute('EXPIRATION_DATE', $this->expirationDate ? $this->expirationDate->format('Y-m-d'): null);
        if (!$this->team->hasAttribute('HOME_MESSAGE')) {
            $this->team->createAttribute('HOME_MESSAGE', $this->welcomeMessage);
        } else {
            $this->team->setAttribute('HOME_MESSAGE', $this->welcomeMessage);
        }
        //update côté centrale
//        $pm = BNSAccess::getContainer()->get('bns.group_manager');
//        $pm->setGroup($this->team);
//        $pm->updateGroup($params);

        //En attendant que le problème du groupe soit résolu
        $pm = BNSAccess::getContainer()->get('bns.partnership_manager');
        $pm->setPartnership($this->team);
        $pm->updatePartnership($params);
    }

}

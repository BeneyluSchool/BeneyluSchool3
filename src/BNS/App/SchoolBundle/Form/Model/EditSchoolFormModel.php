<?php

namespace BNS\App\SchoolBundle\Form\Model;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;

class EditSchoolFormModel
{
    public $homeMessage;
    public $address;
    public $city;
    public $zipcode;
    public $country;

    protected $groupManager;

    public function __construct(Group $school, BNSGroupManager $groupManager)
    {
        $this->groupManager = $groupManager;

        $this->school = $school;
        $this->home_message = $school->getAttribute('HOME_MESSAGE');
        $this->address = $school->getAttribute('ADDRESS');
        $this->city = $school->getAttribute('CITY');
        $this->zipcode = $school->getAttribute('ZIPCODE');
        $this->country = $school->getCountry();
    }

    public function save()
    {
        $this->school->setAttribute('HOME_MESSAGE', $this->home_message);
        $this->school->setAttribute('ADDRESS', $this->address);
        $this->school->setAttribute('CITY', $this->city);
        $this->school->setAttribute('ZIPCODE', $this->zipcode);
        $this->school->setCountry($this->country);
        $updateClassroomCountry = $this->school->isColumnModified(GroupPeer::COUNTRY);
        $this->school->save();

        if ($updateClassroomCountry) {
            $classrooms = $this->groupManager->setGroup($this->school)->getSubgroupsByGroupType('CLASSROOM');
            foreach ($classrooms as $classroom) {
                $classroom->setCountry($this->country);
                $classroom->save();
            }
        }
    }
}

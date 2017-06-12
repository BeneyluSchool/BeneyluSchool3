<?php

namespace BNS\App\InfoBundle\Model;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\InfoBundle\Model\om\BaseSponsorship;

class Sponsorship extends BaseSponsorship
{
    CONST LIMIT_BY_SCHOOL = 2;


    public function isAvailable()
    {
        $email = $this->getEmail();
        $exists = UserQuery::create()->findOneByEmail($email);

        /*if($exists)
        {
            return 'EXISTS';
        }*/

        if(SponsorshipQuery::create()->filterByFromUserId($this->getFromUserId())->findOneByEmail($email))
        {
            return 'SPONSORSHIP_EXISTS';
        }

        return true;
    }

    public function getColor()
    {
        switch($this->getStatus())
        {
            case 'PENDING':
                return 'orange';
                break;
            case 'REGISTERED':
                return 'blue';
                break;
            case 'VALIDATED':
                return 'green';
                break;
            case 'REFUSED':
                return 'blue';
                break;
            case 'WRONG_SCHOOL':
                return 'orange';
                break;
        }
    }

    public function getStatusLabelToken()
    {
        //return 'sponsorship.' . strtolower($this->getStatus());
        switch($this->getStatus())
        {
            case 'PENDING':
                return 'INVITATION_ALREADY_SEND';
                break;
            case 'REGISTERED':
                return 'REFER_FRIEND_REGISTERED_MUST_VALID_CLASS';
                break;
            case 'VALIDATED':
                return 'FLASH_REFERAL_SUCCESS';
                break;
            case 'REFUSED':
                return 'INVITATION_LIMIT_REACH';
                break;
            case 'WRONG_SCHOOL':
                return 'REFER_FRIEND_NOT_REGISTERED_IN_YOUR_SCHOOL';
                break;
        }
    }

    public function register($schoolId, $userTo)
    {
        $this->setStatus('REGISTERED');
        $this->setToUserId($userTo->getId());
        $this->save();
    }

    public function activate()
    {
        if(SponsorshipQuery::create()->filterBySchoolId($this->getSchoolId())->filterByStatus('VALIDATED')->count() < self::LIMIT_BY_SCHOOL)
        {
            $this->setStatus('VALIDATED');
        }else{
            $this->setStatus('REFUSED');
        }
        $this->save();
    }

    public function isRegistered()
    {
        return $this->getStatus() == 'REGISTERED';
    }

    public function isValidated()
    {
        return $this->getStatus() == 'VALIDATED';
    }

}

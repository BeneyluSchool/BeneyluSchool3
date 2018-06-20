<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\LsuBundle\Model\om\BaseLsuConfig;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class LsuConfig extends BaseLsuConfig
{
    protected $users;
    protected $teacherInfo;
    protected $schoolInfo;

    /**
     * @return User[]|\PropelObjectCollection
     */
    public function getUsers()
    {
        if (!$this->users) {
            $this->users = UserQuery::create()->filterById($this->getUserIds())->find();
        }

        return $this->users;
    }

    /**
     * @inheritDoc
     */
    public function setUserIds($v)
    {
        if ($this->user_ids_unserialized !== $v) {
            $this->users = null;
        }

        return parent::setUserIds($v);
    }

    public function setTeacherInfo($teacherInfo)
    {
        $this->teacherInfo = $teacherInfo;
    }

    public function getTeacherInfo()
    {
        return $this->teacherInfo;
    }

    public function setSchoolInfo($schoolInfo)
    {
        $this->schoolInfo = $schoolInfo;
    }

    public function getSchoolInfo()
    {
        return $this->schoolInfo;
    }

    public function validateUniqueLevel(ExecutionContextInterface $context)
    {
        if ($this->getLevelId()) {
            $otherConfigCount = LsuConfigQuery::create()
                ->filterById($this->getId(), \Criteria::NOT_EQUAL)
                ->filterByGroupId($this->getGroupId())
                ->filterByLevelId($this->getLevelId())
                ->count();

            if ($otherConfigCount) {
                $context->buildViolation('ERROR_DUPLICATE_LEVEL')
                    ->atPath('lsuLevel')
                    ->addViolation()
                ;
            }
        }
    }

}

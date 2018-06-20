<?php
namespace BNS\App\LsuBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\LsuBundle\Model\LsuConfig;
use BNS\App\LsuBundle\Model\LsuConfigQuery;
use BNS\App\LsuBundle\Model\LsuLevelQuery;


/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuConfigManager
{
    protected $groupManager;

    public function __construct(BNSGroupManager $groupManager)
    {
        $this->groupManager = $groupManager;
    }

    /**
     * @param Group $group
     * @return array|mixed|\PropelObjectCollection
     */
    public function getConfigs(Group $group)
    {
        $configs = LsuConfigQuery::create()
            ->filterByGroup($group)
            ->useLsuLevelQuery()
                ->orderBySortableRank()
            ->endUse()
            ->find()
        ;
        if ($configs->count()) {
            $pupilIds = $this->groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('PUPIL');
            /** @var LsuConfig $config */
            foreach ($configs as $config) {
                $this->filterConfigUsers($config, $pupilIds);
            }

            return $configs;
        }

        return [];
    }

    public function decorateConfig(LsuConfig $config)
    {
        $group = $config->getGroup();
        $userIds = $this->groupManager->getUserIdsByRole('TEACHER', $group);
        $teacher = UserQuery::create()->filterById($userIds)->findOne();
        if ($teacher) {
            $config->setTeacherInfo(array(
                'name' => $teacher->getFullName(),
                'gender' => $teacher->getGender()
            ));
        }

        //Get school group
        $school = $this->groupManager->setGroup($group)->getSchool();
        $schoolInfo = array(
            'name' => $school->getLabel(),
            'address' => $school->getAttribute('ADDRESS'),
            'zipcode' => $school->getAttribute('ZIPCODE'),
            'city' => $school->getAttribute('CITY')
        );
        $config->setSchoolInfo($schoolInfo);
    }

    /**
     * @param Group $group
     * @param LsuConfig[]|\PropelObjectCollection $configs
     * @return int[] list of user id
     */
    public function getPupilNotInConfigs(Group $group, $configs)
    {
        $pupilIds = $this->groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('PUPIL');
        foreach ($configs as $config) {
            $pupilIds = array_diff($pupilIds, $config->getUserIds());
        }

        return UserQuery::create()->filterById($pupilIds)->find();
    }

    /**
     * @param Group $group
     * @return array|bool
     */
    public function initConfigs(Group $group)
    {
        if (LsuConfigQuery::create()->filterByGroup($group)->count()) {
            return false;
        }
        $levels = LsuLevelQuery::create()
            ->filterByCode($group->getAttribute('LEVEL'), \Criteria::IN)
            ->distinct()
            ->orderById()
            ->find()
        ;

        $configs = null;
        foreach ($levels as $level) {
            $config = new LsuConfig();
            $config->setGroup($group);
            $config->setLsuLevel($level);
            if (!$configs) {
                $configs = [];
                // set all pupil in the first config
                $pupilIds = $this->groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('PUPIL');
                $config->setUserIds($pupilIds);
            }
            $config->save();
            $configs[] = $config;
        }

        return $configs ? : [];
    }

    public function filterConfigUsers(LsuConfig $config, array $validUserIds = null)
    {
        if (!$validUserIds && $config->getGroup()) {
            $validUserIds = $this->groupManager->setGroup($config->getGroup())->getUsersByRoleUniqueNameIds('PUPIL');
        }

        if (!$validUserIds) {
            throw new \InvalidArgumentException('config should have a group or validUserIds should be provided');
        }
        $config->setUserIds(array_intersect($config->getUserIds(), $validUserIds));

        return $config;
    }
}

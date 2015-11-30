<?php

namespace BNS\App\FixtureBundle\Marker;

use BNS\App\CoreBundle\Classroom\BNSClassroomManager;
use BNS\App\CoreBundle\Model\Group;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MarkerManager
{
    /**
     * @var array<MarkerInterface>
     */
	private $markers;

    /**
     * @var BNSClassroomManager
     */
    private $classroomManager;

    /**
     * @var Group
     */
    private $group;

    /**
     * @var array<String, User>
     */
    private $groupUsers;


    /**
     * @param BNSClassroomManager $classroomManager
     */
    public function __construct(BNSClassroomManager $groupManager)
    {
        $this->classroomManager = $groupManager;
    }

    /**
     * @see \BNS\App\FixtureBundle\CompilerPass\MarkerCompilerPass::process
     *
     * @param MarkerInterface $marker
     */
    public function addMarker(MarkerInterface $marker)
    {
        $this->markers[] = $marker;
    }

    /**
     * @param string $markerName
     *
     * @return MarkerInterface
     */
    public function getMarker(\ColumnMap $column)
    {
        foreach ($this->markers as $marker) {
            if ($marker->isMatch($column)) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * @param \BNS\App\FixtureBundle\Marker\Group $group
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;
        $this->classroomManager->setGroup($group);
    }

    /**
     * @return Group
     * 
     * @throws \RuntimeException
     */
    public function getGroup()
    {
        if (!isset($this->group)) {
            throw new \RuntimeException('You must call "setGroup(Group $group)" before using this method !');
        }

        return $this->group;
    }

    /**
     * @return array
     */
    public function getGroupUsers()
    {
        if (!isset($this->group)) {
            throw new \RuntimeException('You must call "setGroup(Group $group)" before using this method !');
        }

        if (!isset($this->groupUsers)) {
            $this->groupUsers = array();
            $this->groupUsers['TEACHER'] = $this->classroomManager->getTeachers();
            $this->groupUsers['PARENT'] = $this->classroomManager->getPupilsParents();
            $this->groupUsers['PUPIL'] = $this->classroomManager->getPupils();
        }

        return $this->groupUsers;
    }
}
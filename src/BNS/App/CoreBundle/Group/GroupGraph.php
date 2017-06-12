<?php

namespace BNS\App\CoreBundle\Group;

use BNS\App\CoreBundle\Model\GroupQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class GroupGraph
{
    /**
     * @var array<GroupNode>
     */
    private $nodes;

    /**
     * @var array<Group>
     */
    private $groups;

    /**
     * @param array $groups
     */
    public function __construct(array $groups = array())
    {
        $this->fromArray($groups);
    }

    /**
     * @param array $groups
     */
    public function fromArray(array $groups)
    {
        $this->nodes = array();
        foreach ($groups as $group) {
            $this->nodes[] = new GroupNode($group, $this);
        }
    }

    /**
     * @param int $groupId
     * 
     * @return boolean
     */
    public function hasNode($groupId)
    {
        foreach ($this->nodes as $node) {
            if ($node->getData('id') == $groupId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $groupId
     *
     * @return GroupNode
     *
     * @throws \InvalidArgumentException
     */
    public function getNode($groupId)
    {
        foreach ($this->nodes as $node) {
            if ($node->getData('id') == $groupId) {
                return $node;
            }
        }

        throw new \InvalidArgumentException('The node with id : ' . $groupId . ' is NOT found !');
    }

    /**
     * @param GroupNode $node
     */
    public function addNode($node)
    {
        $this->nodes[] = $node;
    }

    /**
     * @return array<GroupNode> 
     */
    public function getParents()
    {
        $parents = array();
        foreach ($this->nodes as $node) {
            if (!$node->hasParents()) {
                $parents[] = $node;
            }
        }

        return $parents;
    }

    /**
     * @param int $id
     *
     * @return Group
     *
     * @throws \RuntimeException
     */
    public function getGroup($id)
    {
        if (!isset($this->groups)) {
            $ids = array();
            foreach ($this->nodes as $node) {
                $ids[] = $node->getData('id');
            }

            $groups = GroupQuery::create('g')
                ->joinWith('g.GroupType gt')
                ->joinWith('g.GroupData gd', \Criteria::LEFT_JOIN)
            ->findPks($ids);
            
            foreach ($groups as $group) {
                $this->groups[$group->getId()] = $group;
            }
        }

        if (!isset($this->groups[$id])) {
            throw new \RuntimeException('The group with id : ' . $id . ' is NOT in the node spool !');
        }

        return $this->groups[$id];
    }

    /**
     * @return GroupNode
     */
    public function getNodes()
    {
        return $this->nodes;
    }
}
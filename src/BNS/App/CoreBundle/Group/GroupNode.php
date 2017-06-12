<?php

namespace BNS\App\CoreBundle\Group;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class GroupNode
{
    /**
     * array
     */
    private $data;
    
    /**
     * @var array<GroupNode>
     */
    private $parents = array();

    /**
     * @var array<GroupNode> 
     */
    private $children = array();

    /**
     * @var GroupGraph
     */
    private $graph;


    /**
     * @param array      $group
     * @param GroupGraph $graph
     */
    public function __construct($group, $graph)
    {
        $this->graph = $graph;
        
        if (isset($group['parent_groups'])) {
            foreach ($group['parent_groups'] as $gParent) {
                $this->addParent($gParent);
            }
            
            unset($group['parent_groups']);
        }
        
        $this->data = $group;
    }

    /**
     * @param array $child
     */
    public function addChild($child)
    {
        $this->children[] = $child;
    }

    /**
     * @param array      $parent
     * @param GroupGraph $graph
     */
    public function addParent($parent)
    {
        if ($this->graph->hasNode($parent['id'])) {
            $node = $this->graph->getNode($parent['id']);
        }
        else {
            $node = new GroupNode($parent, $this->graph);
            $this->graph->addNode($node);
        }

        $this->parents[] = $node;
        $node->addChild($this);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getData($name, $default = null)
    {
        return isset($this->data[$name]) ? $this->data[$name] : $default;
    }

    /**
     * @return boolean
     */
    public function hasParents()
    {
        return isset($this->parents[0]);
    }

    /**
     * @return boolean
     */
    public function hasChildren()
    {
        return isset($this->children[0]);
    }

    /**
     * @return array<GroupNode>
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return array<GroupNode>
     */
    public function getParents()
    {
        return $this->parents;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->graph->getGroup($this->getData('id'));
    }
}
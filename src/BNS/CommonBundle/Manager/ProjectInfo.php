<?php
namespace BNS\CommonBundle\Manager;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ProjectInfo
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    public function getProjectInfo($groupId, $key = null)
    {
        if (!$this->hasProjectInfoForGroup($groupId)) {
            return null;
        }

        if (null === $key) {
            return $this->data[$groupId];
        }

        if (isset($this->data[$groupId][$key])) {
            return $this->data[$groupId][$key];
        }

        return null;
    }

    public function hasProjectInfoForGroup($groupId)
    {
        return isset($this->data[$groupId]);
    }
}

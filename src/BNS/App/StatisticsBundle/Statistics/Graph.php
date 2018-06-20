<?php
namespace BNS\App\StatisticsBundle\Statistics;

use JMS\Serializer\Annotation as Serializer;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Graph
{
    const GRAPH_TYPE_LINE = 'line';
    const GRAPH_TYPE_COLUMN = 'column';
    const GRAPH_TYPE_SPLINE = 'spline';
    const GRAPH_TYPE_AREA = 'area';

    const ROLE_MODE_AGGREGATE = 'aggregate';
    const ROLE_MODE_DISTINCT  = 'distinct';
    const ROLE_MODE_ALL_IN_ONE = 'all_in_one';

    /**
     * @var  string
     * @Serializer\Expose()
     */
    protected $name;

    /** @var  array<Indicator> */
    protected $indicators;

    /** @var  array<string> */
    protected $roles;

    /** @var  string */
    protected $roleMode;

    /** @var  boolean */
    protected $aggregate;

    /**
     * @var  string
     * @Serializer\Expose()
     */
    protected $graphType;

    /**
     * @var  string
     * @Serializer\Expose()
     */
    protected $title;

    /**
     * @var  array
     * @Serializer\Expose()
     */
    protected $xAxis = array();

    /**
     * @var  array
     * @Serializer\Expose()
     */
    protected $yAxis = array();

    /**
     * @var  array
     * @Serializer\Expose()
     */
    protected $options;

    protected $unDuplicateGroup = true;

    public function __construct($name, array $indicators, array $roles = array(), $roleMode = self::ROLE_MODE_AGGREGATE, $aggregate = true, $graphType = self::GRAPH_TYPE_SPLINE, array $options = array())
    {
        $this->name = $name;
        $this->indicators = $indicators;
        $this->roles = $roles;
        $this->roleMode = $roleMode;
        $this->aggregate = $aggregate;
        $this->graphType = $graphType;
        $this->options = $options;


        $this->xAxis = array(
            'type' => 'datetime',
//            'dateTimeLabelFormats' => array(// don't display the dummy year
//              'month' => '%e. %b',
//              'year' => '%b'
//            ),
            'title' => array(
                'text' => 'Date'
            )
        );
        $this->yAxis = array(
            'title' => ['text' => isset($options['yAxisTitle'])? $options['yAxisTitle'] : "" ],
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Graph
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function getIndicators()
    {
        return $this->indicators;
    }

    /**
     * @param array $indicators
     * @return Graph
     */
    public function setIndicators($indicators)
    {
        $this->indicators = $indicators;

        return $this;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param array $roles
     * @return Graph
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return string
     */
    public function getRoleMode()
    {
        return $this->roleMode;
    }

    /**
     * @param string $roleMode
     * @return Graph
     */
    public function setRoleMode($roleMode)
    {
        $this->roleMode = $roleMode;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isAggregate()
    {
        return $this->aggregate;
    }

    /**
     * @param boolean $aggregate
     * @return Graph
     */
    public function setAggregate($aggregate)
    {
        $this->aggregate = $aggregate;

        return $this;
    }

    /**
     * @return string
     */
    public function getGraphType()
    {
        return $this->graphType;
    }

    /**
     * @param string $graphType
     * @return Graph
     */
    public function setGraphType($graphType)
    {
        $this->graphType = $graphType;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title ? : $this->getName();
    }

    /**
     * @param string $title
     * @return Graph
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return array
     */
    public function getXAxis($filters)
    {
        return array_merge(array(
            'min' => $filters['start']->format('U')*1000,
            'max' => $filters['end']->format('U')*1000,
        ), $this->xAxis);
    }

    /**
     * @param array $xAxis
     * @return Graph
     */
    public function setXAxis($xAxis)
    {
        $this->xAxis = $xAxis;

        return $this;
    }

    /**
     * @return array
     */
    public function getYAxis($filters)
    {
        return array_merge(array(
            'min' => 0,
        ), $this->yAxis);
    }

    /**
     * @param array $yAxis
     * @return Graph
     */
    public function setYAxis($yAxis)
    {
        $this->yAxis = $yAxis;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return array_merge(array(
            'chart' => array('type' => $this->getGraphType()),
            'tooltip' => array(
                'xDateFormat' => '%A %e %B %Y',
            )
        ), $this->options);
    }

    /**
     * @param array $options
     * @return Graph
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isUnDuplicateGroup()
    {
        return $this->unDuplicateGroup;
    }

    /**
     * @param boolean $unDuplicateGroup
     */
    public function setUnDuplicateGroup($unDuplicateGroup)
    {
        $this->unDuplicateGroup = $unDuplicateGroup;

        return $this;
    }

}

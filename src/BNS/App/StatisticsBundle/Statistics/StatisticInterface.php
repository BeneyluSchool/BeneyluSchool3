<?php
namespace BNS\App\StatisticsBundle\Statistics;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface StatisticInterface
{

    /**
     * @return array<Indicator>
     */
    public function getIndicators();

    /**
     * Graph
     *
     * @return array<Graph>
     */
    public function getGraphs();


    /**
     * Graph options if $graph name match
     * @param string $graph
     * @return Graph
     */
    public function getGraph($graph);

    /**
     * <pre>
     * [
     * 'name' => '',
     * 'columns' => [],
     * 'filters' => [],
     * 'orders'  => [],
     * ]
     * </pre>
     *
     * @return array
     */
    public function getTable();


    /**
     * Allow override of table data, return false for default
     * @param array $filters
     *
     * @return false|array
     */
    public function getTableData($filters);


    /**
     * Statistic unique name
     * @return string
     */
    public function getName();
}

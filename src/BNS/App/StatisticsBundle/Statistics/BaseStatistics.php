<?php
namespace BNS\App\StatisticsBundle\Statistics;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 * @Serializer\ExclusionPolicy("all")
 */
abstract class BaseStatistics implements StatisticInterface
{
    const AGGREGATION_TYPES_SUM     = 2;
    const AGGREGATION_TYPES_COUNT   = 4;
    const AGGREGATION_TYPES_AVG     = 8;
    const AGGREGATION_TYPES_MIN     = 16;
    const AGGREGATION_TYPES_MAX     = 32;

    /** @var  array<Indicator> */
    protected $indicators;

    /** @var  array<Graph> */
    protected $graphs;

    protected $table;

    /** @var  Translator */
    protected $translator;

    /**
     * @Serializer\VirtualProperty()
     *
     * @throws \Exception
     */
    public function getName()
    {
        throw new \Exception('You should overide "getName" in ' . get_class($this));
    }

    /**
     * @Serializer\VirtualProperty()
     */
    public function getTitle()
    {
        /** @Ignore */
        return $this->translator->trans('TITLE_' . $this->getName(), [], 'STATISTICS');
    }

    public function getIndicators()
    {
        return $this->indicators;
    }

    /**
     * @Serializer\VirtualProperty()
     *
     * @return array<Graph>
     */
    public function getGraphs()
    {
        return $this->graphs;
    }

    /**
     * @param string $name
     * @return bool|Graph
     */
    public function getGraph($name)
    {
        /** @var Graph $graph */
        foreach ($this->getGraphs() as $graph) {
            if ($graph->getName() === $name) {
                return $graph;
            }
        }

        return false;
    }

    /**
     * Return false to disable override
     * @param array $filters
     * @param Graph $graph
     * @return bool|array
     */
    public function getGraphData(Graph $graph, $filters)
    {
        return false;
    }

    public function transformDataToGraph($datas, $roleId = null)
    {
        $stats = array();
        foreach ($datas as $i => $data) {

            if (null !== $roleId && $data->getRoleId() !== $roleId) {
                continue;
            }
            $date = date('U', strtotime($data->getDate('Y-m-d 12:00')));
            $stats[] = array($date*1000, (int)$data->getCount());
        }

        return $stats;
    }

    public function getTable()
    {
        return $this->table;
    }

    /**
     * Return false to disable override
     * @param array $filters
     * @return bool|array
     */
    public function getTableData($filters)
    {
        return false;
    }

    public function getTableOptions()
    {
        return array(
            'enableSelectAll'       => true,
            'enableGridMenu'        => true,
            'exporterCsvFilename'   => 'export.csv',
            'showColumnFooter'      => true,
        );
    }

}

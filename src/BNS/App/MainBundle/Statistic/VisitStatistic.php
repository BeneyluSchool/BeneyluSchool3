<?php
namespace BNS\App\MainBundle\Statistic;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\StatisticsBundle\Model\MarkerQuery;
use BNS\App\StatisticsBundle\Statistics\BaseStatistics;
use BNS\App\StatisticsBundle\Statistics\Graph;
use BNS\App\StatisticsBundle\Statistics\Indicator;
use Doctrine\Common\Util\Inflector;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 * @Serializer\ExclusionPolicy("all")
 */
class VisitStatistic extends BaseStatistics
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator, array $options = array())
    {
        $this->translator = $translator;

        $this->indicators = array(
            new Indicator('blog_visit', 'BLOG_VISIT'),
            new Indicator('calendar_visit', 'CALENDAR_VISIT'),
            new Indicator('userdirectory_visit', 'USERDIRECTORY_VISIT'),
            new Indicator('forum_visit', 'FORUM_VISIT'),
            new Indicator('gps_visit', 'GPS_VISIT'),
            new Indicator('homework_visit', 'HOMEWORK_VISIT'),
            new Indicator('liaisonbook_visit', 'LIAISONBOOK_VISIT'),
            new Indicator('lunch_visit', 'LUNCH_VISIT'),
            new Indicator('medialibrary_visit', 'MEDIALIBRARY_VISIT'),
            new Indicator('messaging_visit', 'MESSAGING_VISIT'),
            new Indicator('portal_visit', 'PORTAL_VISIT'),
            new Indicator('profile_visit', 'PROFILE_VISIT'),
            new Indicator('search_visit', 'SEARCH_VISIT'),
            new Indicator('workshop_visit', 'WORKSHOP_VISIT'),
        );

        if (isset($options['indicators'])) {
            $this->indicators = array_values(array_filter($this->indicators, function($indicator) use ($options) {
                return in_array($indicator->getName(), $options['indicators']);
            }));
        };

        $graph = new Graph('graph_visit', $this->indicators, array('TEACHER', 'PUPIL'), Graph::ROLE_MODE_AGGREGATE, true, Graph::GRAPH_TYPE_COLUMN);
        $graph->setTitle('statistic.visit_by_module');
        $graph->setUnDuplicateGroup(false);
        $graph->setOptions(array(
            'plotOptions' => array(
                'column' => array(
                    'stacking' => 'normal'
                )
            )
        ));

        $categories = array();
        foreach ($this->indicators as $indicator) {
            $categories[] = /** @Ignore */ $this->translator->trans('statistic.indicator.' . $indicator->getCode());
        }
        $graph->setXAxis(array(
            'min' => null,
            'max' => null,
            'categories' => $categories
        ));
        $graph->setYAxis(array(
            'title' => ['text' => $this->translator->trans('VALUES', [], 'STATISTICS')],
            'stackLabels' => array(
                'enabled' => true
            )
        ));

        $this->graphs = array(
            $graph,
        );
    }

    /**
     * Return false to disable override
     * @param array $filters
     * @param Graph $graph
     * @return bool|array
     */
    public function getGraphData(Graph $graph, $filters)
    {
        $indicators = $graph->getIndicators();

        $groups = $filters['groupIds'];
        $childGroups = array();

        $roles = GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType($graph->getRoles())
            ->find()
            ;
        $data = array();
        /** @var GroupType $role */
        foreach ($roles as $role) {

            $data[$role->getId()] = array(
                'name' => /** @Ignore */ $this->translator->trans('statistic.indicator.by_role.'.$role->getType()),
                'data'  => array(),
            );

            /** @var Indicator $indicator */
            foreach ($indicators as $key => $indicator) {
                $code = $indicator->getCode();
                $query = $this->getStatQuery($code, $filters, false);
                $query->filterByRoleId($role->getId());
                $query->select(array('count'));
                $count = $query->findOne();

                $data[$role->getId()]['data'][$key] = (int)$count;
            }

        }

        return array(
            'config' => array(
                'options' => $graph->getOptions(),
                'title'   => array('text' => ''),
                'series'  => $data,
                'yAxis'   => $graph->getYAxis($filters),
                'xAxis'   => $graph->getXAxis($filters),
            ),
            'groups'      => $groups,
            'childGroups' => $childGroups,
            'title' => /** @Ignore */ $this->translator->trans('statistic.graph.title.' . $graph->getTitle()),
        );
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

    /**
     * Statistic unique name
     * @return string
     */
    public function getName()
    {
        return 'MAIN_VISIT';
    }

    public function getTableData($filters)
    {
        $indicators = $this->getIndicators();

        $roles = GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType(array('TEACHER', 'PUPIL'))
            ->find();

        $data = array();
        /** @var Indicator $indicator */
        foreach ($filters['groupIds'] as $group) {
            $datas = [];
            $options = ['columnDefs' => [
                [
                    'field' => 'role_id',
                    'displayName' => $this->translator->trans('statistic.column.role'),
                    'headerTooltip' => true
                ]
            ]];
            foreach ($indicators as $indicator) {
                $code = $indicator->getCode();
                $query = $this->getStatQuery($code, $filters, false);
                $query->filterByGroupId($group);
                $query->filterByRoleId($roles->getPrimaryKeys(false));
                $query->groupByRoleId();


                $rows = $query
                    ->select(array('RoleId', 'count'))
                    ->find();
                if (count($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($datas[$row['RoleId']])) {
                            $datas[$row['RoleId']] = array(
                                'role_id' => /** @Ignore */
                                    $this->translator->trans('statistic.indicator.by_role.' . $this->getRoleType($row['RoleId']))
                            );
                        }
                        $datas[$row['RoleId']][$indicator->getCode()] = (int)$row['count'];
                    }
                    foreach ($roles as $role) {
                        if (!isset($datas[$role->getId()])) {
                            $datas[$role->getId()] = array(
                                'role_id' => /** @Ignore */
                                    $this->translator->trans('statistic.indicator.by_role.' . $role->getType())
                            );
                        }
                        if (!isset($datas[$role->getId()][$indicator->getCode()])) {
                            $datas[$role->getId()][$indicator->getCode()] = 0;
                        }
                    }

                    $options['columnDefs'][] = [
                        'field' => $code,

                        'displayName' => /** @Ignore */
                            $this->translator->trans('statistic.indicator.' . $code),
                        'headerTooltip' => true
                    ];
                }
            }
            $data[] = array_merge($this->getTableOptions(), array(
                'data' => array_values($datas),
                'group' => $group
            ), $options
            );
        }

        return $data;
    }


    protected function getStatQuery($markerName, $filters, $groupByPeriod = 'DAY')
    {
        $marker = MarkerQuery::create()->filterByUniqueName($markerName)->findOne();
        if (!$marker) {
            throw new \Exception(sprintf('invalid marker : %s', $markerName));
        }
        $module = $marker->getModuleUniqueName();

        $queryClass = "\\BNS\\App\\StatisticsBundle\\Model\\" . Inflector::classify(strtolower($module)) . "Query";
        if (!class_exists($queryClass)) {
            throw new \Exception(sprintf('invalid query class %s', $queryClass));
        }

        $query = $queryClass::create();

        $query->filterByMarker($marker);
        $query->filterByDate(array(
            'min' => $filters['start'],
            'max' => $filters['end']
        ));


        $query->filterByGroupId($filters['groupIds']);
//        $query->orderByDate();
        $query->withColumn("SUM(value)", 'count');

        if ($groupByPeriod) {
            switch ($groupByPeriod) {
                case 'MONTH':
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m')";
                    $dateFormatPhp = "Y-m";
                    break;
                case 'HOUR': //pour l'heure il faut d'abords faire un format sur le jour puis l'heure
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d %H')";
                    $dateFormatPhp = "Y-m-d H:i:s";
                    break;
                case 'DAY':
                default: // par défaut 'DAY'
                    $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d')";
                    $dateFormatPhp = "Y-m-d";
                    break;
            }
            $query->addGroupByColumn($dateFormatPropel);
        }


        return $query;
    }

    /**
     * @param array|string $roles
     * @return int|array<int>
     */
    protected function getRoleIds($roles)
    {
        $query = GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType($roles)
            ->select('Id')
        ;

        if (!is_array($roles) || count($roles) === 1) {
            return (int)$query->findOne();
        }

        return $query->find()->getArrayCopy();
    }

    protected function getRoleType($roleId)
    {
        $role = GroupTypeQuery::create()
            ->filterByRole()
            ->findPk($roleId);

        if ($role) {
            return $role->getType();
        }

        return $roleId;
    }
}

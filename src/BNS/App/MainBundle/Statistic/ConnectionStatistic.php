<?php
namespace BNS\App\MainBundle\Statistic;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\StatisticsBundle\Model\MainQuery;
use BNS\App\StatisticsBundle\Model\MarkerQuery;
use BNS\App\StatisticsBundle\Statistics\BaseStatistics;
use BNS\App\StatisticsBundle\Statistics\Graph;
use BNS\App\StatisticsBundle\Statistics\Indicator;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 * @Serializer\ExclusionPolicy("all")
 */
class ConnectionStatistic extends BaseStatistics
{
    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var BNSRightManager
     */
    protected $rightManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(BNSGroupManager $groupManager, TranslatorInterface $translator, ContainerInterface $container)
    {
        $this->groupManager = $groupManager;
        $this->translator = $translator;
        $this->container = $container;

        $this->indicators = array(
            new Indicator('main_connect', 'MAIN_CONNECT_PLATFORM'),
        );

        $graph = new Graph('graph_connect', $this->indicators, array('TEACHER', 'PUPIL', 'PARENT'), Graph::ROLE_MODE_DISTINCT, true, Graph::GRAPH_TYPE_SPLINE, ['yAxisTitle' => $this->translator->trans('VALUES', [], 'STATISTICS')]);
        $graph->setUnDuplicateGroup(true);
        $this->graphs = array(
            $graph,
        );
    }

    /**
     * Statistic unique name
     * @return string
     */
    public function getName()
    {
        return 'MAIN_CONNECT';
    }

    public function getTableData($filters)
    {
        /** @var Indicator $indicator */
        $indicator = $this->indicators[0];

        $data = array();
        $code = $indicator->getCode();
        $marker = MarkerQuery::create()->filterByUniqueName($code)->findOne();

        $baseQuery = MainQuery::create()->filterByMarker($marker);
        $baseQuery->filterByMarker($marker);
        $baseQuery->filterByDate(array(
            'min' => $filters['start'],
            'max' => $filters['end']
        ));

        $roles = $this->getRoles(array('TEACHER', 'PUPIL', 'PARENT'));

//        $groupIds = array();
        foreach ($filters['groupIds'] as $group) {
            $groupIds = array();
            $group = GroupQuery::create()->findPk($group);
            if (!in_array($group->getType(), array('TEAM', 'SCHOOL', 'CLASSROOM'))) {
                // find child groups
                if ($this->getRightManager()->hasRight('STATISTICS_SCHOOL', $group->getId())) {
                    $groupIds = $this->groupManager->getAllSubgroups($group->getId(), 'SCHOOL', false);
                }
            }

            if (0 === count($groupIds)) {
                $groupIds = array(
                    $group->getId()
                );
            }

            $query = clone $baseQuery;
            $query->filterByGroupId($groupIds);
            $query->groupByGroupId();
            $query->orderByDate();
            $query->filterByRoleId($roles->getPrimaryKeys(false));

            $select = array();
            foreach ($roles as $role) {
                $roleId = (int)$role->getId();
                $columnName = 'count_' . strtolower($role->getType());
                $query->withColumn("SUM(IF(role_id = {$roleId}, value, 0))", $columnName);
                $select[] = $columnName;
            }

            $rows = $query
                ->select(array_merge(array('GroupId'), $select))
                ->find();
            array_walk($rows, function(&$item, $key){
                foreach ($item as $key => $value) {
                    $item[$key] = (int) $value;
                }
                $group = GroupQuery::create()->findPk($item['GroupId']);
                $item['Group'] = $group->getLabel();
                if ($group->getType() === 'SCHOOL') {
                    $item['City'] = $group->getAttribute('CITY') ?: '';
                    $item['UAI'] = $group->getAttribute('UAI') ?: '';
                }
            });

            $data[] = array_merge($this->getTableOptions(count($rows) > 1), array(
                'data'  => $rows,
                'title' => $group->getLabel()
            ));
        }

        return $data;
    }

    public function getTableOptions($withTotalFooter = false)
    {
        $options = parent::getTableOptions();

        $options['columnDefs'] = array(
            array('field' => 'UAI', 'displayName' =>'UAI', 'width' => '10%', 'sort' => array('direction' => 'asc', 'priority' => 0), 'headerTooltip' => true),
            array('field' => 'City', 'displayName' => $this->translator->trans('statistic.column.city'), 'width' => '25%', 'sort' => array('direction' => 'asc', 'priority' => 0), 'headerTooltip' => true),
            array('field' => 'Group', 'displayName' => $this->translator->trans('statistic.column.group'), 'width' => '25%', 'sort' => array('direction' => 'asc', 'priority' => 1), 'headerTooltip' => true),
            array('field' => 'count_teacher', 'displayName' => $this->translator->trans('statistic.column.connexion_count_teacher'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
            array('field' => 'count_pupil', 'displayName' => $this->translator->trans('statistic.column.connexion_count_pupil'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
            array('field' => 'count_parent', 'displayName' => $this->translator->trans('statistic.column.connexion_count_parent'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
        );

        $options['showColumnFooter'] = (bool) $withTotalFooter;

        return $options;
    }

    protected function getRoles($roles)
    {
        return GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType($roles)
            ->find();
    }

    protected function getRightManager()
    {
        if (null === $this->rightManager) {
            $this->rightManager = $this->container->get('bns.right_manager');
        }

        return $this->rightManager;
    }

}

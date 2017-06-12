<?php
namespace BNS\App\GroupBundle\Statistic;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\StatisticsBundle\Model\MarkerQuery;
use BNS\App\StatisticsBundle\Statistics\BaseStatistics;
use BNS\App\StatisticsBundle\Statistics\Graph;
use BNS\App\StatisticsBundle\Statistics\Indicator;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 * Serializer\AccessType("public_method")
 * @Serializer\ExclusionPolicy("all")
 */
class ActivationStatistics extends BaseStatistics
{
    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var array
     */
    protected $subgroupIds = array();

    /**
     * @var array
     */
    protected $subgroupIdsByType = array();


    protected $translator;

    public function __construct(BNSGroupManager $groupManager, TranslatorInterface $translator)
    {
        $this->groupManager = $groupManager;
        $this->translator = $translator;

        // Indicators
        $this->indicators = array(
            new Indicator('classroom_activation', 'GROUP_CLASSROOM_ACTIVATION'),
            new Indicator('school_activation', 'GROUP_SCHOOL_ACTIVATION'),
        );

        // Graph
        $graph = new Graph('graph_classroom_activation', $this->indicators, array(), Graph::ROLE_MODE_ALL_IN_ONE);
        $graph->setTitle('statistic.classroom_school_activation');
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
        return 'CLASSROOM_SCHOOL_ACTIVATION';
    }

    public function getTableData($filters)
    {
        $res = array();

        $groupIds = $filters['groups'];

        foreach ($groupIds as $group) {
            list($data, $totals) = $this->getStatsFormGroup($group, $filters['start'], $filters['end']);

            $res[] = array_merge(
                $this->getTableOptions(),
                array(
                    'data' => $data,
                    'totals' => $totals,
                    'group' => $group,
                )
            );
        }

        return $res;
    }

    public function getTableOptions()
    {
        return array_merge(parent::getTableOptions(), array(
            'columnDefs' => array(
                array('field' => 'city', 'displayName' => $this->translator->trans('statistic.column.city'), 'width' => '20%', 'sort' => array('direction' => 'asc', 'priority' => 0), 'headerTooltip' => true),
                array('field' => 'name', 'displayName' => $this->translator->trans('statistic.column.activation_school_name'), 'width' => '20%', 'sort' => array('direction' => 'asc', 'priority' => 1), 'headerTooltip' => true),
                array('field' => 'activatedClassrooms', 'displayName' => $this->translator->trans('statistic.column.activation_activated_classroom'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
                array('field' => 'classrooms', 'displayName' => $this->translator->trans('statistic.column.activation_classroom_number'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
                array('field' => 'pupils', 'displayName' => $this->translator->trans('statistic.column.activation_pupil_number'), 'aggregationType' => self::AGGREGATION_TYPES_SUM, 'headerTooltip' => true),
                array('field' => 'activationDate', 'displayName' => $this->translator->trans('statistic.column.activation_date'), 'headerTooltip' => true),
            )
        ));
    }

    /**
     * @param Group $group
     * @param string|null $start date
     * @param string|null $end date
     * @return int
     */
    public function getSchoolActivationNumber(Group $group, $start = null, $end = null)
    {
        return $this->getGroupActivationQuery($group, 'SCHOOL', $start, $end)->count();
    }

    public function getSchoolNumber(Group $group, $start = null, $end = null)
    {
        return $this->getGroupFilterQuery($group, 'SCHOOL', $start, $end)->count();
    }

    /**
     * @param Group $group
     * @param string|null $start date
     * @param string|null $end date
     * @return int
     */
    public function getClassroomActivationNumber(Group $group, $start = null, $end = null)
    {
        return $this->getGroupActivationQuery($group, 'CLASSROOM', $start, $end)->count();
    }

    public function getClassroomNumber(Group $group, $start = null, $end = null)
    {
        return $this->getGroupFilterQuery($group, 'CLASSROOM', $start, $end)->count();
    }

    public function generateAllActivationStatistics($date = null)
    {
        $groups = GroupQuery::create()->setFormatter(\ModelCriteria::FORMAT_ON_DEMAND)->find();

        foreach ($groups as $group) {
            $this->generateActivationStatisticsData($group, $date);
        }
    }

    public function generateActivationStatisticsData(Group $group, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d', strtotime('yesterday noon'));
        }
        $groupType = $group->getGroupType();

        if (in_array($groupType->getType(), array('TEAM', 'CLASSROOM'))) {
            return;
        }

        $nbClassroom = $this->getClassroomActivationNumber($group, null, $date);
        $nbSchool = $this->getSchoolActivationNumber($group, null, $date);

        $marker = MarkerQuery::create()->filterByUniqueName('GROUP_CLASSROOM_ACTIVATION')->findOne();
        $classroom = \BNS\App\StatisticsBundle\Model\GroupQuery::create()
            ->filterByMarker($marker)
            ->filterByGroupId($group->getId())
            ->filterByDate($date, \Criteria::EQUAL)
            ->findOneOrCreate()
        ;
        $classroom->setValue($nbClassroom);
        $classroom->save();

        if ('SCHOOL' === $groupType->getType()) {
            return;
        }

        $marker = MarkerQuery::create()->filterByUniqueName('GROUP_SCHOOL_ACTIVATION')->findOne();
        $school = \BNS\App\StatisticsBundle\Model\GroupQuery::create()
            ->filterByMarker($marker)
            ->filterByGroupId($group->getId())
            ->filterByDate($date, \Criteria::EQUAL)
            ->findOneOrCreate()
        ;
        $school->setValue($nbSchool);
        $school->save();
    }


    public function getGroupFilterQuery(Group $group, $groupType, $start = null, $end = null)
    {
        $query = $this->getGroupQuery($group, $groupType);

        if ($start) {
            $query->filterByRegistrationDate($start, \Criteria::GREATER_EQUAL);
        }

        if ($end) {
            $query->filterByRegistrationDate($end, \Criteria::LESS_EQUAL);
        }

        return $query;
    }


    protected function getStatsFormGroup($group, $start, $end)
    {
        if ($group->getType() === 'SCHOOL') {
            $schools = array($group);
        } else {
            $schools = $this->getGroupActivationQuery($group, 'SCHOOL')->find();
        }

        $data = array();
        $totals = array(
            'activatedSchools'    => 0,
            'activatedClassrooms' => 0,
            'classrooms'          => 0,
            'pupils'              => 0,
        );
        foreach ($schools as $school) {
            if (!$school->isEnabled()) {
                continue;
            }
            $nbPupils = (int)$this->groupManager->setGroup($school)->getNbUsers('PUPIL');
            $nbActivatedClassrooms = $this->getClassroomActivationNumber($school, $start, $end);
            $nbClassrooms = $this->getClassroomNumber($school);

            $data[] = array(
                'name'                  => $school->getLabel(),
                'city'                  => $school->getAttribute('CITY'),
                'pupils'                => $nbPupils,
                'activatedClassrooms'   => $nbActivatedClassrooms,
                'classrooms'            => $nbClassrooms,
                'activationDate'        => $school->getEnabledAt('Y-m-d')
            );
            $totals['activatedSchools']++;
            $totals['activatedClassrooms'] += $nbActivatedClassrooms;
            $totals['classrooms'] += $nbClassrooms;
            $totals['pupils'] += $nbPupils;
        }

        return array($data, $totals);
    }




    /**
     * @param Group $group
     * @param string $groupType "SCHOOL", "CLASSROOM"
     * @return GroupQuery
     */
    protected function getGroupQuery(Group $group, $groupType)
    {
        $subGroupIds = $this->getAllSubgroupIds($group->getId(), $groupType);

        $query = GroupQuery::create()
            ->filterById($subGroupIds)
            ->groupById()
        ;

        return $query;
    }

    /**
     * @param Group $group
     * @param $groupType "SCHOOL", "CLASSROOM"
     * @param string|null $start date
     * @param string|null $end date
     * @return GroupQuery
     */
    public function getGroupActivationQuery(Group $group, $groupType, $start = null, $end = null)
    {
        $query = $this->getGroupQuery($group, $groupType)
            ->filterByEnabled(true)
        ;

        if ($start) {
            $query->filterByEnabledAt($start, \Criteria::GREATER_EQUAL);
            $query->_or()->filterByEnabledAt(null, \Criteria::ISNULL);
        }
        if ($end) {
            $query->filterByEnabledAt($end, \Criteria::LESS_EQUAL);
            $query->_or()->filterByEnabledAt(null, \Criteria::ISNULL);
        }

        return $query;
    }

    /**
     * Get All Sub Group Ids with cache
     *
     * @param $groupId
     * @param string|null $groupType
     * @return mixed
     * @throws \PropelException
     */
    protected function getAllSubgroupIds($groupId, $groupType = null)
    {
        if (null === $groupType) {
            if (!isset($this->subgroupIds[$groupId])) {
                $this->subgroupIds[$groupId] = $this->groupManager->getOptimisedAllSubGroupIds($groupId);
            }

            return $this->subgroupIds[$groupId];
        }

        if (!isset($this->subgroupIdsByType[$groupType])) {
            $this->subgroupIdsByType[$groupType] = array();
        }
        if (!isset($this->subgroupIdsByType[$groupType][$groupId])) {
            $this->subgroupIdsByType[$groupType][$groupId] = GroupQuery::create()
                ->filterById($this->getAllSubgroupIds($groupId), \Criteria::IN)
                ->useGroupTypeQuery()
                    ->filterByType($groupType)
                ->endUse()
                ->select(array('Id'))
                ->find()
                ->getArrayCopy()
            ;
        }

        return $this->subgroupIdsByType[$groupType][$groupId];
    }
}

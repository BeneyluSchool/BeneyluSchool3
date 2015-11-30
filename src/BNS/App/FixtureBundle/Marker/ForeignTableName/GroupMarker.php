<?php

namespace BNS\App\FixtureBundle\Marker\ForeignTableName;

use BNS\App\FixtureBundle\Marker\MarkerManager;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class GroupMarker extends AbstractForeignTableNameMarker
{
    /**
     * @var MarkerManager
     */
    private $manager;

    /**
     * @param MarkerManager $manager
     */
    public function __construct(MarkerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @return string 
     */
    public function getTableName()
    {
        return 'group';
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param DialogHelper $dialog
     * @param \ColumnMap $column
     * @param mixed $value
     *
     * @return string
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value)
    {
        return 'GROUP()';
    }

    /**
     * @param \ColumnMap $column
     * @param Object $obj
     * @param mixed $value
     *
     * @return id
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value)
    {
        // We don't want new INSERT but UPDATE the current table. So deleting the row before insert values
        $queryClass = substr($column->getTable()->getPeerClassname(), 0, -strlen('Peer')) . 'Query';
        $filterBy = 'filterBy' . $column->getPhpName();

        $groupId = $this->manager->getGroup()->getId();
        $queryClass::create()->$filterBy($groupId)->delete();

        return $groupId;
    }
}
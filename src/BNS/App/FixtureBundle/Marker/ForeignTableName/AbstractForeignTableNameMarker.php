<?php

namespace BNS\App\FixtureBundle\Marker\ForeignTableName;

use BNS\App\FixtureBundle\Marker\AbstractMarker;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractForeignTableNameMarker extends AbstractMarker
{
    /**
     * @param \ColumnMap $column
     *
     * @return boolean
     */
    public function isMatch(\ColumnMap $column)
    {
        return strtolower($this->getTableName()) == strtolower($column->getRelatedTableName());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'ForeignTableNameMarker::' . strtolower($this->getTableName());
    }

    /**
     * @return string
     */
    public abstract function getTableName();
}
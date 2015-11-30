<?php

namespace BNS\App\FixtureBundle\Marker\ColumnType;

use BNS\App\FixtureBundle\Marker\AbstractMarker;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractColumnTypeMarker extends AbstractMarker
{
    /**
     * @param \ColumnMap $column
     *
     * @return boolean
     */
    public function isMatch(\ColumnMap $column)
    {
        return strtoupper($this->getColumnType()) == strtoupper($column->getType());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'ColumnTypeMarker::' . strtoupper($this->getColumnType());
    }

    /**
     * @return string
     */
    public abstract function getColumnType();
}
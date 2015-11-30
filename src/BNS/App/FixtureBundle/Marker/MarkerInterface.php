<?php

namespace BNS\App\FixtureBundle\Marker;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface MarkerInterface
{
    /**
     * @param \ColumnMap $column The column map
     */
    public function isMatch(\ColumnMap $column);

    /**
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     * @param DialogHelper    $dialog The console dialog helper, if you want ask a question
     * @param \ColumnMap      $column The current dumped column map
     * @param mixed           $value  The current dumped value
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value);

    /**
     * @param InputInterface $input  The console input
     * @param \ColumnMap     $column The current loaded column map
     * @param Object         $obj    The current loaded propel object
     * @param mixed          $value  The current loaded value
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value);
}
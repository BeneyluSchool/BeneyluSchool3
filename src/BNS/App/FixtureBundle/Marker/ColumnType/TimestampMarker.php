<?php

namespace BNS\App\FixtureBundle\Marker\ColumnType;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class TimestampMarker extends AbstractColumnTypeMarker
{
    /**
     * @return string
     */
    public function getColumnType()
    {
        return 'timestamp';
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param DialogHelper    $dialog
     * @param \ColumnMap      $column
     * @param mixed           $value
     *
     * @return string
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value)
    {
        $output->writeln(array(
            '',
            '   ---------------',
            '   1: timestamp already setted (' . $value . ')',
            '   2: current time when the fixture is loaded',
            '   3: current time plus a value of your choice, in second (ex: NOW+360, NOW+(-360))',
            '   4: a custom timestamp, fixed in time',
            '   ---------------',
            ''
        ));

        $choice = null;
        while (!in_array($choice, range(1, 4))) {
            $choice = $dialog->ask($output, '   > Your choice ? [1]: ', '1');
        }

        switch ($choice) {
            case 1: return $value;
            case 3:
                $value = null;
                while (!is_numeric($value)) {
                    $value = $dialog->ask($output, '    > Your diffence value in second (can be a negative value to return to the past) : ');
                }
            return 'NOW(' . $value . ')';

            case 4:
                $isCorrect = false;
                while (!$isCorrect) {
                    $value = $dialog->ask($output, '    > Enter a correct timestamp (ex: 2000-12-24 23:56:41) : ');
                    
                    try {
                        new ExtendedDateTime($value);
                        $isCorrect = true;
                    }
                    catch (\Exception $e) {
                        // Nothing, back to while statement
                    }
                }
            return $value;
        }

        return 'NOW';
    }

    /**
     * @param \ColumnMap $column
     * @param Object $obj
     * @param mixed $value
     *
     * @return ExtendedDateTime
     *
     * @throws \InvalidArgumentException
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value)
    {
        // Easy case, DateTime can understand "NOW" and a timestamp
        try {
            return new ExtendedDateTime($value);
        }
        catch (\Exception $e) {
            // Nothing, by deduction we can say it's "NOW(x)" pattern
        }

        // NOW(number) case
        if (!$matches = $this->getMarkers('NOW', $value)) {
            throw new \InvalidArgumentException('Unknown marker for ' . $this->getColumnType() . ' value: ' . $value);
        }

        return time() + $matches[0];
    }
}
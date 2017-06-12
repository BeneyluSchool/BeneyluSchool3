<?php

namespace BNS\App\CoreBundle\Utils;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class Console
{
    /**
	 * @param OutputInterface $output
	 * @param int             $size
	 * @param int             $progress
	 * @param boolean         $newLine
	 */
	public static function progress(OutputInterface $output, $size, $progress = 0, $newLine = false)
	{
		if (0 == $progress) {
			$output->write('	> ');
		}

		if ($progress > 0) {
			$output->write(str_repeat("\x08", $size + 2));
		}

		$progressBar = '[';
		for ($i=0; $i<$size; $i++) {
			$progressBar .= $i < $progress ? '=' : ' ';
		}
		$progressBar .= ']';

		$output->write($progressBar, $newLine);
	}
}
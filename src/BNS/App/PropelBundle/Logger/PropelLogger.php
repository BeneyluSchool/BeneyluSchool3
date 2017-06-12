<?php

namespace BNS\App\PropelBundle\Logger;

use Symfony\Bridge\Propel1\Logger\PropelLogger as BasePropelLogger;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class PropelLogger extends BasePropelLogger
{
	/**
	 * @var boolean 
	 */
	private $isPrepared = false;

	/**
     * A convenience function for logging a debug event.
     *
     * @param mixed $message the message to log.
     */
    public function debug($message)
    {
        $add = true;

        if (null !== $this->stopwatch) {
            $trace = debug_backtrace();
            $method = $trace[2]['args'][2];

            $watch = 'Propel Query '.(count($this->queries)+1);
            if ('PropelPDO::prepare' === $method) {
                $this->isPrepared = true;
                $this->stopwatch->start($watch, 'propel');

                $add = false;
            } elseif ($this->isPrepared) {
                $this->isPrepared = false;
                $this->stopwatch->stop($watch);
            }
        }

        if ($add) {
            if (null !== $this->logger) {
                $this->logger->debug($message);
            }
			
			// Stacktrace process
			if (!isset($trace)) {
				$trace = debug_backtrace();
			}
			
			$cleanedTrace = array();
			foreach ($trace as $line) {
				// Check before condition
				if (!isset($line['class']) || !isset($line['file'])) {
					continue;
				}
				// Keeping only file with "BNS" namespace
				else if (preg_match('#BNS#', $line['file'])) {
					$i = count($cleanedTrace);
					$cleanedTrace[$i]['file']		= $line['file'];
					$cleanedTrace[$i]['function']	= $line['function'];
					$cleanedTrace[$i]['line']		= $line['line'];
					$cleanedTrace[$i]['class']		= $line['class'];
					
					foreach ($line['args'] as $arg) {
						if (is_array($arg)) {
							$cleanedTrace[$i]['args'][] = 'Array out of depth';
						}
						else {
							$cleanedTrace[$i]['args'][] = $arg;
						}
					}
				}
			}
			try{
                $this->queries[] = $message . ' | ' . json_encode($cleanedTrace);
            }catch(\Exception $e){

            }

        }
    }
}
<?php
namespace BNS\App\CoreBundle\Snappy;

use Knp\Snappy\Pdf;

class BnsPdf extends Pdf
{

    /**
     * Checks the process return status
     *
     * @param int    $status  The exit status code
     * @param string $stdout  The stdout content
     * @param string $stderr  The stderr content
     * @param string $command The run command
     *
     * @throws \RuntimeException if the output file generation failed
     */
    protected function checkProcessStatus($status, $stdout, $stderr, $command)
    {
        /**
         * On accepte les codes 0 et 1 (voir https://github.com/wkhtmltopdf/wkhtmltopdf/issues/1502)
         */
        if (0 !== $status and 2 !== $status and 1 !== $status and '' !== $stderr) {
            throw new \RuntimeException(sprintf(
                'The exit status code \'%s\' says something went wrong:'."\n"
                .'stderr: "%s"'."\n"
                .'stdout: "%s"'."\n"
                .'command: %s.',
                $status, $stderr, $stdout, $command
            ));
        }
    }

}

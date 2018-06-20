<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 16/02/2017
 * Time: 12:11
 */

namespace BNS\App\CompetitionBundle\Install;


use BNS\App\InstallBundle\Process\AbstractInstallProcess;

class InstallProcess extends AbstractInstallProcess
{
    public function getType()
    {
        return 'SUBAPP';
    }
}

<?php

namespace BNS\App\UserBundle\Model;

use BNS\App\UserBundle\Model\om\BaseUserMerge;

class UserMerge extends BaseUserMerge
{
    /**
     * @param string $message
     * @return $this
     */
    public function addLog($message)
    {
        $logs = $this->getLog();
        if (!$logs || !is_array($logs)) {
            $logs = [];
        }
        $logs[] = [
            'date' => date('U'),
            'message' => $message
        ];

        $this->setLog($logs);

        return $this;
    }
}

<?php

namespace BNS\App\BoardBundle\Model;

use BNS\App\BoardBundle\Model\om\BaseBoard;

class Board extends BaseBoard
{
    const PERMISSION_BOARD_ACCESS         = 'BOARD_ACCESS';
    const PERMISSION_BOARD_ACCESS_BACK    = 'BOARD_ACCESS_BACK';
    const PERMISSION_BOARD_ACTIVATION     = 'BOARD_ACTIVATION';
}

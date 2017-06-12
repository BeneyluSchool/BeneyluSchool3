<?php

namespace BNS\App\ClassroomBundle\DataReset\User;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface DataResetUserInterface
{
    /**
     * @return Le nom du data reset
     */
    public function getName();

    /**
     * @param array<Integer> $usersId La liste des utilisateurs à reset
     */
    public function reset($usersId);
}
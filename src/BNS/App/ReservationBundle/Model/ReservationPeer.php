<?php

namespace BNS\App\ReservationBundle\Model;

use BNS\App\ReservationBundle\Model\om\BaseReservationPeer;

class ReservationPeer extends BaseReservationPeer
{
    /**
     * Création de l'agenda, généralement à la création du groupe
     * @param $params Tableau des valeurs nécessaires
     * @return Reservation
     */
    public static function createReservation($params)
    {
        $reservation = new Reservation();

        $reservation->setTitle($params['label']);
        if (isset($params['group_id'])) {
            $reservation->setGroupId($params['group_id']);
        }

        $arrayKeys = array_keys(Reservation::$colorsClass);
        $random_class = $arrayKeys[rand(0, count($arrayKeys) - 1)];
        $reservation->setColorClass($random_class);
        $reservation->initReservationName($params['label']);

        return $reservation->save();
    }
}

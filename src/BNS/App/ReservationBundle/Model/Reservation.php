<?php

namespace BNS\App\ReservationBundle\Model;

use BNS\App\ReservationBundle\Model\om\BaseReservation;

class Reservation extends BaseReservation
{
    public static $colorsClass = array(
            'cal-green'      => 'A7C736',
            'cal-red'        => 'E8452E',
            'cal-orange'     => 'F8B93C',
            'cal-pink'       => 'C97378',
            'cal-blue'       => '63B4BB',
            'cal-light-blue' => '9BD3D5',
            'cal-brown'      => 'FAC53E',
            );

    //Initialise le nom de l'agenda
    /*
     * @param $name : Nom du groupe pour l'instant
     */
    public function initReservationName($name)
    {
        //TODO prendre en compte la langue par d�faut de la classe
        $this->setTitle($name);
    }

    public function saveColorClassFromColorHex($colorHex)
    {
        $colorClass;
        foreach (self::$colorsClass as $key => $value) {
            if ($value == ($colorHex)) {
                $colorClass = $key;
                break;
            }
        }

        $this->setColorClass($colorClass);

        $this->save();
    }

    public function preSave(\PropelPDO $con = null)
    {
        if ($this->isNew()) {
            if (!$this->getTitle()) {
                $this->setTitle('Réservation');
            }

            if (!$this->getColorClass()) {
                $this->setColorClass('cal-red');
            }
        }

        return parent::preSave($con);
    }

}

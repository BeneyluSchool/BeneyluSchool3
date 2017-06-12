<?php

namespace BNS\App\CoreBundle\Utils;

/**
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class Byte
{
    /**
    * Transforme une valeur en octet en une chaine de caractère représentant une taille de précision $precision 
    */
    public static function formatBytes($bytes, $precision = 2)
    { 
        $units = array('o', 'Ko', 'Mo', 'Go', 'To'); 

        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 

        // Uncomment one of the following alternatives
         $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision) . ' ' . $units[$pow]; 
    } 
}
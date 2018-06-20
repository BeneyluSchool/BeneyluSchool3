<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\LsuBundle\Model\om\BaseLsuPeer;

class LsuPeer extends BaseLsuPeer
{
    public static function getAccompanyingConditions()
    {
        return [
            "PAP" => "Projet d’accompagnement personnalisé",
            "PPS" => "Projet personnalisé de scolarisation",
            "UPE2A" => "Unité pédagogique pour élèves allophones arrivants",
            "PAI" => "Projet d’accueil individualisé",
            "RASED" => "Réseau d'aides spécialisées aux élèves en difficulté",
            "ULIS" => "Unité localisée pour l’inclusion scolaire",
            "PPRE" => "Projet personnalisé de réussite éducative",
        ];
    }
}

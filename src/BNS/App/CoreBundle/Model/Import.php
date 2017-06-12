<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseImport;

class Import extends BaseImport
{

    /*
     * Initialisation de l'objet import
     */
    public function initialise($type, $groupId = null)
    {
        $this->setDateCreate(time());
        $this->setStatus('CREATED');
        $this->setType($type);
        $this->setFileLineProcessedNbr(0);
        $this->setFileLineAddNbr(0);
        $this->setFileLineModifyNbr(0);
        $this->setFileLineDeleteNbr(0);
        if($groupId)
        {
            $this->setGroupId($groupId);
        }
        $this->start();
    }

    /**
     * Lancement d'un import
     */
    public function start()
    {
        $this->setStatus('STARTED');
        $this->setDateLaunch(time());
        $this->save();
    }

    /**
     * Fin d'un import
     */
    public function finish()
    {
        $this->setStatus('FINISHED');
        $this->setDateEnd(time());
        $this->save();
    }

    /**
     * Set le nombre d'entrées dans le fichier
     * @param $nbRows Nombre d'entrées dans le fichier
     */
    public function setNbRows($nbRows)
    {
        $this->setFileLineTotalNbr($nbRows);
        $this->save();
    }

    /**
     * Incrémente le nombre de données traitées
     * @param null $type le type de données
     */
    public function incrementRows($type = null)
    {
        $this->setFileLineProcessedNbr($this->getFileLineProcessedNbr() + 1);
        if($type)
        {
            switch($type)
            {
                case 'ADD':
                    $this->setFileLineAddNbr($this->getFileLineAddNbr() + 1);
                    break;
                case 'MODIFY':
                    $this->setFileLineModifyNbr($this->getFileLineModifyNbr() + 1);
                    break;
                case 'DELETE':
                    $this->setFileLineDeleteNbr($this->getFileLineDeleteNbr() + 1);
                    break;
            }
        }
        $this->save();
    }

    /**
     * Met l'import en erreur
     * @param $message Message à placer
     */
    public function error($message)
    {
        $this->setErrorMsg($message);
        $this->setStatus('ERROR');
        $this->save();
    }

    /**
     * L'import est il en erreur
     * @return bool
     */
    public function hasError()
    {
        return $this->getErrorMsg() != null;
    }

    //// Fonction d'impression des libellés  \\\\\

    public function printType()
    {
        switch($this->getType())
        {
            case 'PersRelEleve':
                $label = "Parents d'élèves";
                break;
            case 'PersEducNat':
                $label = "Personnel Education Nationnale";
                break;
            case 'Eleve':
                $label = "Elèves";
                break;
            case 'EtabEducNat':
                $label = "Etablissement Education Nationale";
                break;
            default:
                $label = ' - ';
                break;
        }
        return $label;
    }

    public function printStatus()
    {
        switch($this->getStatus())
        {
            case 'STARTED':
                $label = "En cours";
                break;
            case 'FINISHED':
                $label = "Terminé";
                break;
            case 'ERROR':
                $label = "Erreur";
                break;
            case 'CREATED':
                $label = "Créé";
                break;
            default:
                $label = ' - ';
                break;
        }
        return $label;
    }

    ////// Fonctions "raccourcis" \\\\\\\\

    public function getNbRows()
    {
        return $this->getFileLineTotalNbr();
    }

    public function getNbRowsProcessed()
    {
        return $this->getFileLineProcessedNbr();
    }

    public function getNbAdds()
    {
        return $this->getFileLineAddNbr();
    }

    public function getNbModifys()
    {
        return $this->getFileLineModifyNbr();
    }

    public function getNbDeletes()
    {
        return $this->getFileLineDeleteNbr();
    }

    public function setProjectName($name)
    {
        $this->projectName = $name;
    }

    public function getProjectName()
    {
        return $this->projectName;
    }
}

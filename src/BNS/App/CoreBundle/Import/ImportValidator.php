<?php
namespace BNS\App\CoreBundle\Import;

use \BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use \BNS\App\CoreBundle\Model\GroupDataQuery;
use BNS\App\CoreBundle\Model\Import;
use BNS\App\CoreBundle\Model\ImportQuery;
use BNS\App\ResourceBundle\FileSystem\BNSFileSystemManager;
use Gaufrette\Adapter\InMemory as InMemoryAdapter;
use Gaufrette\Filesystem;
use Gaufrette\StreamWrapper;
use \BNS\App\CoreBundle\Model\GroupQuery;

/**
 * Description of ImportValidator
 *
 * @author florian rotagnon <florian.rotagnon@atos.net>
 */
class ImportValidator {

    public static function validateImport(&$import, &$type, $filesystemManager)
    {
        //tableau d'erreurs d'importation
        $errorsArray = array(
            "size"              => array(),
            "uai_form"          => array(),
            "uai_exist"         => array(),
            "string"            => array(),
            "mail"              => array(),
            "structure_form"    => array(),
            "structure_exist"   => array(),
            "insee_form"        => array(),
            "insee_exist"       => array(),
            "circo_form"        => array(),
            "circo_exist"       => array(),
            "zip"               => array(),
            "sexe"              => array(),
            "role"              => array()
        );

        //recuperation du fichier d'import
        $filePath = "/imports/" . $import->getId() . ".csv";
        $fs = $filesystemManager->getFileSystem();
        if (!$fs->has($filePath)) {
            throw new \Exception("File does not exists");
        }


        //creation du fichier en mémoire pour lecture ligne par ligne
        $adapter = new InMemoryAdapter(array($import->getId().'.csv' => $fs->read($filePath)));
        $filesystem = new Filesystem($adapter);

        $map = StreamWrapper::getFilesystemMap();
        $map->set('validation', $filesystem);

        StreamWrapper::register();

        $handle = fopen("gaufrette://validation/".$import->getId().".csv", 'r');
        $rowNbr = 0;
        $lineValidate = 0;
        $lineError = 0;

        //lecture ligne par ligne
        while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
            //saut de la premiere ligne
            /*if($rowNbr > 0) {
                //on ajoute 1 au numéro de ligne car ligne dans excel commence par 1
                if(ImportValidator::validateLine($line, $type, $errorsArray, $rowNbr+1)){
                    $lineValidate++;
                }
                else {
                    $lineError++;
                }
            }*/
            $lineValidate++;
            $rowNbr++;
        }

        //set des resultats de l'import
        $import->setDataValidatedNbr($lineValidate);
        $import->setDataErrorNbr($lineError);

        if($lineError == 0) {
            $import->setStatus("VALIDATE");
        }
        else {
            $import->setStatus("UNVALIDATE");
        }

        $import->setFileLineProcessedNbr(0);
        $import->setFileLineTotalNbr($rowNbr - 1 ); //On ne compte pas l'entête
        $import->save();

        return $errorsArray;
    }

    /**
     * validation d'une ligne format + existence des donnees
     *
     * @param type $line
     * @param type $type
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function validateLine($line, &$type, &$errorArray, $rowNbr)
    {
        $ret = false;

        //verification des colonnes en fonction du type d'import
        switch($type){
            case 'classroom':
                if( ImportValidator::sizeCheck($line, 3, $errorArray, $rowNbr)
                    && ImportValidator::structureCheck($line[0], $errorArray, $rowNbr)
                    && ImportValidator::uaiCheck($line[1], $errorArray, $rowNbr, true)
                    && ImportValidator::stringCheck($line[2], $errorArray, $rowNbr))
                {
                    $ret = true;
                }
                break;
            case 'pupil':
                //pour les eleves l'import se fait forcement dans une classe
                //sinon on ne peut pas créer les comptes parents
                if( ImportValidator::sizeCheck($line, 5, $errorArray, $rowNbr)
                    && ImportValidator::structureCheck($line[0], $errorArray, $rowNbr, true)
                    //&& ImportValidator::uaiCheck($line[1], $errorArray, $rowNbr, true)
                    && ImportValidator::stringCheck($line[2], $errorArray, $rowNbr)
                    && ImportValidator::stringCheck($line[3], $errorArray, $rowNbr)
                    && ImportValidator::sexeCheck($line[4], $errorArray, $rowNbr)) {
                        $ret = true;
                }
                break;
            case 'adult':
                if(ImportValidator::sizeCheck($line, 7, $errorArray, $rowNbr)
                    && (ImportValidator::structureCheck($line[0], $errorArray, $rowNbr, true)
                    || ImportValidator::uaiCheck($line[1], $errorArray, $rowNbr, true))
                    && ImportValidator::stringCheck($line[2], $errorArray, $rowNbr)
                    && ImportValidator::stringCheck($line[3], $errorArray, $rowNbr)
                    && ImportValidator::emailCheck($line[4], $errorArray, $rowNbr)
                    && ImportValidator::roleCheck($line[5], $errorArray, $rowNbr)
                    && ImportValidator::sexeCheck($line[6], $errorArray, $rowNbr)) {
                        $ret = true;
                }
                break;
            default: //school : on ne fait pas la vérification Circo / Ville si line[7] est checked => on fait de la création

                if(!isset($line[7]))
                {
                    if(
                        ImportValidator::sizeCheck($line, 7, $errorArray, $rowNbr)
                        && ImportValidator::circoCheck(@$line[6], $errorArray, $rowNbr, true)
                        && ImportValidator::uaiCheck($line[0], $errorArray, $rowNbr)
                        && ImportValidator::inseeCheck($line[1], $errorArray, $rowNbr, true)
                        && ImportValidator::stringCheck($line[2], $errorArray, $rowNbr)
                        && ImportValidator::stringCheck($line[3], $errorArray, $rowNbr)
                        && ImportValidator::zipCheck($line[4], $errorArray, $rowNbr)
                        && ImportValidator::stringCheck($line[5], $errorArray, $rowNbr)
                    )
                    {
                        $ret = true;
                    }
                }else{
                    if(
                        ImportValidator::sizeCheck($line, 8, $errorArray, $rowNbr)
                        && ImportValidator::uaiCheck($line[0], $errorArray, $rowNbr)
                        && ImportValidator::stringCheck($line[2], $errorArray, $rowNbr)
                        && ImportValidator::stringCheck($line[3], $errorArray, $rowNbr)
                        && ImportValidator::zipCheck($line[4], $errorArray, $rowNbr)
                        && ImportValidator::stringCheck($line[5], $errorArray, $rowNbr)
                    )
                    {
                        $ret = true;
                    }
                }
        }

        return $ret;
    }

    /**
     * verification du nombre de colonne
     *
     * @param type $line
     * @param type $size
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function sizeCheck($line, $size, &$errorArray, $rowNbr)
    {
        $sizeOk = false;

        if(count($line) == $size){
            $sizeOk = true;
        }
        else {
            $errorArray["size"][] = $rowNbr;
        }
        return $sizeOk;
    }

    /**
     * verification de l'uai
     * format + BDD
     *
     * @param type $uai
     * @param type $errorArray
     * @param type $rowNbr
     * @param boolean $existTest si true verifie en base que l'element existe
     * @return boolean
     */
    private static function uaiCheck($uai, &$errorArray, $rowNbr, $existTest = false)
    {
        $isUai = false;

        //test format
        if(\preg_match("`^[0-9]{7}[A-Z]{1}$`", $uai)) {
            //test existence en base
             if($existTest) {
                $school = GroupQuery::create()
                        ->filterBySingleAttribute('UAI', $uai)
                        ->findOne();
                 if($school != null) {
                    $isUai = true;
                }
                else {
                    //erreur l'uai n'existe pas
                    $errorArray["uai_exist"][] = $rowNbr;
                }
             }
             else {
                 $isUai = true;
             }
        }
        else {//erreur format
            $errorArray["uai_form"][] = $rowNbr;
        }

        return $isUai;
    }

    private static function circoCheck($circo, &$errorArray, $rowNbr, $existTest = false)
    {

        $circoOk = false;
        if(\preg_match("`^[0-9]{7}[A-Z]{1}$`", $circo)) {
            if($existTest) {

                //recuperer le grouptype id de la circo
                $circoTypeId = \BNS\App\CoreBundle\Model\GroupTypeQuery::create()
                        ->findOneByType('CIRCONSCRIPTION')
                        ->getId();

                //CIRCO_ID
                $circo_id = GroupTypeDataQuery::create()
                        ->filterByGroupTypeId($circoTypeId)
                        ->findOneByGroupTypeDataTemplateUniqueName("CIRCO_ID")
                        ->getId();

                $groupsData = GroupDataQuery::create()
                    ->filterByGroupTypeDataId($circo_id)
                    ->findByValue($circo);

                //verifier que l'une des valeurs trouvees provient bien d'une commune
                $isCirco = false;
                foreach ($groupsData as $groupData) {
                    $group = GroupQuery::create()
                            ->findOneById($groupData->getGroupId());
                    if($group->getGroupTypeId() == $circoTypeId) {
                        $isCirco = true;
                    }
                }

                if($isCirco) {
                    $circoOk = true;
                }
                else {
                    $errorArray["circo_exist"][] = $rowNbr;
                }
            }
            else {
               $circoOk = true;
            }

        }
        elseif ($circo == null ||  $circo == "" ) {
            $circoOk = true;
        }
        else {
            $errorArray["circo_form"][] = $rowNbr;
        }
        return $circoOk;
    }

    /**
     * verifier du code insee
     * format + BDD
     *
     * @param type $insee
     * @param type $errorArray
     * @param type $rowNbr
     * @param boolean $existTest si true verifie en base que l'element existe
     * @return boolean
     */
    private static function inseeCheck($insee, &$errorArray, $rowNbr, $existTest = false)
    {
        $isInsee = false;
        if(\preg_match("`^[0-9]{5}$`", $insee)) {
            if($existTest) {

                //recuperer le grouptype id de l'ecole
                $cityTypeId = \BNS\App\CoreBundle\Model\GroupTypeQuery::create()
                        ->findOneByType('CITY')
                        ->getId();

                //INSEE_ID ID
                $insee_id = GroupTypeDataQuery::create()
                        ->filterByGroupTypeId($cityTypeId)
                        ->findOneByGroupTypeDataTemplateUniqueName("INSEE_ID")
                        ->getId();

                $groupsData = GroupDataQuery::create()
                    ->filterByGroupTypeDataId($insee_id)
                    ->findByValue($insee);



                //verifier que l'une des valeurs trouvees provient bien d'une commune
                $isCity = false;
                foreach ($groupsData as $groupData) {
                    $group = GroupQuery::create()
                            ->findOneById($groupData->getGroupId());
                    if($group->getGroupTypeId() == $cityTypeId) {
                        $isCity = true;
                    }
                }

                if($isCity) {
                    $isInsee = true;
                }
                else {
                    $errorArray["insee_exist"][] = $rowNbr;
                }
            }
            else {
               $isInsee = true;
            }

        }
        elseif ($insee == null ||  $insee == "" ) {
            $isInsee = true;
        }
        else {
            $errorArray["insee_form"][] = $rowNbr;
        }

        return $isInsee;
    }

    /**
     * verification du code de structure
     * format + BDD  ( le format peut être nul )
     *
     * @param type $struct
     * @param type $errorArray
     * @param type $rowNbr
     * @param boolean $existTest si true verifie en base que l'element existe
     * @return boolean
     */
    private static function structureCheck($struct, &$errorArray, $rowNbr, $existTest = false)
    {
        if(trim($struct) == "")
        {
            //Si code structure vide, on affectera à l'école
            return true;
        }
        $isStruct = false;
        //if(\preg_match("`^[A-Z]{4}[0-9]{2}$`", $struct)) {
            if($existTest) {
                //STRUCTURE_ID ID
                $str_id = GroupTypeDataQuery::create()
                        ->findOneByGroupTypeDataTemplateUniqueName("STRUCTURE_ID")
                        ->getId();
                //récupérer toutes les valeurs
                $groupsData = GroupDataQuery::create()
                    ->filterByGroupTypeDataId($str_id)
                    ->findByValue($struct);

                //recuperer le grouptype id de la classe
                $classroomTypeId = \BNS\App\CoreBundle\Model\GroupTypeQuery::create()
                        ->findOneByType('CLASSROOM')
                        ->getId();

                //verifier que l'une des valeurs trouvees provient bien d'une ecole
                $isSchool = false;
                foreach ($groupsData as $groupData) {
                    $group = GroupQuery::create()
                            ->findOneById($groupData->getGroupId());
                    if($group->getGroupTypeId() == $classroomTypeId) {
                        $isSchool = true;
                    }
                }

                if($isSchool) {
                    $isStruct = true;
                }
                else {
                    $errorArray["structure_exist"][] = $rowNbr;
                }
            }
            else {
               $isStruct = true;
            }
        /*} else*/
        if ($struct == null ||  $struct == "" ) {
            $isStruct = true;
        }/*else {
            $errorArray["structure_form"][] = $rowNbr;
        }*/

        return $isStruct;
    }

    /**
     * verification email
     * format
     *
     * @param type $email
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function emailCheck($email, &$errorArray, $rowNbr)
    {
        $email = trim($email);
        $emailOk = false;

        if(preg_match('#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $email)) {
            $emailOk = true;
        }
        else {
            $errorArray["mail"][] = $rowNbr;
        }

        return $emailOk;
    }

    /**
     * verification string
     * longueur <= 255
     *
     * @param type $name
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function stringCheck($name, &$errorArray, $rowNbr)
    {
        $strinOk = false;

        if(\strlen($name) <= 255) {
            $strinOk = true;
        }
        else {
            $errorArray["string"][] = $rowNbr;
        }

        return $strinOk;
    }

    /**
     * verification code postal
     * format
     *
     * @param type $zipcode
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function zipCheck($zipcode, &$errorArray, $rowNbr)
    {
        $zipOk = false;

        if(\preg_match("`^[0-9]{5}$`", $zipcode)) {
            $zipOk = true;
        }
        else {
            $errorArray["zip"][] = $rowNbr;
        }

        return $zipOk;
    }

    /**
     * verification sexe
     * format (M ou F)
     *
     * @param type $sexe
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function sexeCheck($sexe, &$errorArray, $rowNbr)
    {
        $sexeOk = false;

        if(\preg_match("`^[MF12]$`", $sexe)) {
            $sexeOk = true;
        }
        else {
            $errorArray["sexe"][] = $rowNbr;
        }

        return $sexeOk;
    }

    /**
     * verification role
     * format (1, 2 ou 3)
     *
     * @param type $role
     * @param type $errorArray
     * @param type $rowNbr
     * @return boolean
     */
    private static function roleCheck($role, &$errorArray, $rowNbr)
    {
        $roleOk = false;

        if(\preg_match("`^[1-3]$`", $role)) {
            $roleOk = true;
        }
        else {
            $errorArray["role"][] = $rowNbr;
        }

        return $roleOk;
    }
}
